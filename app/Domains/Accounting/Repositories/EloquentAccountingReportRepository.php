<?php

namespace App\Domains\Accounting\Repositories;

use App\Domains\Accounting\Contracts\AccountingReportRepositoryContract;
use App\Domains\Shared\Support\Period;
use App\Models\Payment;
use App\Models\TuitionInstallment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class EloquentAccountingReportRepository implements AccountingReportRepositoryContract
{
    public function collected(?Period $period, array $filters = []): array
    {
        $row = $this->payments($period, $filters)
            ->selectRaw('coalesce(sum(payments.amount), 0) as total_amount, count(*) as payments_count')
            ->first();

        return ['total' => round((float) $row->total_amount, 2), 'count' => (int) $row->payments_count];
    }

    public function collectedBy(string $column, ?Period $period, array $filters = []): array
    {
        // Colonne choisie dans une liste fermee : jamais reprise telle quelle
        // d'un parametre de requete.
        $column = match ($column) {
            'period_type' => 'payments.period_type',
            'method' => 'payments.method',
        };

        $totals = [];
        $rows = $this->payments($period, $filters)
            ->selectRaw("{$column} as bucket, sum(payments.amount) as total_amount, count(*) as payments_count")
            ->groupBy($column)
            ->get();

        foreach ($rows as $row) {
            $totals[$row->bucket] = ['total' => round((float) $row->total_amount, 2), 'count' => (int) $row->payments_count];
        }

        return $totals;
    }

    public function collectedByMonth(?Period $period, array $filters = []): array
    {
        $byMonth = [];

        foreach ($this->payments($period, $filters)->get(['payments.paid_at', 'payments.amount']) as $payment) {
            $month = $payment->paid_at->format('Y-m');
            $byMonth[$month] = round(($byMonth[$month] ?? 0.0) + (float) $payment->amount, 2);
        }

        ksort($byMonth);

        return $byMonth;
    }

    public function expected(?Period $period, array $filters = []): array
    {
        $row = $this->installments($period, $filters)
            ->selectRaw('coalesce(sum(tuition_installments.amount), 0) as total_amount, coalesce(sum(case when tuition_installments.payment_id is not null then tuition_installments.amount else 0 end), 0) as settled_amount')
            ->first();

        return ['total' => round((float) $row->total_amount, 2), 'settled' => round((float) $row->settled_amount, 2)];
    }

    public function arrears(?Period $period, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->overdue($period, $filters)
            ->selectRaw('tuition_installments.enrollment_id as enrollment_id, count(*) as months_overdue, sum(tuition_installments.amount) as total_amount, min(tuition_installments.month) as oldest_month')
            ->groupBy('tuition_installments.enrollment_id')
            ->orderByDesc('total_amount')
            ->orderBy('enrollment_id')
            ->with(['enrollment.student:id,first_name,last_name,matricule,guardian_phone', 'enrollment.schoolClass:id,name'])
            ->paginate($perPage)
            ->withQueryString();
    }

    public function arrearsTotals(?Period $period, array $filters = []): array
    {
        $row = $this->overdue($period, $filters)
            ->selectRaw('coalesce(sum(tuition_installments.amount), 0) as total_amount, count(*) as months_count, count(distinct tuition_installments.enrollment_id) as students_count')
            ->first();

        return [
            'amount' => round((float) $row->total_amount, 2),
            'students' => (int) $row->students_count,
            'months' => (int) $row->months_count,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Payment>
     */
    private function payments(?Period $period, array $filters): Builder
    {
        $query = Payment::query()
            ->whereNull('payments.cancelled_at')
            ->when($filters['school_class_id'] ?? null, fn ($q, $id) => $q->whereHas(
                'enrollment',
                fn ($enrollment) => $enrollment->where('school_class_id', $id),
            ))
            ->when($filters['enrollment_year'] ?? null, fn ($q, $year) => $q->whereHas(
                'enrollment',
                fn ($enrollment) => $enrollment->where('academic_year', $year),
            ));

        return $period?->constrain($query, 'payments.paid_at') ?? $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<TuitionInstallment>
     */
    private function installments(?Period $period, array $filters): Builder
    {
        $query = TuitionInstallment::query()
            ->when($filters['school_class_id'] ?? null, fn ($q, $id) => $q->whereHas(
                'enrollment',
                fn ($enrollment) => $enrollment->where('school_class_id', $id),
            ));

        return $period?->constrain($query, 'tuition_installments.month') ?? $query;
    }

    /**
     * Echeances non reglees dont le mois est termine. Le mois en cours n'est
     * pas un impaye : il est "a payer", pas "en retard".
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<TuitionInstallment>
     */
    private function overdue(?Period $period, array $filters): Builder
    {
        return $this->installments($period, $filters)
            ->whereNull('tuition_installments.payment_id')
            ->where('tuition_installments.month', '<', today()->startOfMonth()->toDateString())
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->whereHas(
                'enrollment.student',
                fn ($student) => $student
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('matricule', 'like', "%{$search}%"),
            ));
    }
}
