<?php

namespace App\Domains\Roles\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;

final class RoleException extends DomainException
{
    public static function protected(string $label): self
    {
        return new self(
            "Le rôle « {$label} » est un rôle système : il ne peut être ni renommé ni supprimé.",
            'role_protected',
            409,
        );
    }

    /** L'administrateur garde toutes les permissions : sans cela, une erreur de manipulation verrouillerait l'administration. */
    public static function adminLocked(): self
    {
        return new self(
            'Le rôle Administrateur possède toutes les permissions : elles ne peuvent pas être modifiées.',
            'role_admin_locked',
            409,
        );
    }

    public static function inUse(int $users): self
    {
        return new self(
            $users === 1
                ? 'Ce rôle est attribué à 1 utilisateur : retirez-le de son compte avant de le supprimer.'
                : "Ce rôle est attribué à {$users} utilisateurs : retirez-le de leurs comptes avant de le supprimer.",
            'role_in_use',
            409,
            ['users_count' => $users],
        );
    }
}
