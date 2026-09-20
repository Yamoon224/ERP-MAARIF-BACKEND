<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Eleves de l'etablissement.
 *
 * Le matricule est la cle de voute du cahier des charges : c'est par lui,
 * associe a un mot de passe, que le parent se connecte au portail. Il est
 * donc unique, immuable une fois attribue, et ne se devine pas (voir
 * App\Domains\Students\Support\MatriculeGenerator).
 *
 * Les coordonnees du tuteur (nom, telephone, e-mail) vivent directement sur
 * l'eleve plutot que dans une table de tuteurs distincte : le cahier des
 * charges ne demande qu'un seul contact par eleve, et une table separee pour
 * un unique enregistrement lie n'ajouterait qu'une jointure sans jamais servir
 * a autre chose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('matricule', 30)->unique();
            $table->string('password');

            $table->string('first_name');
            $table->string('last_name');
            $table->enum('gender', ['M', 'F']);
            $table->date('birth_date')->nullable();

            $table->foreignUuid('school_class_id')->nullable()->constrained('school_classes')->nullOnDelete();

            $table->string('guardian_name');
            $table->string('guardian_phone', 30);
            $table->string('guardian_email')->nullable();
            $table->string('address')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();

            $table->timestamps();

            $table->index(['school_class_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
