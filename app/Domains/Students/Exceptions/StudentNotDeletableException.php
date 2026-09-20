<?php

namespace App\Domains\Students\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class StudentNotDeletableException extends DomainException
{
    public static function hasPayments(): self
    {
        return new self(
            'Cet eleve a des paiements de scolarite enregistres : il ne peut pas etre supprime. Desactivez-le a la place.',
            'student_has_payments',
            409,
        );
    }
}
