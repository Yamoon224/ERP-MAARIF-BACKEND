<?php

namespace App\Models;

use App\Domains\Accounting\Enums\PaymentMethod;
use Database\Factories\ExpenseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Depense ou approvisionnement de l'etablissement : achat de craies, de
 * registres, reparation... Le montant est toujours quantite x prix unitaire.
 *
 * @property string $id
 * @property string $number
 * @property string $expense_category_id
 * @property string $label
 * @property string|null $supplier_name
 * @property string $quantity
 * @property string|null $unit
 * @property string $unit_price
 * @property string $amount
 * @property PaymentMethod $method
 * @property Carbon $spent_at
 * @property Carbon|null $cancelled_at
 */
class Expense extends Model
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory, HasUuids;

    /** @var list<string> */
    protected $fillable = [
        'number', 'expense_category_id', 'label', 'supplier_name', 'quantity', 'unit', 'unit_price', 'amount',
        'method', 'invoice_reference', 'spent_at', 'note', 'recorded_by',
        'cancelled_at', 'cancelled_by', 'cancellation_reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
            'spent_at' => 'date:Y-m-d',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
