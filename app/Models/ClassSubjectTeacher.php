<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Affectation d'un enseignant a une matiere pour une classe. La table pivot a
 * une cle primaire UUID : sans ce modele, `attach()` inserterait la ligne sans
 * identifiant et la base la refuserait.
 */
class ClassSubjectTeacher extends Pivot
{
    use HasUuids;

    protected $table = 'class_subject_teacher';

    public $incrementing = false;

    protected $keyType = 'string';
}
