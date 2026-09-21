<?php

namespace App\Domains\Academics\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class TeachingAssignmentException extends DomainException
{
    public static function notTeacherOf(): self
    {
        return new self(
            "Vous n'enseignez pas cette matière à la classe de cet élève. Demandez à un administrateur de vous l'affecter.",
            'not_assigned_to_subject',
            403,
        );
    }
}
