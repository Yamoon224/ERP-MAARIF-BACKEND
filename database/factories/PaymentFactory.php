<?php

namespace Database\Factories;

use App\Domains\Accounting\Enums\PaymentMethod;
use App\Domains\Accounting\Enums\PaymentPeriod;
use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    private static int $sequence = 0;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        // Compteur en memoire : le numero de recu est unique, et un tirage
        // aleatoire finirait par produire un doublon en creation par lot.
        return [
            'receipt_number' => sprintf('REC-%s-%06d', now()->year, ++self::$sequence),
            'enrollment_id' => Enrollment::factory(),
            'period_type' => PaymentPeriod::Monthly,
            'months' => [now()->format('Y-m')],
            'amount' => 50000,
            'method' => PaymentMethod::Cash,
            'paid_at' => now()->toDateString(),
        ];
    }
}
