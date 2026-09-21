<?php

namespace App\Domains\Accounting\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class AccountingException extends DomainException
{
    public static function nothingToPay(): self
    {
        return new self(
            "Aucune echeance a payer pour cette inscription. Verifiez que les frais de scolarite de la classe sont renseignes, que les trimestres de l'annee sont definis, et que tous les mois ne sont pas deja regles.",
            'nothing_to_pay',
        );
    }

    public static function monthsAlreadySettled(): self
    {
        return new self('Certains des mois visés par ce paiement ont déjà été réglés entre-temps.', 'months_already_settled', 409);
    }

    public static function amountChanged(): self
    {
        return new self('Les frais de scolarité ont changé depuis la demande : le montant encaissé ne correspond plus aux mois visés.', 'tuition_amount_changed', 409);
    }

    public static function alreadyCancelled(): self
    {
        return new self('Ce paiement est deja annule.', 'payment_already_cancelled', 409);
    }
}
