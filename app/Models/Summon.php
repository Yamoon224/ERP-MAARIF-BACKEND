<?php

namespace App\Models;

use App\Domains\Discipline\Enums\SummonStatus;
use Database\Factories\SummonFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Convocation d'un parent par l'etablissement (cahier des charges 3.2/3.3).
 *
 * @property string $id
 * @property string $student_id
 * @property SummonStatus $status
 */
class Summon extends Model
{
    /** @use HasFactory<SummonFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = ['student_id', 'reason', 'scheduled_at', 'location', 'status', 'created_by', 'notified_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'notified_at' => 'datetime',
            'status' => SummonStatus::class,
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
