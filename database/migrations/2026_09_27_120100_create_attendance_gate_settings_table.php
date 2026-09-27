<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reglage du pointage geolocalise au portail (option 1 du pointage par QR
 * code) : une seule ligne pour l'etablissement (voir
 * AttendanceGateSettingRepositoryContract::current(), qui la cree avec des
 * valeurs par defaut si elle n'existe pas encore).
 *
 * `gate_token` est le jeton encode dans le QR affiche au portail : le
 * regenerer invalide l'affiche deja imprimee sans toucher aux comptes des
 * eleves ou du personnel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_gate_settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('radius_meters')->default(100);
            $table->uuid('gate_token');
            $table->boolean('is_enabled')->default(false);
            $table->timestamps();

            $table->unique('gate_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_gate_settings');
    }
};
