<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jeton opaque imprime sur la carte scolaire de l'eleve (QR code) : le
 * surveillant qui la scanne au portail retrouve l'eleve par ce jeton, jamais
 * par le matricule directement affiche. Genere a la demande, au premier
 * export de carte (voir StudentCardService::ensureToken()) et regenerable
 * independamment des autres eleves si une carte est perdue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->uuid('qr_token')->nullable()->unique()->after('matricule');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->dropColumn('qr_token');
        });
    }
};
