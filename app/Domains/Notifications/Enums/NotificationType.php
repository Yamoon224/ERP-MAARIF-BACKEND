<?php

namespace App\Domains\Notifications\Enums;

/**
 * Nature de l'evenement notifie au tuteur (cahier des charges 3.3).
 */
enum NotificationType: string
{
    case Summon = 'convocation';
    case Sanction = 'sanction';
    case BulletinReminder = 'bulletin';

    public function label(): string
    {
        return match ($this) {
            self::Summon => 'Convocation',
            self::Sanction => 'Sanction',
            self::BulletinReminder => 'Rappel de bulletin',
        };
    }
}
