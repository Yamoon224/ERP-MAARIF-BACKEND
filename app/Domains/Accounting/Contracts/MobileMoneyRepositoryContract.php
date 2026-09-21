<?php

namespace App\Domains\Accounting\Contracts;

use App\Models\MobileMoneyTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface MobileMoneyRepositoryContract
{
    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): MobileMoneyTransaction;

    public function findOrFail(string $id): MobileMoneyTransaction;

    /** Relit la transaction en posant un verrou : à appeler dans une transaction de base de données. */
    public function lockOrFail(string $id): MobileMoneyTransaction;

    /** @param  array<string, mixed>  $attributes */
    public function update(MobileMoneyTransaction $transaction, array $attributes): MobileMoneyTransaction;

    /** Demande encore en attente pour cette inscription, s'il y en a une. */
    public function pendingForEnrollment(string $enrollmentId): ?MobileMoneyTransaction;

    /** @return Collection<int, MobileMoneyTransaction> */
    public function allPending(): Collection;

    /**
     * @param  array<string, mixed>  $filters  `student_id`, `status`
     * @return LengthAwarePaginator<int, MobileMoneyTransaction>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;
}
