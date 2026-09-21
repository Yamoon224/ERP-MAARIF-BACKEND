<?php

namespace App\Domains\Expenses\Http\Resources;

use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Expense */
class ExpenseResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'label' => $this->label,
            'supplier_name' => $this->supplier_name,
            'quantity' => (float) $this->quantity,
            'unit' => $this->unit,
            'unit_price' => (float) $this->unit_price,
            'amount' => (float) $this->amount,
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'invoice_reference' => $this->invoice_reference,
            'spent_at' => $this->spent_at?->toDateString(),
            'note' => $this->note,
            'status' => $this->isCancelled() ? 'cancelled' : 'valid',
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancellation_reason' => $this->cancellation_reason,
            'recorded_by' => $this->whenLoaded('recordedBy', fn () => $this->recordedBy ? [
                'id' => $this->recordedBy->id,
                'name' => $this->recordedBy->name,
            ] : null),
        ];
    }
}
