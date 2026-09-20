<?php

namespace App\Models;

use App\Domains\Results\Enums\PromotionDecisionType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Decision de passage d'une inscription (eleve x annee) : admis, redoublant ou
 * exclu. Voir la migration `create_promotion_decisions_table`.
 *
 * @property string $id
 * @property string $enrollment_id
 * @property PromotionDecisionType $decision
 * @property float|null $average
 * @property string|null $note
 * @property Carbon $decided_at
 */
class PromotionDecision extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = ['enrollment_id', 'decision', 'average', 'note', 'decided_by', 'decided_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'decision' => PromotionDecisionType::class,
            'average' => 'decimal:2',
            'decided_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
