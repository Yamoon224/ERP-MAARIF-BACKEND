<?php

namespace App\Domains\Attendance\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class AttendanceException extends DomainException
{
    public static function studentNotInClass(): self
    {
        return new self(
            "Certains eleves ne sont pas inscrits dans la classe de l'appel.",
            'student_not_in_class',
        );
    }
}
