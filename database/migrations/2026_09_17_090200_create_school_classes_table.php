<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Classes de l'etablissement (ex. "6eme A"), rattachees a une annee scolaire.
 *
 * Une classe est reconduite d'une annee sur l'autre sous un nouvel
 * enregistrement plutot que d'ecraser `academic_year` sur la meme ligne : les
 * notes et absences d'une annee passee doivent rester lisibles telles qu'elles
 * etaient, sans se retrouver rattachees a la composition de classe suivante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('school_classes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('level');
            $table->string('academic_year', 9);
            $table->foreignUuid('main_teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['name', 'academic_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('school_classes');
    }
};
