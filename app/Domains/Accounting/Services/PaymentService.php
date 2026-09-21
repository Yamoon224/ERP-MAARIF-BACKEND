<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Contracts\InstallmentRepositoryContract;
use App\Domains\Accounting\Contracts\PaymentRepositoryContract;
use App\Domains\Accounting\Enums\PaymentPeriod;
use App\Domains\Accounting\Exceptions\AccountingException;
use App\Domains\Accounting\Support\ReceiptNumberGenerator;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\TuitionInstallment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Encaissement de la scolarite.
 *
 * La scolarite est mensuelle ; la formule (mensuel, trimestre, semestre,
 * annee) dit seulement combien de mois on regle d'un coup. Le paiement couvre
 * toujours les mois impayes les plus anciens d'abord : on ne peut pas payer
 * decembre en laissant octobre en retard. Le montant est calcule ici, jamais
 * fourni par le client.
 */
final class PaymentService
{
    public function __construct(
        private readonly PaymentRepositoryContract $payments,
        private readonly InstallmentRepositoryContract $installments,
        private readonly TuitionService $tuition,
        private readonly ReceiptNumberGenerator $receipts,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Payment>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->payments->paginate($filters, $perPage);
    }

    public function find(string $id): Payment
    {
        return $this->payments->findOrFail($id);
    }

    /**
     * Simule un paiement sans l'enregistrer : quels mois, quel montant.
     *
     * @return array{period: string, requested_months: int|null, months: list<string>, amount: float}
     */
    public function preview(Enrollment $enrollment, PaymentPeriod $period): array
    {
        $this->tuition->ensureInstallments($enrollment);

        $unpaid = $this->installments->unpaidForEnrollment($enrollment->id);
        $selected = $this->select($unpaid, $period);

        return [
            'period' => $period->value,
            'requested_months' => $period->months(),
            'months' => $selected->map(fn (TuitionInstallment $i) => $i->month->format('Y-m'))->values()->all(),
            'amount' => $this->total($selected),
        ];
    }

    /** @param  array<string, mixed>  $data  method, reference, paid_at, note */
    public function register(Enrollment $enrollment, PaymentPeriod $period, array $data, string $receivedByUserId): Payment
    {
        $this->tuition->ensureInstallments($enrollment);

        return DB::transaction(function () use ($enrollment, $period, $data, $receivedByUserId): Payment {
            // Verrou pris dans la transaction : deux encaissements simultanes
            // ne peuvent pas regler les memes mois.
            $selected = $this->select($this->installments->unpaidForEnrollment($enrollment->id, lock: true), $period);

            if ($selected->isEmpty()) {
                throw AccountingException::nothingToPay();
            }

            return $this->record($enrollment, $period, $selected, $data, $receivedByUserId);
        });
    }

    /**
     * Enregistre un paiement pour des mois précis, et non "les N plus anciens" :
     * c'est le cas d'un paiement confirmé après coup (mobile money), dont les
     * mois et le montant ont été figés à l'initiation. Si l'un de ces mois a été
     * réglé entre-temps, ou si le tarif a changé, rien n'est enregistré.
     *
     * @param  list<string>  $months  au format `YYYY-MM`
     * @param  array<string, mixed>  $data  method, reference, paid_at, note
     */
    public function registerMonths(Enrollment $enrollment, PaymentPeriod $period, array $months, float $expectedAmount, array $data, ?string $receivedByUserId = null): Payment
    {
        $this->tuition->ensureInstallments($enrollment);

        return DB::transaction(function () use ($enrollment, $period, $months, $expectedAmount, $data, $receivedByUserId): Payment {
            $selected = $this->installments->unpaidForEnrollment($enrollment->id, lock: true)
                ->filter(fn (TuitionInstallment $i) => in_array($i->month->format('Y-m'), $months, true))
                ->values();

            if ($selected->count() !== count($months)) {
                throw AccountingException::monthsAlreadySettled();
            }

            if (abs($this->total($selected) - $expectedAmount) > 0.005) {
                throw AccountingException::amountChanged();
            }

            return $this->record($enrollment, $period, $selected, $data, $receivedByUserId);
        });
    }

    /**
     * @param  Collection<int, TuitionInstallment>  $selected
     * @param  array<string, mixed>  $data
     */
    private function record(Enrollment $enrollment, PaymentPeriod $period, Collection $selected, array $data, ?string $receivedByUserId): Payment
    {
        $payment = $this->payments->create([
            'receipt_number' => $this->receipts->next(Carbon::now()),
            'enrollment_id' => $enrollment->id,
            'period_type' => $period->value,
            'months' => $selected->map(fn (TuitionInstallment $i) => $i->month->format('Y-m'))->values()->all(),
            'amount' => $this->total($selected),
            'method' => $data['method'],
            'reference' => $data['reference'] ?? null,
            'paid_at' => $data['paid_at'] ?? today()->toDateString(),
            'note' => $data['note'] ?? null,
            'received_by' => $receivedByUserId,
        ]);

        $this->installments->assignToPayment($selected->pluck('id')->all(), $payment->id);

        return $this->payments->findOrFail($payment->id);
    }

    /** Annule un paiement : ses mois redeviennent a payer, le recu reste consultable. */
    public function cancel(Payment $payment, string $reason, string $cancelledByUserId): Payment
    {
        if ($payment->isCancelled()) {
            throw AccountingException::alreadyCancelled();
        }

        return DB::transaction(function () use ($payment, $reason, $cancelledByUserId): Payment {
            $this->installments->releasePayment($payment->id);

            return $this->payments->update($payment, [
                'cancelled_at' => Carbon::now(),
                'cancelled_by' => $cancelledByUserId,
                'cancellation_reason' => $reason,
            ]);
        });
    }

    /**
     * Mois couverts par la formule : les N plus anciens impayes, ou tous pour
     * la formule annuelle. S'il en reste moins que N, on regle ce qui reste.
     *
     * @param  Collection<int, TuitionInstallment>  $unpaid  tries du plus ancien au plus recent
     * @return Collection<int, TuitionInstallment>
     */
    private function select(Collection $unpaid, PaymentPeriod $period): Collection
    {
        $months = $period->months();

        return $months === null ? $unpaid->values() : $unpaid->take($months)->values();
    }

    /** @param  Collection<int, TuitionInstallment>  $installments */
    private function total(Collection $installments): float
    {
        return round((float) $installments->sum(fn (TuitionInstallment $i) => (float) $i->amount), 2);
    }
}
