<?php

namespace App\Domains\Attendance\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class AttendanceException extends DomainException
{
    public static function studentNotInClass(): self
    {
        return new self(
            "Certains eleves ne sont pas inscrits dans la classe de l'appel.",
            'student_not_in_class',
        );
    }

    public static function unknownCard(): self
    {
        return new self(
            'Cette carte ne correspond a aucun eleve actif.',
            'unknown_card',
            404,
        );
    }

    public static function gateDisabled(): self
    {
        return new self(
            "Le pointage par QR code n'est pas active pour le moment.",
            'gate_disabled',
        );
    }

    public static function invalidGateToken(): self
    {
        return new self(
            "Ce QR code n'est plus valide, demandez au personnel l'affiche a jour.",
            'invalid_gate_token',
        );
    }

    public static function outsideGateRadius(int $distanceMeters): self
    {
        return new self(
            "Vous etes a {$distanceMeters} m du portail : rapprochez-vous pour pointer votre arrivee.",
            'outside_gate_radius',
            422,
            ['distance_meters' => $distanceMeters],
        );
    }
}
