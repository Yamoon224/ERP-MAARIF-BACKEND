<?php

namespace App\Domains\Notifications\Enums;

/**
 * Canal d'envoi vers le tuteur (cahier des charges 3.3 : "notifications SMS
 * ou emails").
 */
enum NotificationChannel: string
{
    case Email = 'email';
    case Sms = 'sms';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'E-mail',
            self::Sms => 'SMS',
        };
    }
}
