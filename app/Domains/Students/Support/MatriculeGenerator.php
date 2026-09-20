<?php

namespace App\Domains\Students\Support;

use App\Models\Student;
use Illuminate\Support\Carbon;

/**
 * Matricule unique d'un eleve : identifiant public utilise pour la connexion
 * du parent au portail (cahier des charges 2 et 3.1).
 *
 * Format `MAA-{annee}-{sequence}`, ex. `MAA-2026-000123`. L'annee se lit sans
 * requete — utile sur un bulletin imprime ou une convocation — et la
 * sequence est incrementale par annee plutot que globale, pour qu'elle reste
 * courte meme apres des annees de fonctionnement.
 */
final class MatriculeGenerator
{
    private const PREFIX = 'MAA';

    private function __construct() {}

    public static function generate(?Carbon $date = null): string
    {
        $year = ($date ?? Carbon::now())->format('Y');
        $prefix = self::PREFIX."-{$year}-";

        // Le dernier numero est extrait et compare en PHP plutot qu'en SQL
        // (SUBSTRING_INDEX, CAST...) : cela evite de lier ce generateur au
        // moteur de base de donnees du serveur de production.
        $lastMatricule = Student::query()
            ->where('matricule', 'like', "{$prefix}%")
            ->orderByDesc('matricule')
            ->value('matricule');

        $next = $lastMatricule === null
            ? 1
            : ((int) substr((string) $lastMatricule, strlen($prefix))) + 1;

        return sprintf('%s%06d', $prefix, $next);
    }
}
