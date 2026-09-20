<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Echeances de scolarite : une ligne par inscription et par mois scolaire.
 *
 * La scolarite est mensuelle quelle que soit la formule choisie par la
 * famille. Payer un trimestre, un semestre ou l'annee revient a regler
 * plusieurs echeances d'un coup (`payment_id`), ce qui garde un seul modele de
 * dette et evite de raisonner sur des periodes qui se chevauchent.
 * `month` est toujours le premier jour du mois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tuition_installments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('enrollment_id')->constrained('enrollments')->cascadeOnDelete();
            $table->date('month');
            $table->decimal('amount', 12, 2);
            $table->foreignUuid('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->timestamps();

            $table->unique(['enrollment_id', 'month']);
            $table->index(['payment_id']);
            $table->index(['month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tuition_installments');
    }
};
