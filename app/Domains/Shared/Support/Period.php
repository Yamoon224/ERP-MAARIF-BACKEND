<?php

namespace App\Domains\Shared\Support;

use App\Models\Term;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * Periode demandee par le client pour filtrer une liste ou un indicateur :
 * une annee scolaire entiere, un trimestre, ou un mois.
 *
 * Les trois filtres se resolvent en un meme intervalle de dates, du plus fin
 * au plus large : `month` l'emporte sur `term_id`, qui l'emporte sur
 * `academic_year`. Le client peut donc envoyer l'annee choisie *et* un mois
 * sans conflit, et chaque depot n'a plus qu'une colonne de date a borner.
 */
final class Period
{
    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
    ) {}

    /** Regles de validation communes aux endpoints filtrables par periode.
     *
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'academic_year' => ['nullable', 'string', 'regex:/^\d{4}-\d{4}$/'],
            'term_id' => ['nullable', 'uuid', Rule::exists('terms', 'id')],
            'month' => ['nullable', 'date_format:Y-m'],
        ];
    }

    /** @param  array<string, mixed>  $filters */
    public static function fromFilters(array $filters): ?self
    {
        $month = $filters['month'] ?? null;
        if (is_string($month) && $month !== '') {
            $start = CarbonImmutable::createFromFormat('!Y-m', $month);

            return new self($start, $start->endOfMonth()->startOfDay());
        }

        $termId = $filters['term_id'] ?? null;
        if (is_string($termId) && $termId !== '') {
            $term = Term::query()->findOrFail($termId);

            return self::forTerm($term);
        }

        $year = $filters['academic_year'] ?? null;
        if (is_string($year) && $year !== '') {
            return self::forAcademicYear($year);
        }

        return null;
    }

    /**
     * Vrai quand seule une annee scolaire est demandee, sans trimestre ni
     * mois : certaines mesures (l'encaissement d'une annee) se rattachent
     * alors a l'annee elle-meme plutot qu'a un intervalle de dates.
     *
     * @param  array<string, mixed>  $filters
     */
    public static function isWholeYear(array $filters): bool
    {
        return ! empty($filters['academic_year']) && empty($filters['term_id']) && empty($filters['month']);
    }

    public static function forTerm(Term $term): self
    {
        return new self(
            CarbonImmutable::parse($term->starts_at)->startOfDay(),
            CarbonImmutable::parse($term->ends_at)->startOfDay(),
        );
    }

    /**
     * Une annee scolaire s'etend du premier au dernier jour de ses trimestres.
     * Sans trimestre defini, on retombe sur le calendrier civil habituel
     * (1er septembre - 31 aout) pour que le filtre reste utilisable.
     */
    public static function forAcademicYear(string $academicYear): self
    {
        $bounds = Term::query()
            ->where('academic_year', $academicYear)
            ->selectRaw('min(starts_at) as first_day, max(ends_at) as last_day')
            ->first();

        if ($bounds?->first_day !== null && $bounds->last_day !== null) {
            return new self(
                CarbonImmutable::parse($bounds->first_day)->startOfDay(),
                CarbonImmutable::parse($bounds->last_day)->startOfDay(),
            );
        }

        [$startYear, $endYear] = array_map('intval', explode('-', $academicYear));

        return new self(
            CarbonImmutable::create($startYear, 9, 1)->startOfDay(),
            CarbonImmutable::create($endYear, 8, 31)->startOfDay(),
        );
    }

    /**
     * Borne une colonne de date (ou de date-heure) sur la periode demandee.
     * Sans filtre de periode, la requete est retournee telle quelle.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<TModel>
     */
    public static function scope(Builder $query, array $filters, string $column): Builder
    {
        $period = self::fromFilters($filters);

        return $period === null ? $query : $period->constrain($query, $column);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function constrain(Builder $query, string $column): Builder
    {
        return $query
            ->whereDate($column, '>=', $this->from->toDateString())
            ->whereDate($column, '<=', $this->to->toDateString());
    }

    /** @return array{from: string, to: string} */
    public function toArray(): array
    {
        return ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString()];
    }
}
