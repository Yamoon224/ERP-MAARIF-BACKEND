<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Notifications de decision d'admission.
 *
 * Un candidat n'est pas encore un eleve : tant que sa candidature n'est pas
 * inscrite, il n'y a pas de `student_id` auquel rattacher le message. Le
 * journal rattache donc une notification soit a un eleve, soit a un dossier de
 * candidature. Les coordonnees du tuteur utilisees sont celles du dossier.
 *
 * `notified_at` sur le dossier dit si le tuteur a bien recu la derniere
 * decision, sans avoir a fouiller le journal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->uuid('student_id')->nullable()->change();
            $table->foreignUuid('admission_application_id')
                ->nullable()
                ->after('student_id')
                ->constrained('admission_applications')
                ->cascadeOnDelete();
        });

        Schema::table('admission_applications', function (Blueprint $table): void {
            $table->timestamp('notified_at')->nullable()->after('decided_by');
        });
    }

    public function down(): void
    {
        Schema::table('admission_applications', function (Blueprint $table): void {
            $table->dropColumn('notified_at');
        });

        // Les messages rattaches a un dossier n'ont pas d'eleve : ils ne
        // peuvent pas survivre au retour de la colonne obligatoire.
        DB::table('notification_logs')->whereNull('student_id')->delete();

        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('admission_application_id');
            $table->uuid('student_id')->nullable(false)->change();
        });
    }
};
