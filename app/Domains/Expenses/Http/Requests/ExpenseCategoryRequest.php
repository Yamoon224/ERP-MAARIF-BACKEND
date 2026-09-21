<?php

namespace App\Domains\Expenses\Http\Requests;

use App\Models\ExpenseCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Creation ou modification d'un poste de depense : le nom est unique. */
class ExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $current = $this->route('expenseCategory');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('expense_categories', 'name')->ignore($current instanceof ExpenseCategory ? $current->id : null),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
