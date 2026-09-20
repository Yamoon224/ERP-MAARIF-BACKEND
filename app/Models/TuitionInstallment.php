<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Echeance mensuelle de scolarite d'une inscription. `payment_id` vide veut
 * dire "a payer".
 *
 * @property string $id
 * @property string $enrollment_id
 * @property Carbon $month premier jour du mois
 * @property string $amount
 * @property string|null $payment_id
 */
class TuitionInstallment extends Model
{
    use HasUuids;

    protected $table = 'tuition_installments';

    /** @var list<string> */
    protected $fillable = ['enrollment_id', 'month', 'amount', 'payment_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'month' => 'date:Y-m-d',
            'amount' => 'decimal:2',
        ];
    }

    public function isPaid(): bool
    {
        return $this->payment_id !== null;
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return BelongsTo<Payment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
