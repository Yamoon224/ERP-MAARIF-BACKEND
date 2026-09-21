<?php

namespace Database\Factories;

use App\Domains\Accounting\Enums\PaymentMethod;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Expense> */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        // Compteur en memoire : le numero est unique, et un tirage aleatoire
        // finirait par produire un doublon en creation par lot.
        return [
            'number' => sprintf('DEP-%s-%06d', now()->year, ++self::$sequence),
            'expense_category_id' => ExpenseCategory::factory(),
            'label' => 'Craies blanches',
            'supplier_name' => null,
            'quantity' => 1,
            'unit' => null,
            'unit_price' => 10000,
            'amount' => 10000,
            'method' => PaymentMethod::Cash,
            'spent_at' => now()->toDateString(),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(['cancelled_at' => now(), 'cancellation_reason' => 'Saisie en double']);
    }
}
