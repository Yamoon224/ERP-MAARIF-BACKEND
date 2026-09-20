<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trimestres scolaires. `is_current` marque le trimestre actif : c'est lui que
 * les ecrans de saisie de notes proposent par defaut, sans que l'enseignant
 * ait a le chercher dans un calendrier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terms', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('academic_year', 9);
            $table->date('starts_at');
            $table->date('ends_at');
            $table->boolean('is_current')->default(false);
            $table->timestamps();

            $table->unique(['name', 'academic_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms');
    }
};
