<?php

namespace App\Domains\Students\Repositories;

use App\Domains\Shared\Support\Sort;
use App\Domains\Students\Contracts\StudentRepositoryContract;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class EloquentStudentRepository implements StudentRepositoryContract
{
    /** @var array<string, string> */
    private const SORTABLE = [
        'first_name' => 'first_name',
        'last_name' => 'last_name',
        'matricule' => 'matricule',
        'created_at' => 'created_at',
    ];

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return Student::query()
            ->with('schoolClass:id,name,level')
            ->when($filters['school_class_id'] ?? null, fn ($query, $id) => $query->where('school_class_id', $id))
            ->when($filters['academic_year'] ?? null, fn ($query, $year) => $query->enrolledInYear($year))
            ->when(
                array_key_exists('is_active', $filters) && $filters['is_active'] !== null,
                fn ($query) => $query->where('is_active', $filters['is_active']),
            )
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($sub) => $sub
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('matricule', 'like', "%{$search}%"),
            ))
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'last_name', 'asc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(string $id): Student
    {
        return Student::query()->with('schoolClass')->findOrFail($id);
    }

    public function create(array $attributes): Student
    {
        return Student::create($attributes);
    }

    public function update(Student $student, array $attributes): Student
    {
        $student->update($attributes);

        return $student->refresh();
    }

    public function delete(Student $student): void
    {
        $student->delete();
    }

    public function hasPayments(Student $student): bool
    {
        return Payment::query()
            ->whereHas('enrollment', fn ($enrollment) => $enrollment->where('student_id', $student->id))
            ->exists();
    }
}
