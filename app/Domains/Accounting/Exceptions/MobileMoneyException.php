<?php

namespace App\Domains\Accounting\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class MobileMoneyException extends DomainException
{
    public static function alreadyPending(): self
    {
        return new self(
            'Une demande de paiement est déjà en attente pour cette inscription. Validez-la sur votre téléphone ou attendez son expiration avant d\'en lancer une autre.',
            'mobile_money_already_pending',
            409,
        );
    }
}
