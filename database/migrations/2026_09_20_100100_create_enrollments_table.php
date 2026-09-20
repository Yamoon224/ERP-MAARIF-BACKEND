<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Inscriptions : un eleve s'inscrit pour une annee scolaire entiere (donc
 * pour ses trois trimestres), dans une classe.
 *
 * `academic_year` est recopie sur l'inscription au lieu d'etre lu via la
 * classe : si la classe est supprimee (`nullOnDelete`), l'historique de
 * l'eleve et sa scolarite restent rattaches a la bonne annee.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('school_class_id')->nullable()->constrained('school_classes')->nullOnDelete();
            $table->string('academic_year', 9);
            $table->date('enrolled_on');
            $table->timestamps();

            $table->unique(['student_id', 'academic_year']);
            $table->index(['academic_year', 'school_class_id']);
        });

        $this->backfillFromCurrentClasses();
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }

    /** Les eleves deja affectes a une classe recoivent l'inscription correspondante. */
    private function backfillFromCurrentClasses(): void
    {
        $rows = DB::table('students')
            ->join('school_classes', 'school_classes.id', '=', 'students.school_class_id')
            ->select('students.id as student_id', 'school_classes.id as class_id', 'school_classes.academic_year', 'students.created_at')
            ->get();

        foreach ($rows as $row) {
            DB::table('enrollments')->insert([
                'id' => (string) Str::uuid(),
                'student_id' => $row->student_id,
                'school_class_id' => $row->class_id,
                'academic_year' => $row->academic_year,
                'enrolled_on' => substr((string) ($row->created_at ?? now()->toDateString()), 0, 10),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
