<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Presences et absences, une ligne par eleve et par jour (cahier des charges
 * 3.2 : "historique des presences et absences par date").
 *
 * L'unicite (eleve, date) empeche une double saisie le meme jour pour la
 * meme personne : une classe pointee deux fois par erreur ne doit pas
 * produire deux verdicts contradictoires dans l'historique d'un eleve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->date('date');
            $table->enum('status', ['present', 'absent', 'retard']);
            $table->boolean('justified')->default(false);
            $table->string('reason')->nullable();
            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
