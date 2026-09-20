<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Convocations des parents par l'etablissement (cahier des charges 3.2 et
 * 3.3) : creer une convocation declenche l'envoi d'une notification au
 * tuteur (voir App\Domains\Notifications\Services\GuardianNotifier).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('summons', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('reason');
            $table->dateTime('scheduled_at');
            $table->string('location')->nullable();
            $table->enum('status', ['pending', 'done', 'cancelled'])->default('pending');
            $table->foreignUuid('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('summons');
    }
};
