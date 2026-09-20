<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paiements de scolarite. Un paiement regle un ou plusieurs mois consecutifs
 * (mensuel, trimestre, semestre ou annee), voir `tuition_installments`.
 *
 * Un paiement n'est jamais supprime : l'annuler (`cancelled_at`) libere les
 * mois qu'il couvrait mais garde la trace du recu, pour qu'un numero de recu
 * remis a un parent reste toujours retrouvable. `restrictOnDelete` sur
 * l'inscription interdit d'effacer, en cascade, l'historique comptable d'un
 * eleve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('receipt_number', 30)->unique();
            $table->foreignUuid('enrollment_id')->constrained('enrollments')->restrictOnDelete();

            $table->enum('period_type', ['monthly', 'quarterly', 'semiannual', 'annual']);
            // Mois regles ('2025-10', ...) : conserves sur le paiement lui-meme pour
            // qu'un recu annule reste lisible une fois ses echeances liberees.
            $table->json('months');
            $table->decimal('amount', 12, 2);

            $table->enum('method', ['cash', 'mobile_money', 'bank_transfer', 'cheque']);
            $table->string('reference')->nullable();
            $table->date('paid_at');
            $table->string('note')->nullable();

            $table->foreignUuid('received_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('cancelled_at')->nullable();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();

            $table->timestamps();

            $table->index('paid_at');
            $table->index(['enrollment_id', 'cancelled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
