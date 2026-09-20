<?php

namespace App\Domains\Accounting\Support;

use App\Domains\Accounting\Contracts\PaymentRepositoryContract;
use Illuminate\Support\Carbon;

/**
 * Numero de recu : `REC-{annee}-{sequence}`, ex. `REC-2026-000042`. La
 * sequence repart de 1 chaque annee civile et n'est jamais reutilisee, meme
 * apres l'annulation d'un paiement (l'annule garde son numero).
 */
final class ReceiptNumberGenerator
{
    private const PREFIX = 'REC';

    public function __construct(private readonly PaymentRepositoryContract $payments) {}

    public function next(?Carbon $date = null): string
    {
        $prefix = self::PREFIX.'-'.($date ?? Carbon::now())->format('Y').'-';

        $last = $this->payments->lastReceiptNumber($prefix);
        $next = $last === null ? 1 : ((int) substr($last, strlen($prefix))) + 1;

        return sprintf('%s%06d', $prefix, $next);
    }
}
