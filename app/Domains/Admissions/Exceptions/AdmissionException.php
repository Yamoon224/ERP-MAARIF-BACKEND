<?php

namespace App\Domains\Admissions\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class AdmissionException extends DomainException
{
    public static function alreadyEnrolled(): self
    {
        return new self(
            'Ce candidat est déjà inscrit : son dossier ne peut plus être modifié ni supprimé.',
            'admission_already_enrolled',
            409,
        );
    }

    public static function notAccepted(): self
    {
        return new self(
            'Seule une candidature admise peut être transformée en inscription.',
            'admission_not_accepted',
        );
    }

    public static function classYearMismatch(string $applicationYear, string $classYear): self
    {
        return new self(
            "La candidature vise l'année {$applicationYear} : la classe choisie appartient à l'année {$classYear}.",
            'admission_class_year_mismatch',
            422,
            ['application_year' => $applicationYear, 'class_year' => $classYear],
        );
    }
}
