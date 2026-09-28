<?php

namespace App\Models;

use App\Domains\Results\Enums\PromotionDecisionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tranche du bareme de passage et d'appreciation d'une classe. Voir
 * App\Domains\Results\Services\GradeScaleService.
 *
 * @property string $id
 * @property string $school_class_id
 * @property float $min_average
 * @property float $max_average
 * @property string $label
 * @property PromotionDecisionType|null $decision
 */
class GradeScaleBand extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = ['school_class_id', 'min_average', 'max_average', 'label', 'decision'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'min_average' => 'float',
            'max_average' => 'float',
            'decision' => PromotionDecisionType::class,
        ];
    }

    /** @return BelongsTo<SchoolClass, $this> */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }
}
