<?php

namespace App\Domains\Expenses\Services;

use App\Domains\Expenses\Contracts\ExpenseRepositoryContract;
use App\Domains\Expenses\Exceptions\ExpenseException;
use App\Domains\Expenses\Support\ExpenseNumberGenerator;
use App\Models\Expense;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Registre des depenses et approvisionnements de l'etablissement.
 *
 * Le montant est calcule ici (quantite x prix unitaire), jamais fourni par le
 * client : une ligne dont le total ne correspond pas a son detail fausserait
 * tous les bilans. Comme un paiement, une depense n'est pas supprimee mais
 * annulee, avec son motif.
 */
final class ExpenseService
{
    public function __construct(
        private readonly ExpenseRepositoryContract $expenses,
        private readonly ExpenseNumberGenerator $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Expense>
     */
    public function list(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->expenses->paginate($filters, $perPage);
    }

    public function find(string $id): Expense
    {
        return $this->expenses->findOrFail($id);
    }

    /** @return list<string> */
    public function suppliers(): array
    {
        return $this->expenses->suppliers();
    }

    /**
     * @param  array<string, mixed>  $data  expense_category_id, label, supplier_name, quantity, unit, unit_price,
     *                                      method, invoice_reference, spent_at, note
     */
    public function register(array $data, string $recordedByUserId): Expense
    {
        return DB::transaction(fn (): Expense => $this->expenses->create([
            ...$this->attributes($data),
            'number' => $this->numbers->next(Carbon::now()),
            'recorded_by' => $recordedByUserId,
        ]));
    }

    /**
     * Corrige une depense encore valide (faute de frappe, prix ajuste). Le
     * numero et l'auteur de la saisie ne changent pas.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Expense $expense, array $data): Expense
    {
        if ($expense->isCancelled()) {
            throw ExpenseException::cancelledNotEditable();
        }

        return $this->expenses->update($expense, $this->attributes($data));
    }

    public function cancel(Expense $expense, string $reason, string $cancelledByUserId): Expense
    {
        if ($expense->isCancelled()) {
            throw ExpenseException::alreadyCancelled();
        }

        return $this->expenses->update($expense, [
            'cancelled_at' => Carbon::now(),
            'cancelled_by' => $cancelledByUserId,
            'cancellation_reason' => $reason,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $quantity = (float) $data['quantity'];
        $unitPrice = (float) $data['unit_price'];

        return [
            'expense_category_id' => $data['expense_category_id'],
            'label' => $data['label'],
            'supplier_name' => $data['supplier_name'] ?? null,
            'quantity' => $quantity,
            'unit' => $data['unit'] ?? null,
            'unit_price' => $unitPrice,
            'amount' => round($quantity * $unitPrice, 2),
            'method' => $data['method'],
            'invoice_reference' => $data['invoice_reference'] ?? null,
            'spent_at' => $data['spent_at'] ?? today()->toDateString(),
            'note' => $data['note'] ?? null,
        ];
    }
}
