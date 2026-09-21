<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paiements de scolarité initiés par les parents via mobile money.
 *
 * Une transaction naît « en attente » : le parent valide la demande sur son
 * téléphone (code PIN), puis l'opérateur confirme. Ce n'est qu'à la
 * confirmation qu'un `Payment` (avec son reçu) est créé et que les mois sont
 * réglés : une demande abandonnée ou refusée ne touche donc jamais la
 * comptabilité. Les mois et le montant sont figés à l'initiation, calculés par
 * le serveur, jamais fournis par le client.
 *
 * `needs_review` : l'opérateur a débité le parent alors que les mois avaient
 * été réglés entre-temps (par exemple en espèces). Aucun paiement n'est créé ;
 * la comptabilité doit rembourser ou réaffecter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_money_transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('reference', 30)->unique();
            $table->foreignUuid('enrollment_id')->constrained('enrollments')->restrictOnDelete();

            $table->enum('operator', ['orange_money', 'mtn_momo', 'moov_money']);
            $table->string('phone', 20);

            $table->enum('period_type', ['monthly', 'quarterly', 'semiannual', 'annual']);
            $table->json('months');
            $table->decimal('amount', 12, 2);

            $table->enum('status', ['pending', 'successful', 'failed', 'expired', 'needs_review'])->default('pending');
            $table->string('provider_reference')->nullable();
            $table->string('failure_reason')->nullable();

            $table->foreignUuid('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['enrollment_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_money_transactions');
    }
};
