<?php

namespace App\Domains\Admissions\Support;

use App\Models\AdmissionApplication;
use Illuminate\Support\Carbon;

/**
 * Reference d'un dossier de candidature, du meme esprit que le matricule :
 * `ADM-{annee}-{sequence}`, ex. `ADM-2026-000042`. Le dossier la porte sur
 * les courriers et permet de le retrouver sans connaitre son identifiant.
 */
final class AdmissionReferenceGenerator
{
    private const PREFIX = 'ADM';

    private function __construct() {}

    public static function generate(?Carbon $date = null): string
    {
        $year = ($date ?? Carbon::now())->format('Y');
        $prefix = self::PREFIX."-{$year}-";

        $last = AdmissionApplication::query()
            ->where('reference', 'like', "{$prefix}%")
            ->orderByDesc('reference')
            ->value('reference');

        $next = $last === null ? 1 : ((int) substr((string) $last, strlen($prefix))) + 1;

        return sprintf('%s%06d', $prefix, $next);
    }
}
