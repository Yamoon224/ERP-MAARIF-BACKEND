<?php

namespace Database\Factories;

use App\Domains\Attendance\Enums\AttendanceStatus;
use App\Models\AttendanceRecord;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AttendanceRecord> */
class AttendanceRecordFactory extends Factory
{
    protected $model = AttendanceRecord::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'date' => fake()->unique()->dateTimeBetween('-2 months', 'now')->format('Y-m-d'),
            'status' => fake()->randomElement(AttendanceStatus::cases()),
            'justified' => false,
            'reason' => null,
            'recorded_by' => null,
        ];
    }
}
