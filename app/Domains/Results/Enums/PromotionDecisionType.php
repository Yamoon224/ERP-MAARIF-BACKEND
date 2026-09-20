<?php

namespace App\Domains\Results\Enums;

enum PromotionDecisionType: string
{
    case Admitted = 'admitted';
    case Repeat = 'repeat';
    case Excluded = 'excluded';

    public function label(): string
    {
        return match ($this) {
            self::Admitted => 'Admis en classe supérieure',
            self::Repeat => 'Redoublant',
            self::Excluded => 'Exclu',
        };
    }
}
