<?php

namespace App\Domains\Results\Repositories;

use App\Domains\Results\Contracts\GradeScaleRepositoryContract;
use App\Models\GradeScaleBand;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EloquentGradeScaleRepository implements GradeScaleRepositoryContract
{
    public function forClass(string $schoolClassId): Collection
    {
        return GradeScaleBand::query()
            ->where('school_class_id', $schoolClassId)
            ->orderBy('min_average')
            ->get();
    }

    public function replaceForClass(string $schoolClassId, array $bands): Collection
    {
        DB::transaction(function () use ($schoolClassId, $bands): void {
            GradeScaleBand::query()->where('school_class_id', $schoolClassId)->delete();

            foreach ($bands as $band) {
                GradeScaleBand::create([...$band, 'school_class_id' => $schoolClassId]);
            }
        });

        return $this->forClass($schoolClassId);
    }
}
