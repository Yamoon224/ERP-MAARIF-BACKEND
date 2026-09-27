<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trace la provenance d'un pointage (saisie manuelle, QR du portail
 * geolocalise ou carte elave scannee) et, pour les deux pointages
 * automatiques, l'heure et le lieu declares par l'appareil qui a pointe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->enum('source', ['manual', 'self_service', 'card_scan'])->default('manual')->after('reason');
            $table->timestamp('checked_in_at')->nullable()->after('source');
            $table->decimal('latitude', 10, 7)->nullable()->after('checked_in_at');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedInteger('distance_meters')->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table): void {
            $table->dropColumn(['source', 'checked_in_at', 'latitude', 'longitude', 'distance_meters']);
        });
    }
};
