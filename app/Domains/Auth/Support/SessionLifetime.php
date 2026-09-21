<?php

namespace App\Domains\Auth\Support;

use Illuminate\Support\Carbon;

/** Echeance du jeton d'une session, selon que l'utilisateur a coche "Se souvenir de moi". */
final class SessionLifetime
{
    public static function expiresAt(bool $remember): Carbon
    {
        $minutes = config($remember ? 'auth.token_lifetime.remembered' : 'auth.token_lifetime.standard');

        return Carbon::now()->addMinutes($minutes);
    }
}
