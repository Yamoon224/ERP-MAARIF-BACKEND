<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bareme de passage et d'appreciation d'une classe : chaque tranche de
 * moyenne annuelle donne un libelle (ex. "Redouble", "Bien") et,
 * optionnellement, une decision de passage suggeree. Une classe sans
 * tranche continue d'utiliser le bareme global existant (voir
 * GradeScaleService, qui retombe sur Mention::forAverage() et
 * config('school.pass_mark') quand aucune tranche ne couvre la moyenne).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_scale_bands', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('school_class_id')->constrained('school_classes')->cascadeOnDelete();
            $table->decimal('min_average', 4, 2);
            $table->decimal('max_average', 4, 2);
            $table->string('label', 50);
            $table->string('decision', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_scale_bands');
    }
};
