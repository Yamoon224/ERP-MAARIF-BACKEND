<?php

namespace App\Domains\Expenses\Http\Requests;

use App\Domains\Accounting\Enums\PaymentMethod;
use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Saisie ou correction d'une depense. Le montant n'est pas saisi : il est
 * calcule (quantite x prix unitaire). Une categorie desactivee n'est plus
 * proposee, sauf pour la depense qui l'utilise deja.
 */
class ExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $current = $this->route('expense');
        $currentCategoryId = $current instanceof Expense ? $current->expense_category_id : null;

        return [
            'expense_category_id' => [
                'required',
                'uuid',
                Rule::exists('expense_categories', 'id')->where(
                    fn ($query) => $query->where('is_active', true)->orWhere('id', $currentCategoryId),
                ),
            ],
            'label' => ['required', 'string', 'max:150'],
            'supplier_name' => ['nullable', 'string', 'max:150'],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'unit' => ['nullable', 'string', 'max:30'],
            'unit_price' => ['required', 'numeric', 'min:0.01', 'max:9999999999'],
            'method' => ['required', Rule::in(PaymentMethod::values())],
            'invoice_reference' => ['nullable', 'string', 'max:100'],
            'spent_at' => ['nullable', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
