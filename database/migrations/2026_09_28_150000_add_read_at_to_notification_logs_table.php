<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marque d'un message du journal comme lu par le personnel : un etat partage,
 * pas par utilisateur (voir NotificationLogController::markRead()) — suffisant
 * pour distinguer d'un coup d'oeil ce qui reste a traiter dans le journal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->timestamp('read_at')->nullable()->after('sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->dropColumn('read_at');
        });
    }
};
