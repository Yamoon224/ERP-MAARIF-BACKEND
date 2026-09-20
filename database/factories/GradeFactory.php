<?php

namespace Database\Factories;

use App\Domains\Grades\Enums\GradeType;
use App\Models\Grade;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Grade> */
class GradeFactory extends Factory
{
    protected $model = Grade::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'subject_id' => Subject::factory(),
            'term_id' => Term::factory(),
            'teacher_id' => null,
            'type' => fake()->randomElement(GradeType::cases()),
            'label' => null,
            'value' => fake()->randomFloat(2, 4, 20),
            'max_value' => 20,
            'recorded_at' => fake()->dateTimeBetween('-2 months', 'now'),
            'comment' => null,
        ];
    }
}
