<?php

namespace App\Models;

use App\Domains\Accounting\Enums\PaymentMethod;
use App\Domains\Accounting\Enums\PaymentPeriod;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Paiement de scolarite : regle un ou plusieurs mois consecutifs d'une
 * inscription et donne lieu a un recu numerote.
 *
 * @property string $id
 * @property string $receipt_number
 * @property string $enrollment_id
 * @property PaymentPeriod $period_type
 * @property int $months_count
 * @property string $amount
 * @property PaymentMethod $method
 * @property Carbon $paid_at
 * @property Carbon|null $cancelled_at
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'receipt_number', 'enrollment_id', 'period_type', 'months_count', 'amount', 'method',
        'reference', 'paid_at', 'note', 'received_by',
        'cancelled_at', 'cancelled_by', 'cancellation_reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'period_type' => PaymentPeriod::class,
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'months_count' => 'integer',
            'paid_at' => 'date:Y-m-d',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return HasMany<TuitionInstallment, $this> */
    public function installments(): HasMany
    {
        return $this->hasMany(TuitionInstallment::class);
    }
}
