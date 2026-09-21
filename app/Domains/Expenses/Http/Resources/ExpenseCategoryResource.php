<?php

namespace App\Domains\Expenses\Http\Resources;

use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ExpenseCategory */
class ExpenseCategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'expenses_count' => $this->expenses_count ?? 0,
        ];
    }
}
