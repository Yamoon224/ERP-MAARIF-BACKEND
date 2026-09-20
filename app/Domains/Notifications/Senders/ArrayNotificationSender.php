<?php

namespace App\Domains\Notifications\Senders;

use App\Domains\Notifications\Contracts\NotificationSenderContract;
use App\Domains\Notifications\DTOs\NotificationMessage;

/**
 * Pilote de test : conserve les messages en memoire au lieu de les envoyer.
 *
 * Enregistre en singleton par App\Providers\DomainServiceProvider : une
 * seconde instance ferait inspecter a un test un envoi qui n'a jamais eu
 * lieu dans celle qu'il interroge.
 */
final class ArrayNotificationSender implements NotificationSenderContract
{
    /** @var list<NotificationMessage> */
    private array $sent = [];

    public function send(NotificationMessage $message): bool
    {
        $this->sent[] = $message;

        return true;
    }

    /** @return list<NotificationMessage> */
    public function sent(): array
    {
        return $this->sent;
    }
}
