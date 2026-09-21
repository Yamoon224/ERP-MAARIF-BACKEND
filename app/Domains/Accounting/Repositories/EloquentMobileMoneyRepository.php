<?php

namespace App\Domains\Accounting\Repositories;

use App\Domains\Accounting\Contracts\MobileMoneyRepositoryContract;
use App\Domains\Accounting\Enums\MobileMoneyStatus;
use App\Models\MobileMoneyTransaction;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

final class EloquentMobileMoneyRepository implements MobileMoneyRepositoryContract
{
    private const RELATIONS = ['payment:id,receipt_number', 'enrollment.student:id,first_name,last_name,matricule', 'enrollment.schoolClass:id,name'];

    public function create(array $attributes): MobileMoneyTransaction
    {
        return MobileMoneyTransaction::create($attributes);
    }

    public function findOrFail(string $id): MobileMoneyTransaction
    {
        return MobileMoneyTransaction::query()->with(self::RELATIONS)->findOrFail($id);
    }

    public function lockOrFail(string $id): MobileMoneyTransaction
    {
        return MobileMoneyTransaction::query()->lockForUpdate()->findOrFail($id);
    }

    public function update(MobileMoneyTransaction $transaction, array $attributes): MobileMoneyTransaction
    {
        $transaction->update($attributes);

        return $this->findOrFail($transaction->id);
    }

    public function pendingForEnrollment(string $enrollmentId): ?MobileMoneyTransaction
    {
        return MobileMoneyTransaction::query()
            ->where('enrollment_id', $enrollmentId)
            ->where('status', MobileMoneyStatus::Pending->value)
            ->first();
    }

    public function allPending(): Collection
    {
        return MobileMoneyTransaction::query()->where('status', MobileMoneyStatus::Pending->value)->get();
    }

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return MobileMoneyTransaction::query()
            ->with(self::RELATIONS)
            ->when($filters['student_id'] ?? null, fn ($query, $id) => $query->whereHas('enrollment', fn ($enrollment) => $enrollment->where('student_id', $id)))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
