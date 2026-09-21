<?php

namespace App\Models;

use App\Domains\Accounting\Enums\MobileMoneyOperator;
use App\Domains\Accounting\Enums\MobileMoneyStatus;
use App\Domains\Accounting\Enums\PaymentPeriod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Demande de paiement de scolarité par mobile money (voir la migration pour le
 * cycle de vie). Les mois et le montant sont ceux calculés à l'initiation.
 *
 * @property string $id
 * @property string $reference
 * @property string $enrollment_id
 * @property MobileMoneyOperator $operator
 * @property string $phone
 * @property PaymentPeriod $period_type
 * @property list<string> $months mois visés, au format `YYYY-MM`
 * @property string $amount
 * @property MobileMoneyStatus $status
 * @property string|null $provider_reference
 * @property string|null $failure_reason
 * @property string|null $payment_id
 * @property Carbon $expires_at
 * @property Carbon|null $confirmed_at
 */
class MobileMoneyTransaction extends Model
{
    use HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'reference', 'enrollment_id', 'operator', 'phone', 'period_type', 'months', 'amount',
        'status', 'provider_reference', 'failure_reason', 'payment_id', 'expires_at', 'confirmed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'operator' => MobileMoneyOperator::class,
            'period_type' => PaymentPeriod::class,
            'status' => MobileMoneyStatus::class,
            'months' => 'array',
            'amount' => 'decimal:2',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function hasExpired(): bool
    {
        return $this->expires_at->isPast();
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
