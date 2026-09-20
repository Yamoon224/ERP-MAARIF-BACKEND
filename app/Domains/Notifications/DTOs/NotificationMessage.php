<?php

namespace App\Domains\Notifications\DTOs;

use App\Domains\Notifications\Enums\NotificationChannel;

/**
 * Message sortant, deja redige : le pilote d'envoi ne connait ni modele ni
 * variables, seulement du texte pret a partir. C'est ce qui permet de changer
 * de prestataire SMS ou d'e-mail sans toucher au contenu des messages.
 */
final readonly class NotificationMessage
{
    public function __construct(
        public NotificationChannel $channel,
        public string $recipient,
        public string $body,
        public ?string $subject = null,
    ) {}
}
