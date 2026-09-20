<?php

namespace App\Domains\Discipline\Services;

use App\Domains\Discipline\Contracts\SanctionRepositoryContract;
use App\Domains\Notifications\Services\GuardianNotifier;
use App\Models\Sanction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Sanctions disciplinaires, y compris les renvois (cahier des charges 3.2).
 * Creer une sanction notifie immediatement le tuteur.
 */
final class SanctionService
{
    public function __construct(
        private readonly SanctionRepositoryContract $sanctions,
        private readonly GuardianNotifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Sanction>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->sanctions->paginate($filters, $perPage);
    }

    public function find(string $id): Sanction
    {
        return $this->sanctions->findOrFail($id);
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data, string $createdByUserId): Sanction
    {
        $sanction = $this->sanctions->create([...$data, 'created_by' => $createdByUserId]);

        $this->notifier->notifySanction($sanction);

        return $this->sanctions->update($sanction, ['notified_at' => Carbon::now()]);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Sanction $sanction, array $data): Sanction
    {
        return $this->sanctions->update($sanction, $data);
    }

    public function delete(Sanction $sanction): void
    {
        $this->sanctions->delete($sanction);
    }
}
