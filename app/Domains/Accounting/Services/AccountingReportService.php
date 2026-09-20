<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Contracts\AccountingReportRepositoryContract;
use App\Domains\Accounting\Enums\PaymentMethod;
use App\Domains\Accounting\Enums\PaymentPeriod;
use App\Domains\Shared\Support\Period;
use App\Models\TuitionInstallment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Tableau de bord comptable : encaissements, taux de recouvrement, impayes. */
final class AccountingReportService
{
    public function __construct(private readonly AccountingReportRepositoryContract $reports) {}

    /**
     * @param  array<string, mixed>  $filters  academic_year / term_id / month, school_class_id
     * @return array<string, mixed>
     */
    public function summary(array $filters): array
    {
        $period = Period::fromFilters($filters);
        $scope = array_intersect_key($filters, ['school_class_id' => true]);

        $expected = $this->reports->expected($period, $scope);

        return [
            'period' => $period?->toArray(),
            'collected' => $this->reports->collected($period, $scope),
            'by_period_type' => $this->bucketed(
                $this->reports->collectedBy('period_type', $period, $scope),
                PaymentPeriod::cases(),
            ),
            'by_method' => $this->bucketed(
                $this->reports->collectedBy('method', $period, $scope),
                PaymentMethod::cases(),
            ),
            'by_month' => $this->byMonth($period, $scope),
            'expected' => [
                ...$expected,
                'rate' => $expected['total'] > 0 ? round($expected['settled'] / $expected['total'] * 100, 1) : null,
            ],
            'arrears' => $this->reports->arrearsTotals($period, $scope),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, TuitionInstallment>
     */
    public function arrears(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->reports->arrears(
            Period::fromFilters($filters),
            array_intersect_key($filters, ['school_class_id' => true, 'search' => true]),
            $perPage,
        );
    }

    /**
     * Toutes les valeurs de l'enum apparaissent, meme a zero : un graphique ou
     * un tableau dont les lignes changent d'une periode a l'autre est illisible.
     *
     * @param  array<string, array{total: float, count: int}>  $totals
     * @param  list<PaymentPeriod|PaymentMethod>  $cases
     * @return list<array{key: string, label: string, total: float, count: int}>
     */
    private function bucketed(array $totals, array $cases): array
    {
        return array_map(fn (PaymentPeriod|PaymentMethod $case) => [
            'key' => $case->value,
            'label' => $case->label(),
            'total' => $totals[$case->value]['total'] ?? 0.0,
            'count' => $totals[$case->value]['count'] ?? 0,
        ], $cases);
    }

    /**
     * Encaissement mois par mois. Sur une periode connue, chaque mois y figure
     * (a zero s'il n'y a rien eu) pour que l'histogramme ait toujours la meme
     * echelle.
     *
     * @param  array<string, mixed>  $scope
     * @return list<array{month: string, total: float}>
     */
    private function byMonth(?Period $period, array $scope): array
    {
        $collected = $this->reports->collectedByMonth($period, $scope);

        if ($period !== null) {
            $month = $period->from->startOfMonth();
            $last = $period->to->startOfMonth();

            for (; $month <= $last; $month = $month->addMonth()) {
                $collected[$month->format('Y-m')] ??= 0.0;
            }
            ksort($collected);
        }

        return array_map(
            fn (string $month, float $total) => ['month' => $month, 'total' => $total],
            array_keys($collected),
            array_values($collected),
        );
    }
}
