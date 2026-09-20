<?php

namespace App\Domains\Discipline\Services;

use App\Domains\Discipline\Contracts\SummonRepositoryContract;
use App\Domains\Notifications\Services\GuardianNotifier;
use App\Models\Summon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Convocations des parents (cahier des charges 3.2/3.3). Creer une
 * convocation notifie immediatement le tuteur.
 */
final class SummonService
{
    public function __construct(
        private readonly SummonRepositoryContract $summons,
        private readonly GuardianNotifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Summon>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->summons->paginate($filters, $perPage);
    }

    public function find(string $id): Summon
    {
        return $this->summons->findOrFail($id);
    }

    /** @param  array<string, mixed>  $data */
    public function create(array $data, string $createdByUserId): Summon
    {
        $summon = $this->summons->create([...$data, 'created_by' => $createdByUserId]);

        $this->notifier->notifySummon($summon);

        return $this->summons->update($summon, ['notified_at' => Carbon::now()]);
    }

    /** @param  array<string, mixed>  $data */
    public function update(Summon $summon, array $data): Summon
    {
        return $this->summons->update($summon, $data);
    }

    public function delete(Summon $summon): void
    {
        $this->summons->delete($summon);
    }
}
