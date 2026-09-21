<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Affectation d'un enseignant a une matiere pour une classe. La table pivot a
 * une cle primaire UUID : sans ce modele, `attach()` inserterait la ligne sans
 * identifiant et la base la refuserait.
 *
 * Une matiere n'a qu'un enseignant par classe (cle unique classe + matiere),
 * mais un enseignant peut avoir autant d'affectations qu'il enseigne de
 * matieres et de classes.
 *
 * @property string $id
 * @property string $school_class_id
 * @property string $subject_id
 * @property string $teacher_id
 */
class ClassSubjectTeacher extends Pivot
{
    use HasUuids;

    protected $table = 'class_subject_teacher';

    public $incrementing = false;

    protected $keyType = 'string';

    /** @var list<string> */
    protected $fillable = ['school_class_id', 'subject_id', 'teacher_id'];

    /** @return BelongsTo<SchoolClass, $this> */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /** @return BelongsTo<Subject, $this> */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    /** @return BelongsTo<User, $this> */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }
}
