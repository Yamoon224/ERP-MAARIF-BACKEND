<?php

namespace App\Domains\Users\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class UserNotDeletableException extends DomainException
{
    public static function self(): self
    {
        return new self(
            'Vous ne pouvez pas supprimer votre propre compte.',
            'user_self_delete',
            409,
        );
    }
}
