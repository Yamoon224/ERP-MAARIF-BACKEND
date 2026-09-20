<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Academics\Support\AcademicCalendar;
use App\Domains\Accounting\Contracts\InstallmentRepositoryContract;
use App\Models\Enrollment;
use App\Models\TuitionInstallment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Scolarite d'une inscription : une echeance par mois scolaire, au tarif
 * mensuel de la classe.
 *
 * Les echeances sont generees a la demande plutot qu'a l'inscription : la
 * comptabilite peut fixer ou corriger le tarif d'une classe apres coup, et la
 * synchronisation (`ensureInstallments`) rattrape alors les mois non regles
 * sans toucher a ce qui a deja ete paye.
 */
final class TuitionService
{
    public function __construct(private readonly InstallmentRepositoryContract $installments) {}

    /**
     * Cree les echeances manquantes et aligne le montant des echeances non
     * reglees sur le tarif actuel de la classe. Une echeance reglee garde le
     * montant qui a ete effectivement paye.
     */
    public function ensureInstallments(Enrollment $enrollment): void
    {
        $enrollment->loadMissing('schoolClass:id,name,level,monthly_fee');

        $fee = (float) ($enrollment->schoolClass?->monthly_fee ?? 0);
        if ($fee <= 0) {
            return;
        }

        $months = AcademicCalendar::months($enrollment->academic_year);
        if ($months === []) {
            return;
        }

        $existing = $this->installments->forEnrollment($enrollment->id)
            ->keyBy(fn (TuitionInstallment $installment) => $installment->month->format('Y-m'));

        DB::transaction(function () use ($months, $existing, $enrollment, $fee): void {
            foreach ($months as $month) {
                $installment = $existing->get($month->format('Y-m'));

                if ($installment === null) {
                    $this->installments->create([
                        'enrollment_id' => $enrollment->id,
                        'month' => $month->toDateString(),
                        'amount' => $fee,
                    ]);
                } elseif (! $installment->isPaid() && (float) $installment->amount !== $fee) {
                    $this->installments->updateAmount($installment, $fee);
                }
            }
        });
    }

    /**
     * Releve de scolarite : chaque mois avec son etat, et les totaux.
     *
     * Etats d'un mois : `paid` (regle), `overdue` (non regle, mois termine),
     * `due` (non regle, mois en cours), `upcoming` (mois a venir).
     *
     * @return array{installments: list<array<string, mixed>>, totals: array<string, float|int>}
     */
    public function statement(Enrollment $enrollment): array
    {
        $this->ensureInstallments($enrollment);

        $currentMonth = today()->startOfMonth();

        $items = $this->installments->forEnrollment($enrollment->id)
            ->map(fn (TuitionInstallment $installment) => [
                'id' => $installment->id,
                'month' => $installment->month->format('Y-m'),
                'amount' => (float) $installment->amount,
                'status' => $this->statusOf($installment, $currentMonth),
                'paid_at' => $installment->payment?->paid_at?->toDateString(),
                'payment' => $installment->payment === null ? null : [
                    'id' => $installment->payment->id,
                    'receipt_number' => $installment->payment->receipt_number,
                ],
            ])
            ->values();

        $sum = fn (?string $status = null): float => round(
            (float) $items->when($status !== null, fn ($rows) => $rows->where('status', $status))->sum('amount'),
            2,
        );

        return [
            'installments' => $items->all(),
            'totals' => [
                'total' => $sum(),
                'paid' => $sum('paid'),
                'remaining' => round($sum() - $sum('paid'), 2),
                'overdue_amount' => $sum('overdue'),
                'overdue_months' => $items->where('status', 'overdue')->count(),
                'months_total' => $items->count(),
                'months_paid' => $items->where('status', 'paid')->count(),
            ],
        ];
    }

    private function statusOf(TuitionInstallment $installment, Carbon $currentMonth): string
    {
        if ($installment->isPaid()) {
            return 'paid';
        }

        return match (true) {
            $installment->month->lt($currentMonth) => 'overdue',
            $installment->month->eq($currentMonth) => 'due',
            default => 'upcoming',
        };
    }
}
