<?php

namespace Database\Factories;

use App\InvoicePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoicePayment>
 */
class InvoicePaymentFactory extends Factory
{
    protected $model = InvoicePayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'amount' => fake()->randomFloat(2, 100, 5000),
            'paid_at' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'notes' => null,
        ];
    }
}
