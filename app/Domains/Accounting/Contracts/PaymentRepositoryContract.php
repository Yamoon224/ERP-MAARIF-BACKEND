<?php

namespace App\Domains\Accounting\Contracts;

use App\Models\Payment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PaymentRepositoryContract
{
    /**
     * @param  array<string, mixed>  $filters  student_id, enrollment_id, school_class_id, period_type, method,
     *                                         status (valid|cancelled), search, academic_year/term_id/month (sur la date de paiement)
     * @return LengthAwarePaginator<int, Payment>
     */
    public function paginate(array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function findOrFail(string $id): Payment;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): Payment;

    /** @param  array<string, mixed>  $attributes */
    public function update(Payment $payment, array $attributes): Payment;

    /** Dernier numero de recu commencant par ce prefixe, pour en deduire le suivant. */
    public function lastReceiptNumber(string $prefix): ?string;
}
