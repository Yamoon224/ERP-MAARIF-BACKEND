<?php

namespace App\Domains\Attendance\Enums;

enum AttendanceSource: string
{
    case Manual = 'manual';
    case SelfService = 'self_service';
    case CardScan = 'card_scan';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Saisie manuelle',
            self::SelfService => 'QR code du portail',
            self::CardScan => 'Carte élève scannée',
        };
    }
}
