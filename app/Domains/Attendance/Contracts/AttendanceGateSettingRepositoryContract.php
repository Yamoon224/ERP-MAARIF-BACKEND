<?php

namespace App\Domains\Attendance\Contracts;

use App\Models\AttendanceGateSetting;

interface AttendanceGateSettingRepositoryContract
{
    /** Reglage courant, cree avec des valeurs par defaut s'il n'existe pas encore (voir la contrainte de ligne unique). */
    public function current(): AttendanceGateSetting;

    /** @param  array<string, mixed>  $attributes */
    public function update(AttendanceGateSetting $setting, array $attributes): AttendanceGateSetting;

    /** Remplace le jeton du QR affiche au portail : invalide l'affiche deja imprimee. */
    public function regenerateToken(AttendanceGateSetting $setting): AttendanceGateSetting;
}
