<?php

namespace App\Domains\Results\Support;

/** Appreciation qui accompagne une moyenne sur 20. */
final class Mention
{
    private function __construct() {}

    public static function forAverage(?float $average): ?string
    {
        return match (true) {
            $average === null => null,
            $average >= 16 => 'Très bien',
            $average >= 14 => 'Bien',
            $average >= 12 => 'Assez bien',
            $average >= 10 => 'Passable',
            default => 'Insuffisant',
        };
    }
}
