<?php

namespace App\Domains\Accounting\Enums;

enum MobileMoneyStatus: string
{
    /** Demande envoyée, le parent doit la valider sur son téléphone. */
    case Pending = 'pending';
    /** Confirmée par l'opérateur : le paiement et son reçu existent. */
    case Successful = 'successful';
    /** Refusée ou annulée (solde insuffisant, code erroné...). */
    case Failed = 'failed';
    /** Jamais validée dans le délai. */
    case Expired = 'expired';
    /** Débité par l'opérateur mais mois déjà réglés entre-temps : à traiter par la comptabilité. */
    case NeedsReview = 'needs_review';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Successful => 'Réussi',
            self::Failed => 'Échoué',
            self::Expired => 'Expiré',
            self::NeedsReview => 'À vérifier',
        };
    }

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
