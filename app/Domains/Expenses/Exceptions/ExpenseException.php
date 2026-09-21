<?php

namespace App\Domains\Expenses\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class ExpenseException extends DomainException
{
    public static function alreadyCancelled(): self
    {
        return new self('Cette dépense est déjà annulée.', 'expense_already_cancelled', 409);
    }

    public static function cancelledNotEditable(): self
    {
        return new self('Une dépense annulée ne peut plus être modifiée : saisissez-en une nouvelle.', 'expense_cancelled', 409);
    }

    public static function categoryInUse(): self
    {
        return new self(
            'Cette catégorie contient déjà des dépenses : elle ne peut pas être supprimée, désactivez-la plutôt.',
            'expense_category_in_use',
            409,
        );
    }
}
