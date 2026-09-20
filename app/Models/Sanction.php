<?php

namespace App\Models;

use App\Domains\Discipline\Enums\SanctionType;
use Database\Factories\SanctionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sanction disciplinaire, y compris un renvoi definitif (cahier des charges
 * 3.2 : "historique des sanctions disciplinaires").
 *
 * @property string $id
 * @property string $student_id
 * @property SanctionType $type
 */
class Sanction extends Model
{
    /** @use HasFactory<SanctionFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = ['student_id', 'type', 'reason', 'start_date', 'end_date', 'created_by', 'notified_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => SanctionType::class,
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'notified_at' => 'datetime',
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
