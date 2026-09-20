<?php

namespace App\Domains\Academics\Services;

use App\Domains\Academics\Contracts\SchoolClassRepositoryContract;
use App\Domains\Academics\Contracts\TermRepositoryContract;
use App\Domains\Shared\Support\Period;
use App\Models\Term;
use Illuminate\Support\Collection;

/**
 * Annees scolaires connues du systeme, avec leurs trimestres : c'est ce qui
 * alimente les filtres "Annee scolaire / Trimestre / Mois" des ecrans.
 *
 * Une annee existe des qu'un trimestre ou une classe la mentionne, plus
 * besoin de la "creer" a part.
 */
final class AcademicYearService
{
    public function __construct(
        private readonly TermRepositoryContract $terms,
        private readonly SchoolClassRepositoryContract $classes,
    ) {}

    /**
     * Annee la plus recente en premier.
     *
     * @return Collection<int, array{label: string, starts_at: string, ends_at: string, is_current: bool, terms: list<array{id: string, name: string, starts_at: string, ends_at: string, is_current: bool}>}>
     */
    public function all(): Collection
    {
        $termsByYear = $this->terms->all()->groupBy('academic_year');

        $labels = $termsByYear->keys()
            ->merge($this->classes->academicYears())
            ->unique()
            ->sortDesc()
            ->values();

        return $labels->map(function (string $label) use ($termsByYear): array {
            $period = Period::forAcademicYear($label);
            $terms = $termsByYear->get($label, collect());

            return [
                'label' => $label,
                'starts_at' => $period->from->toDateString(),
                'ends_at' => $period->to->toDateString(),
                'is_current' => $terms->contains(fn (Term $term) => $term->is_current),
                'terms' => $terms->sortBy('starts_at')->map(fn (Term $term) => [
                    'id' => $term->id,
                    'name' => $term->name,
                    'starts_at' => $term->starts_at->toDateString(),
                    'ends_at' => $term->ends_at->toDateString(),
                    'is_current' => $term->is_current,
                ])->values()->all(),
            ];
        });
    }
}
