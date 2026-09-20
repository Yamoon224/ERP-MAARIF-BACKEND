<?php

namespace Database\Seeders;

use App\Domains\Attendance\Enums\AttendanceStatus;
use App\Domains\Grades\Enums\GradeType;
use App\Models\AttendanceRecord;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/** Jeu de demonstration : de quoi explorer l'application sans saisie manuelle. */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'name' => 'Admin Maarif',
            'email' => 'admin@maarif.test',
            'phone' => '+224600000001',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $admin->assignRole('admin');

        $teacher = User::create([
            'name' => 'Mariam Diallo',
            'email' => 'enseignant@maarif.test',
            'phone' => '+224600000002',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $teacher->assignRole('teacher');

        $terms = Term::factory()->count(3)->create();
        $currentTerm = $terms->first();
        $currentTerm->update(['is_current' => true]);

        $subjects = collect([
            ['name' => 'Mathematiques', 'code' => 'MATH', 'coefficient' => 4],
            ['name' => 'Francais', 'code' => 'FRAN', 'coefficient' => 4],
            ['name' => 'Anglais', 'code' => 'ANGL', 'coefficient' => 2],
            ['name' => 'Sciences de la vie', 'code' => 'SVT', 'coefficient' => 2],
        ])->map(fn (array $data) => Subject::create($data));

        $schoolClass = SchoolClass::create([
            'name' => '6eme A',
            'level' => '6eme',
            'academic_year' => '2025-2026',
            'main_teacher_id' => $teacher->id,
        ]);

        $students = Student::factory()
            ->count(10)
            ->create(['school_class_id' => $schoolClass->id]);

        foreach ($students as $student) {
            foreach ($subjects as $subject) {
                Grade::create([
                    'student_id' => $student->id,
                    'subject_id' => $subject->id,
                    'term_id' => $currentTerm->id,
                    'teacher_id' => $teacher->id,
                    'type' => GradeType::Devoir,
                    'value' => fake()->randomFloat(2, 8, 20),
                    'max_value' => 20,
                    'recorded_at' => now()->subDays(fake()->numberBetween(1, 20)),
                ]);
            }

            AttendanceRecord::create([
                'student_id' => $student->id,
                'date' => now()->subDay()->toDateString(),
                'status' => fake()->randomElement(AttendanceStatus::cases()),
                'recorded_by' => $teacher->id,
            ]);
        }
    }
}
