<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Academics\Contracts\SchoolClassRepositoryContract;
use App\Domains\Academics\Services\AcademicYearService;
use App\Domains\Accounting\Services\AccountingReportService;
use App\Domains\Attendance\Contracts\AttendanceRepositoryContract;
use App\Domains\Discipline\Contracts\SanctionRepositoryContract;
use App\Domains\Discipline\Contracts\SummonRepositoryContract;
use App\Domains\Grades\Contracts\GradeRepositoryContract;
use App\Domains\Shared\Support\Period;
use App\Domains\Students\Contracts\EnrollmentRepositoryContract;

/**
 * Tableau de bord filtrable par annee scolaire, trimestre ou mois.
 *
 * L'annee scolaire est toujours definie (celle du trimestre courant si le
 * client n'en choisit pas) : les effectifs se lisent par annee, et le
 * trimestre ou le mois choisi ne fait que restreindre les autres
 * indicateurs a l'interieur de cette annee.
 */
final class DashboardService
{
    public function __construct(
        private readonly AcademicYearService $years,
        private readonly SchoolClassRepositoryContract $classes,
        private readonly EnrollmentRepositoryContract $enrollments,
        private readonly GradeRepositoryContract $grades,
        private readonly AttendanceRepositoryContract $attendance,
        private readonly SanctionRepositoryContract $sanctions,
        private readonly SummonRepositoryContract $summons,
        private readonly AccountingReportService $accounting,
    ) {}

    /**
     * @param  array<string, mixed>  $filters  academic_year, term_id, month
     * @return array<string, mixed>
     */
    public function stats(array $filters, bool $includeDiscipline, bool $includeAccounting): array
    {
        $year = $filters['academic_year'] ?? $this->years->defaultYear();
        $scope = ['academic_year' => $year, ...array_intersect_key($filters, ['term_id' => true, 'month' => true])];

        $averages = $this->grades->studentAverages($scope);
        $attendance = $this->attendance->summary($scope, 0);

        return [
            'academic_year' => $year,
            'period' => $year === null ? null : Period::fromFilters($scope)?->toArray(),
            'students' => $year === null ? 0 : $this->enrollments->countForYear($year),
            'classes' => $year === null ? 0 : $this->classes->count(['academic_year' => $year]),
            'grades' => [
                'count' => $this->grades->count($scope),
                'average' => $averages->isEmpty() ? null : round((float) $averages->avg(), 2),
            ],
            'attendance' => [
                'present' => $attendance['present'],
                'absent' => $attendance['absent'],
                'late' => $attendance['late'],
                'unjustified_absences' => $attendance['unjustified_absences'],
            ],
            'discipline' => $includeDiscipline ? [
                'sanctions' => $this->sanctions->count($scope),
                'summons' => $this->summons->count($scope),
                'summons_pending' => $this->summons->count([...$scope, 'status' => 'pending']),
            ] : null,
            'accounting' => $includeAccounting ? $this->accountingStats($scope) : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return array{collected: float, arrears: float, recovery_rate: float|null}
     */
    private function accountingStats(array $scope): array
    {
        $summary = $this->accounting->summary($scope);

        return [
            'collected' => $summary['collected']['total'],
            'arrears' => $summary['arrears']['amount'],
            'recovery_rate' => $summary['expected']['rate'],
        ];
    }
}
