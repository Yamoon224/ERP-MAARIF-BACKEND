<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nombre de tentatives d'envoi d'un message. Un message en echec peut etre
 * renvoye depuis le journal : on garde la meme ligne et on compte les
 * tentatives, plutot que d'empiler une ligne par essai.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->unsignedSmallInteger('attempts')->default(1)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->dropColumn('attempts');
        });
    }
};
