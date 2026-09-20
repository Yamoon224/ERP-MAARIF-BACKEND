<?php

namespace App\Domains\Discipline\Enums;

enum SanctionType: string
{
    case Warning = 'avertissement';
    case TemporaryExclusion = 'exclusion_temporaire';
    case Expulsion = 'renvoi_definitif';

    public function label(): string
    {
        return match ($this) {
            self::Warning => 'Avertissement',
            self::TemporaryExclusion => 'Exclusion temporaire',
            self::Expulsion => 'Renvoi definitif',
        };
    }
}
