<?php

namespace App\Domains\Notifications\Contracts;

use App\Domains\Notifications\DTOs\NotificationMessage;

/**
 * Envoi d'un message sortant vers un tuteur (SMS ou e-mail).
 *
 * Le contrat rend un booleen plutot que de lever une exception : un SMS non
 * livre est un evenement ordinaire, pas une erreur qui doit faire echouer la
 * creation de la convocation ou de la sanction qui l'a declenche.
 */
interface NotificationSenderContract
{
    /** @return bool vrai si le message a ete accepte pour envoi */
    public function send(NotificationMessage $message): bool;
}
