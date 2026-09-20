<?php

namespace App\Domains\Results\Repositories;

use App\Domains\Results\Contracts\PromotionDecisionRepositoryContract;
use App\Models\PromotionDecision;
use Illuminate\Support\Collection;

final class EloquentPromotionDecisionRepository implements PromotionDecisionRepositoryContract
{
    public function forEnrollments(array $enrollmentIds): Collection
    {
        if ($enrollmentIds === []) {
            return new Collection;
        }

        return PromotionDecision::query()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->get()
            ->keyBy('enrollment_id');
    }

    public function save(string $enrollmentId, string $decision, ?float $average, ?string $note, string $userId): PromotionDecision
    {
        return PromotionDecision::query()->updateOrCreate(
            ['enrollment_id' => $enrollmentId],
            [
                'decision' => $decision,
                'average' => $average,
                'note' => $note,
                'decided_by' => $userId,
                'decided_at' => now(),
            ],
        );
    }
}
