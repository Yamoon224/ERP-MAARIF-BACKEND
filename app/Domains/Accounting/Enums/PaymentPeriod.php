<?php

namespace App\Domains\Accounting\Enums;

/**
 * Formule de paiement choisie par la famille. La scolarite reste mensuelle :
 * la formule dit seulement combien de mois consecutifs sont regles d'un coup.
 */
enum PaymentPeriod: string
{
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Semiannual = 'semiannual';
    case Annual = 'annual';

    /** Nombre de mois couverts, ou null pour "tous les mois restants de l'annee". */
    public function months(): ?int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
            self::Semiannual => 6,
            self::Annual => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Mensuel',
            self::Quarterly => 'Trimestriel',
            self::Semiannual => 'Semestriel',
            self::Annual => 'Annuel',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
