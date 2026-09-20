<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Enrollment> */
class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'school_class_id' => SchoolClass::factory(),
            'academic_year' => '2025-2026',
            'enrolled_on' => now()->toDateString(),
        ];
    }
}
