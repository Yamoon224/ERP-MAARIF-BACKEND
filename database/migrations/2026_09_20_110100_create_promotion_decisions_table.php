<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Decision de passage d'un eleve a la fin d'une annee scolaire (admis en
 * classe superieure, redoublant, exclu).
 *
 * Rattachee a l'inscription et non a l'eleve : la decision porte sur une
 * annee precise, et une inscription est justement l'unite eleve x annee.
 * `average` fige la moyenne annuelle au moment de la decision, pour que le
 * dossier reste lisible meme si une note est corrigee ensuite.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_decisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('enrollment_id')->unique()->constrained('enrollments')->cascadeOnDelete();
            $table->string('decision', 20);
            $table->decimal('average', 5, 2)->nullable();
            $table->text('note')->nullable();
            $table->foreignUuid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_decisions');
    }
};
