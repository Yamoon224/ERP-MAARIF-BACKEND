<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Depenses et approvisionnements de l'etablissement : un achat = une ligne
 * (designation, quantite, prix unitaire, fournisseur).
 *
 * Comme un paiement de scolarite, une depense n'est jamais supprimee : l'annuler
 * (`cancelled_at`) la sort des totaux mais garde la trace du numero et du
 * motif. `restrictOnDelete` sur la categorie interdit d'effacer l'historique
 * d'un poste de depense.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('number', 30)->unique();
            $table->foreignUuid('expense_category_id')->constrained('expense_categories')->restrictOnDelete();

            $table->string('label', 150);
            $table->string('supplier_name', 150)->nullable();
            $table->decimal('quantity', 10, 2)->default(1);
            $table->string('unit', 30)->nullable();
            $table->decimal('unit_price', 12, 2);
            // Calcule cote serveur (quantite x prix unitaire), jamais fourni par le client.
            $table->decimal('amount', 12, 2);

            $table->enum('method', ['cash', 'mobile_money', 'bank_transfer', 'cheque']);
            $table->string('invoice_reference', 100)->nullable();
            $table->date('spent_at');
            $table->string('note')->nullable();

            $table->foreignUuid('recorded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('cancelled_at')->nullable();
            $table->foreignUuid('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancellation_reason')->nullable();

            $table->timestamps();

            $table->index('spent_at');
            $table->index(['expense_category_id', 'cancelled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
