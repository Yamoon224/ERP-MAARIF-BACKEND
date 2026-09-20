<?php

namespace App\Domains\Academics\Services;

use App\Domains\Academics\Contracts\TermRepositoryContract;
use App\Models\Term;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Un seul trimestre "courant" a la fois : c'est celui que les ecrans de
 * saisie de notes proposent par defaut a l'enseignant.
 */
final class TermService
{
    public function __construct(private readonly TermRepositoryContract $terms) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Term>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->terms->paginate($filters, $perPage);
    }

    /** @return Collection<int, Term> */
    public function all(): Collection
    {
        return $this->terms->all();
    }

    public function find(string $id): Term
    {
        return $this->terms->findOrFail($id);
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data): Term
    {
        return DB::transaction(function () use ($data): Term {
            $term = $this->terms->create($data);

            if ($term->is_current) {
                $this->terms->clearCurrentExcept($term);
            }

            return $term;
        });
    }

    /** @param  array<string, mixed>  $data */
    public function update(Term $term, array $data): Term
    {
        return DB::transaction(function () use ($term, $data): Term {
            $updated = $this->terms->update($term, $data);

            if ($updated->is_current) {
                $this->terms->clearCurrentExcept($updated);
            }

            return $updated;
        });
    }

    public function delete(Term $term): void
    {
        $this->terms->delete($term);
    }
}
