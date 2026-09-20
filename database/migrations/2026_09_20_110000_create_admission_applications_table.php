<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Candidatures d'admission : un futur eleve est d'abord un dossier, avant
 * d'etre un eleve. Le dossier est instruit (etude, decision), puis, s'il est
 * admis, transforme en eleve inscrit dans une classe (`student_id` relie alors
 * le dossier a l'eleve cree).
 *
 * L'identite et le tuteur sont recopies sur le dossier plutot que rattaches a
 * un eleve : l'eleve n'existe pas tant que la candidature n'est pas acceptee,
 * et un dossier refuse ne doit laisser aucun compte de portail derriere lui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admission_applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('reference', 20)->unique();
            $table->string('academic_year', 9);
            $table->string('level', 50);

            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->enum('gender', ['M', 'F']);
            $table->date('birth_date')->nullable();
            $table->string('previous_school')->nullable();

            $table->string('guardian_name', 150);
            $table->string('guardian_phone', 30);
            $table->string('guardian_email')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();

            $table->string('status', 20)->default('pending');
            $table->date('submitted_on');
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignUuid('decided_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignUuid('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->timestamp('enrolled_at')->nullable();

            $table->timestamps();

            $table->index(['academic_year', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_applications');
    }
};
