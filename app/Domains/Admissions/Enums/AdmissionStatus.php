<?php

namespace App\Domains\Admissions\Enums;

/**
 * Etat d'un dossier de candidature.
 *
 * Deposee (`pending`), la candidature est etudiee (`under_review`), puis
 * acceptee, mise en liste d'attente ou refusee. `enrolled` n'est atteint que
 * par l'inscription effective de l'eleve : on ne peut pas y arriver en
 * changeant simplement le statut.
 */
enum AdmissionStatus: string
{
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Accepted = 'accepted';
    case Waitlisted = 'waitlisted';
    case Rejected = 'rejected';
    case Enrolled = 'enrolled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::UnderReview => 'En étude',
            self::Accepted => 'Admis',
            self::Waitlisted => "Liste d'attente",
            self::Rejected => 'Refusé',
            self::Enrolled => 'Inscrit',
        };
    }

    /**
     * Statuts que le personnel peut choisir directement (tous sauf l'etat
     * initial et l'inscription).
     *
     * @return list<self>
     */
    public static function decidable(): array
    {
        return [self::UnderReview, self::Accepted, self::Waitlisted, self::Rejected];
    }
}
