<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notes des eleves, par matiere et par trimestre (cahier des charges 3.2).
 *
 * `max_value` accompagne chaque note plutot que d'imposer un bareme fixe de
 * 20 : un devoir note sur 10 ne doit pas etre ramene a une echelle commune au
 * moment de la saisie, seulement au moment du calcul de moyenne
 * (voir App\Domains\Grades\Services\BulletinService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignUuid('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignUuid('term_id')->constrained('terms')->cascadeOnDelete();
            $table->foreignUuid('teacher_id')->nullable()->constrained('users')->nullOnDelete();

            $table->enum('type', ['devoir', 'composition']);
            $table->string('label')->nullable();
            $table->decimal('value', 5, 2);
            $table->decimal('max_value', 5, 2)->default(20);
            $table->date('recorded_at');
            $table->string('comment')->nullable();

            $table->timestamps();

            $table->index(['student_id', 'term_id']);
            $table->index(['subject_id', 'term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades');
    }
};
