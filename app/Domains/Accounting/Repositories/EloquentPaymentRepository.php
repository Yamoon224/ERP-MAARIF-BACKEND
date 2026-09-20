<?php

namespace App\Domains\Accounting\Repositories;

use App\Domains\Accounting\Contracts\PaymentRepositoryContract;
use App\Domains\Shared\Support\Period;
use App\Domains\Shared\Support\Sort;
use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class EloquentPaymentRepository implements PaymentRepositoryContract
{
    /** @var array<string, string> */
    private const SORTABLE = [
        'paid_at' => 'paid_at',
        'amount' => 'amount',
    ];

    /** @var list<string> */
    private const RELATIONS = [
        'enrollment.student:id,first_name,last_name,matricule',
        'enrollment.schoolClass:id,name,level',
        'receivedBy:id,name',
    ];

    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->filtered($filters)
            ->with(self::RELATIONS)
            ->tap(fn ($query) => Sort::apply($query, $filters, self::SORTABLE, 'paid_at', 'desc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findOrFail(string $id): Payment
    {
        return Payment::query()->with(self::RELATIONS)->findOrFail($id);
    }

    public function create(array $attributes): Payment
    {
        return Payment::create($attributes);
    }

    public function update(Payment $payment, array $attributes): Payment
    {
        $payment->update($attributes);

        return $this->findOrFail($payment->id);
    }

    public function lastReceiptNumber(string $prefix): ?string
    {
        return Payment::query()
            ->where('receipt_number', 'like', "{$prefix}%")
            ->orderByDesc('receipt_number')
            ->value('receipt_number');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Payment>
     */
    private function filtered(array $filters): Builder
    {
        $status = $filters['status'] ?? null;

        // Une annee scolaire entiere = les paiements de ses inscriptions (donc
        // aussi ceux d'une famille qui a paye en septembre pour une rentree en
        // octobre) ; un trimestre ou un mois = la date du paiement.
        $query = Period::isWholeYear($filters)
            ? Payment::query()->whereHas('enrollment', fn ($enrollment) => $enrollment->where('academic_year', $filters['academic_year']))
            : Period::scope(Payment::query(), $filters, 'payments.paid_at');

        return $query
            ->when($filters['enrollment_id'] ?? null, fn ($query, $id) => $query->where('enrollment_id', $id))
            ->when($filters['student_id'] ?? null, fn ($query, $id) => $query->whereHas(
                'enrollment',
                fn ($enrollment) => $enrollment->where('student_id', $id),
            ))
            ->when($filters['school_class_id'] ?? null, fn ($query, $id) => $query->whereHas(
                'enrollment',
                fn ($enrollment) => $enrollment->where('school_class_id', $id),
            ))
            ->when($filters['period_type'] ?? null, fn ($query, $type) => $query->where('period_type', $type))
            ->when($filters['method'] ?? null, fn ($query, $method) => $query->where('method', $method))
            ->when($status === 'valid', fn ($query) => $query->whereNull('cancelled_at'))
            ->when($status === 'cancelled', fn ($query) => $query->whereNotNull('cancelled_at'))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where(
                fn ($sub) => $sub
                    ->where('receipt_number', 'like', "%{$search}%")
                    ->orWhereHas('enrollment.student', fn ($student) => $student
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('matricule', 'like', "%{$search}%")),
            ));
    }
}
