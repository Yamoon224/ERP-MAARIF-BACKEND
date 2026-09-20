<?php

namespace App\Models;

use Database\Factories\EnrollmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Inscription d'un eleve pour une annee scolaire entiere, dans une classe.
 * C'est l'unite a laquelle se rattache la scolarite : on paie pour une
 * inscription, pas pour un eleve dans l'absolu.
 *
 * @property string $id
 * @property string $student_id
 * @property string|null $school_class_id
 * @property string $academic_year
 * @property Carbon $enrolled_on
 */
class Enrollment extends Model
{
    /** @use HasFactory<EnrollmentFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = ['student_id', 'school_class_id', 'academic_year', 'enrolled_on'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'enrolled_on' => 'date:Y-m-d',
        ];
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<SchoolClass, $this> */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /** @return HasMany<TuitionInstallment, $this> */
    public function installments(): HasMany
    {
        return $this->hasMany(TuitionInstallment::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
