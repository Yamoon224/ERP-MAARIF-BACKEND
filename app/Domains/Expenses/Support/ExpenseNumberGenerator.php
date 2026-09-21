<?php

namespace App\Domains\Expenses\Support;

use App\Domains\Expenses\Contracts\ExpenseRepositoryContract;
use Illuminate\Support\Carbon;

/**
 * Numero de depense : `DEP-{annee}-{sequence}`, ex. `DEP-2026-000042`. La
 * sequence repart de 1 chaque annee civile et n'est jamais reutilisee, meme
 * apres l'annulation d'une depense (l'annulee garde son numero).
 */
final class ExpenseNumberGenerator
{
    private const PREFIX = 'DEP';

    public function __construct(private readonly ExpenseRepositoryContract $expenses) {}

    public function next(?Carbon $date = null): string
    {
        $prefix = self::PREFIX.'-'.($date ?? Carbon::now())->format('Y').'-';

        $last = $this->expenses->lastNumber($prefix);
        $next = $last === null ? 1 : ((int) substr($last, strlen($prefix))) + 1;

        return sprintf('%s%06d', $prefix, $next);
    }
}
