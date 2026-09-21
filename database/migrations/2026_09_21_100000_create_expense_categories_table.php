<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categories de depenses de l'etablissement (fournitures, registres,
 * entretien...). Une table plutot qu'une liste figee : chaque ecole a ses
 * propres postes de depense. Une categorie utilisee n'est jamais supprimee,
 * seulement desactivee, pour que l'historique reste lisible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name', 100)->unique();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
