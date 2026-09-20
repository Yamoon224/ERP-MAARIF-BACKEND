<?php

namespace App\Domains\Discipline\Enums;

enum SummonStatus: string
{
    case Pending = 'pending';
    case Done = 'done';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Done => 'Realisee',
            self::Cancelled => 'Annulee',
        };
    }
}
