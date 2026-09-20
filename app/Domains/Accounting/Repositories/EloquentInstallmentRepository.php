<?php

namespace App\Domains\Accounting\Repositories;

use App\Domains\Accounting\Contracts\InstallmentRepositoryContract;
use App\Models\TuitionInstallment;
use Illuminate\Support\Collection;

final class EloquentInstallmentRepository implements InstallmentRepositoryContract
{
    public function forEnrollment(string $enrollmentId): Collection
    {
        return TuitionInstallment::query()
            ->with('payment:id,receipt_number,paid_at')
            ->where('enrollment_id', $enrollmentId)
            ->orderBy('month')
            ->get();
    }

    public function unpaidForEnrollment(string $enrollmentId, bool $lock = false): Collection
    {
        return TuitionInstallment::query()
            ->where('enrollment_id', $enrollmentId)
            ->whereNull('payment_id')
            ->orderBy('month')
            ->when($lock, fn ($query) => $query->lockForUpdate())
            ->get();
    }

    public function create(array $attributes): TuitionInstallment
    {
        return TuitionInstallment::create($attributes);
    }

    public function updateAmount(TuitionInstallment $installment, float $amount): void
    {
        $installment->update(['amount' => $amount]);
    }

    public function assignToPayment(array $installmentIds, string $paymentId): void
    {
        TuitionInstallment::query()->whereIn('id', $installmentIds)->update(['payment_id' => $paymentId]);
    }

    public function releasePayment(string $paymentId): void
    {
        TuitionInstallment::query()->where('payment_id', $paymentId)->update(['payment_id' => null]);
    }
}
