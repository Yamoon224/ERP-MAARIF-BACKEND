<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Journal des notifications envoyees aux tuteurs (SMS/e-mail, cahier des
 * charges 3.3). Trace chaque tentative, y compris les echecs : "je n'ai pas
 * recu la convocation" est la premiere reclamation attendue d'un parent, et
 * sans journal la reponse serait une conjecture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->enum('channel', ['email', 'sms']);
            $table->string('type');
            $table->string('recipient');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->string('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
