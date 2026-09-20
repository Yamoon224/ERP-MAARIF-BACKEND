<?php

namespace App\Models;

use App\Domains\Admissions\Enums\AdmissionStatus;
use Database\Factories\AdmissionApplicationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Dossier de candidature d'un futur eleve (voir la migration
 * `create_admission_applications_table`).
 *
 * @property string $id
 * @property string $reference
 * @property string $academic_year
 * @property string $level
 * @property string $first_name
 * @property string $last_name
 * @property string|null $birth_date
 * @property AdmissionStatus $status
 * @property Carbon|null $decided_at
 * @property string|null $student_id
 */
class AdmissionApplication extends Model
{
    /** @use HasFactory<AdmissionApplicationFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'reference', 'academic_year', 'level',
        'first_name', 'last_name', 'gender', 'birth_date', 'previous_school',
        'guardian_name', 'guardian_phone', 'guardian_email', 'address', 'notes',
        'status', 'submitted_on', 'decision_note', 'decided_at', 'decided_by',
        'student_id', 'enrolled_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => AdmissionStatus::class,
            'birth_date' => 'date:Y-m-d',
            'submitted_on' => 'date:Y-m-d',
            'decided_at' => 'datetime',
            'enrolled_at' => 'datetime',
        ];
    }

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
