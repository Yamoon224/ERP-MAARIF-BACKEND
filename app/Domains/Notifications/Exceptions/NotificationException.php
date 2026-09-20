<?php

namespace App\Domains\Notifications\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class NotificationException extends DomainException
{
    public static function notResendable(): self
    {
        return new self(
            'Seul un message en échec peut être renvoyé.',
            'notification_not_failed',
            409,
        );
    }
}
