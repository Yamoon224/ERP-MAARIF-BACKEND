<?php

namespace App\Domains\Admissions\Repositories;

use App\Domains\Admissions\Contracts\AdmissionRepositoryContract;
use App\Domains\Shared\Support\Sort;
use App\Models\AdmissionApplication;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class EloquentAdmissionRepository implements AdmissionRepositoryContract
{
    /** @var array<string, string> */
    private const SORTABLE = [
        'submitted_on' => 'submitted_on',
        'name' => 'last_name',
        'status' => 'status',
    ];

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return AdmissionApplication::query()
            ->with('student:id,matricule')
            ->when($filters['academic_year'] ?? null, fn ($query, $year) => $query->where('academic_year', $year))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['level'] ?? null, fn ($query, $level) => $query->where('level', $level))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($match) => $match
                    ->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('guardian_name', 'like', "%{$search}%"),
            ))
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'submitted_on', 'desc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(string $id): AdmissionApplication
    {
        return AdmissionApplication::query()->with(['student:id,matricule', 'decider:id,name'])->findOrFail($id);
    }

    public function create(array $attributes): AdmissionApplication
    {
        return AdmissionApplication::create($attributes);
    }

    public function update(AdmissionApplication $application, array $attributes): AdmissionApplication
    {
        $application->update($attributes);

        return $this->findOrFail($application->id);
    }

    public function delete(AdmissionApplication $application): void
    {
        $application->delete();
    }

    public function countByStatus(?string $academicYear = null): Collection
    {
        return AdmissionApplication::query()
            ->when($academicYear, fn ($query, $year) => $query->where('academic_year', $year))
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($total) => (int) $total);
    }
}
