<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sanctions disciplinaires, y compris les renvois (cahier des charges 3.2 :
 * "historique des sanctions disciplinaires"). Comme une convocation, sa
 * creation notifie le tuteur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->enum('type', ['avertissement', 'exclusion_temporaire', 'renvoi_definitif']);
            $table->string('reason');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctions');
    }
};
