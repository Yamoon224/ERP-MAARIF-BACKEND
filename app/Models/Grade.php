<?php

namespace App\Models;

use App\Domains\Grades\Enums\GradeType;
use Database\Factories\GradeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Note d'un eleve dans une matiere, pour un trimestre (cahier des charges
 * 3.2). `max_value` accompagne chaque note : un devoir note sur 10 n'est
 * ramene a une echelle commune qu'au moment du calcul de moyenne, jamais a la
 * saisie.
 *
 * @property string $id
 * @property string $student_id
 * @property string $subject_id
 * @property string $term_id
 * @property GradeType $type
 * @property float $value
 * @property float $max_value
 */
class Grade extends Model
{
    /** @use HasFactory<GradeFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'student_id', 'subject_id', 'term_id', 'teacher_id',
        'type', 'label', 'value', 'max_value', 'recorded_at', 'comment',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => GradeType::class,
            'value' => 'decimal:2',
            'max_value' => 'decimal:2',
            'recorded_at' => 'date:Y-m-d',
        ];
    }

    /** Note ramenee sur 20, quel que soit le bareme d'origine. */
    public function normalizedOn20(): float
    {
        if ((float) $this->max_value <= 0.0) {
            return 0.0;
        }

        return round(((float) $this->value / (float) $this->max_value) * 20, 2);
    }

    /** @return BelongsTo<Student, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return BelongsTo<Term, $this> */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /** @return BelongsTo<User, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
