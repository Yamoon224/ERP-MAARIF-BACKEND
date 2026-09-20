<?php

namespace App\Domains\Grades\Enums;

enum GradeType: string
{
    case Devoir = 'devoir';
    case Composition = 'composition';

    public function label(): string
    {
        return match ($this) {
            self::Devoir => 'Devoir',
            self::Composition => 'Composition',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
