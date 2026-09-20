<?php

namespace App\Domains\Shared\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Tri d'une liste demande par le client.
 *
 * Le nom de colonne ne vient jamais directement de la requete : le client
 * envoie une cle publique (`name`, `date`…) que chaque depot traduit, via une
 * allowlist, en colonne reelle. C'est la seule facon d'exposer un tri par
 * en-tete sans ouvrir une injection SQL.
 */
final class Sort
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $filters  contient eventuellement `sort` et `direction`
     * @param  array<string, string>  $allowed  cle publique vers colonne
     * @return Builder<TModel>
     */
    public static function apply(
        Builder $query,
        array $filters,
        array $allowed,
        string $fallbackColumn = 'created_at',
        string $fallbackDirection = 'desc',
    ): Builder {
        $direction = strtolower((string) ($filters['direction'] ?? '')) === 'desc' ? 'desc' : 'asc';
        $key = $filters['sort'] ?? null;

        // Une cle inconnue est ignoree plutot que refusee : le tri est un
        // confort d'affichage, pas une instruction dont l'echec doit couter
        // une page d'erreur a l'utilisateur.
        if (! is_string($key) || ! array_key_exists($key, $allowed)) {
            return $query->orderBy($fallbackColumn, $fallbackDirection);
        }

        return $query->orderBy($allowed[$key], $direction)->orderBy('id', 'desc');
    }
}
