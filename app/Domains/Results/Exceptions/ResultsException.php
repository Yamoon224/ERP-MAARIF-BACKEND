<?php

namespace App\Domains\Results\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class ResultsException extends DomainException
{
    public static function incompleteCalendar(string $academicYear, string $period): self
    {
        return new self(
            "L'année {$academicYear} n'a pas assez de trimestres pour calculer ce résultat ({$period}).",
            'results_incomplete_calendar',
            422,
            ['academic_year' => $academicYear],
        );
    }

    public static function termOutsideYear(string $academicYear): self
    {
        return new self(
            "Ce trimestre n'appartient pas à l'année {$academicYear} de la classe.",
            'results_term_outside_year',
            422,
            ['academic_year' => $academicYear],
        );
    }

    public static function noAcademicYear(): self
    {
        return new self(
            "Aucune année scolaire n'est configurée : créez d'abord des trimestres.",
            'results_no_academic_year',
        );
    }
}
