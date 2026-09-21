<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Contracts\MobileMoneyGatewayContract;
use App\Domains\Accounting\Contracts\MobileMoneyRepositoryContract;
use App\Domains\Accounting\Enums\MobileMoneyOperator;
use App\Domains\Accounting\Enums\MobileMoneyStatus;
use App\Domains\Accounting\Enums\PaymentMethod;
use App\Domains\Accounting\Enums\PaymentPeriod;
use App\Domains\Accounting\Exceptions\AccountingException;
use App\Domains\Accounting\Exceptions\MobileMoneyException;
use App\Models\Enrollment;
use App\Models\MobileMoneyTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Paiement de la scolarité par mobile money, à l'initiative du parent.
 *
 * Cycle : `initiate` fige les mois et le montant (calculés ici, jamais fournis
 * par le client) et demande à l'opérateur d'envoyer l'invite de paiement ;
 * `refresh` interroge l'opérateur et, à la confirmation, crée le `Payment`
 * (avec son reçu) qui règle les mois. Tant que l'opérateur n'a pas confirmé,
 * la comptabilité n'est pas touchée.
 */
final class MobileMoneyService
{
    public function __construct(
        private readonly MobileMoneyRepositoryContract $transactions,
        private readonly MobileMoneyGatewayContract $gateway,
        private readonly PaymentService $payments,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, MobileMoneyTransaction>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->transactions->paginate($filters, $perPage);
    }

    public function find(string $id): MobileMoneyTransaction
    {
        return $this->transactions->findOrFail($id);
    }

    public function initiate(Enrollment $enrollment, PaymentPeriod $period, MobileMoneyOperator $operator, string $phone): MobileMoneyTransaction
    {
        // Une demande abandonnée depuis longtemps ne doit pas bloquer une nouvelle tentative.
        $stale = $this->transactions->pendingForEnrollment($enrollment->id);
        if ($stale !== null) {
            $stale = $this->refresh($stale);

            if ($stale->status === MobileMoneyStatus::Pending) {
                throw MobileMoneyException::alreadyPending();
            }
        }

        $preview = $this->payments->preview($enrollment, $period);
        if ($preview['months'] === []) {
            throw AccountingException::nothingToPay();
        }

        $transaction = $this->transactions->create([
            'reference' => $this->newReference(),
            'enrollment_id' => $enrollment->id,
            'operator' => $operator->value,
            'phone' => $phone,
            'period_type' => $period->value,
            'months' => $preview['months'],
            'amount' => $preview['amount'],
            'status' => MobileMoneyStatus::Pending->value,
            'expires_at' => Carbon::now()->addMinutes((int) config('mobile_money.expires_after_minutes', 15)),
        ]);

        $result = $this->gateway->requestPayment($transaction);

        return $this->transactions->update($transaction, $result->status === MobileMoneyStatus::Failed
            ? ['status' => MobileMoneyStatus::Failed->value, 'provider_reference' => $result->providerReference, 'failure_reason' => $result->reason]
            : ['provider_reference' => $result->providerReference]);
    }

    /** Interroge l'opérateur pour une demande en attente et en tire les conséquences. */
    public function refresh(MobileMoneyTransaction $transaction): MobileMoneyTransaction
    {
        if ($transaction->status->isFinal()) {
            return $transaction;
        }

        $result = $this->gateway->checkStatus($transaction);

        // La confirmation prime sur l'expiration : si l'opérateur a débité le
        // parent à la dernière minute, l'argent est pris et doit être imputé.
        return match ($result->status) {
            MobileMoneyStatus::Successful => $this->settle($transaction),
            MobileMoneyStatus::Failed => $this->transactions->update($transaction, [
                'status' => MobileMoneyStatus::Failed->value,
                'failure_reason' => $result->reason,
            ]),
            default => $transaction->hasExpired()
                ? $this->transactions->update($transaction, [
                    'status' => MobileMoneyStatus::Expired->value,
                    'failure_reason' => "La demande n'a pas été validée à temps.",
                ])
                : $transaction,
        };
    }

    /** Actualise toutes les demandes en attente (tâche planifiée) : renvoie leur nombre. */
    public function reconcilePending(): int
    {
        $pending = $this->transactions->allPending();
        $pending->each(fn (MobileMoneyTransaction $transaction) => $this->refresh($transaction));

        return $pending->count();
    }

    /**
     * Confirmation reçue : crée le paiement et règle les mois. Si les mois ont
     * été réglés entre-temps (ou si le tarif a changé), aucun paiement n'est créé :
     * la demande passe « à vérifier » pour que la comptabilité rembourse.
     */
    private function settle(MobileMoneyTransaction $transaction): MobileMoneyTransaction
    {
        return DB::transaction(function () use ($transaction): MobileMoneyTransaction {
            // Relecture verrouillée : deux actualisations simultanées ne créent qu'un paiement.
            $locked = $this->transactions->lockOrFail($transaction->id);

            if ($locked->status->isFinal()) {
                return $this->transactions->findOrFail($locked->id);
            }

            $enrollment = Enrollment::query()->findOrFail($locked->enrollment_id);

            try {
                $payment = $this->payments->registerMonths(
                    $enrollment,
                    $locked->period_type,
                    $locked->months,
                    (float) $locked->amount,
                    [
                        'method' => PaymentMethod::MobileMoney->value,
                        'reference' => $locked->provider_reference ?? $locked->reference,
                        'note' => "Paiement en ligne via {$locked->operator->label()} ({$locked->phone})",
                    ],
                );
            } catch (AccountingException $exception) {
                return $this->transactions->update($locked, [
                    'status' => MobileMoneyStatus::NeedsReview->value,
                    'failure_reason' => $exception->getMessage(),
                ]);
            }

            return $this->transactions->update($locked, [
                'status' => MobileMoneyStatus::Successful->value,
                'payment_id' => $payment->id,
                'confirmed_at' => Carbon::now(),
            ]);
        });
    }

    private function newReference(): string
    {
        return 'MM-'.Carbon::now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }
}
