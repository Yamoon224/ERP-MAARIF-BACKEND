<?php

namespace Database\Factories;

use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ExpenseCategory> */
class ExpenseCategoryFactory extends Factory
{
    protected $model = ExpenseCategory::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        // Le nom est unique en base : un compteur evite les doublons en creation par lot.
        return [
            'name' => 'Categorie '.++self::$sequence,
            'description' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
