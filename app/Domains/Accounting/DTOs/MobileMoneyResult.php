<?php

namespace App\Domains\Accounting\DTOs;

use App\Domains\Accounting\Enums\MobileMoneyStatus;

/** Réponse d'une passerelle : où en est la demande, et l'identifiant côté opérateur. */
final class MobileMoneyResult
{
    /** @param  MobileMoneyStatus  $status  seulement Pending, Successful ou Failed : Expired est décidé par l'application */
    public function __construct(
        public readonly MobileMoneyStatus $status,
        public readonly ?string $providerReference = null,
        public readonly ?string $reason = null,
    ) {}
}
