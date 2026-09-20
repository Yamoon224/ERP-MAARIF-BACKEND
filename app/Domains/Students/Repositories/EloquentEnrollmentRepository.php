<?php

namespace App\Domains\Students\Repositories;

use App\Domains\Students\Contracts\EnrollmentRepositoryContract;
use App\Models\Enrollment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class EloquentEnrollmentRepository implements EnrollmentRepositoryContract
{
    public function forStudent(string $studentId): Collection
    {
        return Enrollment::query()
            ->with('schoolClass:id,name,level,monthly_fee')
            ->where('student_id', $studentId)
            ->orderByDesc('academic_year')
            ->get();
    }

    public function findOrFail(string $id): Enrollment
    {
        return Enrollment::query()
            ->with(['student:id,first_name,last_name,matricule', 'schoolClass:id,name,level,monthly_fee'])
            ->findOrFail($id);
    }

    public function upsertForYear(string $studentId, string $academicYear, ?string $schoolClassId, string $enrolledOn): Enrollment
    {
        $enrollment = Enrollment::query()->firstOrNew(['student_id' => $studentId, 'academic_year' => $academicYear]);

        $enrollment->school_class_id = $schoolClassId;
        $enrollment->enrolled_on ??= $enrolledOn;
        $enrollment->save();

        return $enrollment;
    }

    public function paginateForYear(string $academicYear, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Enrollment::query()
            ->with(['student:id,first_name,last_name,matricule,gender,is_active', 'schoolClass:id,name,level,monthly_fee'])
            ->where('enrollments.academic_year', $academicYear)
            ->when($filters['school_class_id'] ?? null, fn ($query, $id) => $query->where('enrollments.school_class_id', $id))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->whereHas(
                'student',
                fn ($student) => $student
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('matricule', 'like', "%{$search}%"),
            ))
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->orderBy('students.last_name')
            ->orderBy('students.first_name')
            ->select('enrollments.*')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function countForYear(string $academicYear, ?string $schoolClassId = null): int
    {
        return Enrollment::query()
            ->where('academic_year', $academicYear)
            ->when($schoolClassId, fn ($query, $id) => $query->where('school_class_id', $id))
            ->count();
    }
}
