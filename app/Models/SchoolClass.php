<?php

namespace App\Models;

use Database\Factories\SchoolClassFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Classe de l'etablissement (ex. "6eme A") pour une annee scolaire donnee.
 *
 * Nommee `SchoolClass` et non `Classe` : `Classe` entrerait en conflit avec le
 * mot reserve `class` du PHP au moment de l'import (`use App\Models\Classe`
 * ne pose pas de probleme, mais nombre d'outils et de gabarits de code
 * confondent le nom du fichier avec le mot-cle).
 *
 * @property string $id
 * @property string $name
 * @property string $level
 * @property string $academic_year
 * @property string $monthly_fee scolarite mensuelle de la classe
 * @property string|null $main_teacher_id
 */
class SchoolClass extends Model
{
    /** @use HasFactory<SchoolClassFactory> */
    use HasFactory, HasUuids;

    protected $table = 'school_classes';

    /** @var list<string> */
    protected $fillable = ['name', 'level', 'academic_year', 'monthly_fee', 'main_teacher_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'monthly_fee' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function mainTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'main_teacher_id');
    }

    /** @return HasMany<Student, $this> */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /** @return HasMany<Enrollment, $this> */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /** Matieres enseignees dans cette classe, avec l'enseignant affecte.
     *
     * @return BelongsToMany<Subject, $this>
     */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'class_subject_teacher')
            ->withPivot('teacher_id')
            ->withTimestamps();
    }
}
