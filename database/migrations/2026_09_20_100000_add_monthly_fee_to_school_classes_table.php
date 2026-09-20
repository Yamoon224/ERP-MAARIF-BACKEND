<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scolarite mensuelle d'une classe.
 *
 * Le montant vit sur la classe plutot que dans une grille "niveau x annee" a
 * part : une classe est deja propre a une annee scolaire (voir
 * `school_classes.academic_year`), donc un tarif par classe est aussi un tarif
 * par annee, sans table de correspondance a maintenir en parallele.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_classes', function (Blueprint $table): void {
            $table->decimal('monthly_fee', 12, 2)->default(0)->after('academic_year');
        });
    }

    public function down(): void
    {
        Schema::table('school_classes', function (Blueprint $table): void {
            $table->dropColumn('monthly_fee');
        });
    }
};
