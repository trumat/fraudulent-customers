<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Scan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A clean adult Dutch customer.
 *
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scan_id' => Scan::factory(),
            'external_id' => fake()->numberBetween(10000, 99999),
            'bsn' => fake()->numberBetween(100000000, 999999999),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'date_of_birth' => fake()->dateTimeBetween('-80 years', '-18 years'),
            'phone_number' => '+316'.fake()->numerify('########'),
            'email' => fake()->safeEmail(),
            'tag' => 'new-customer',
            'street' => fake()->streetAddress(),
            'postcode' => fake()->numerify('####').fake()->lexify('??'),
            'city' => fake()->city(),
            'products' => ['landline'],
            'ip_address' => fake()->unique()->ipv4(),
            'iban' => 'NL'.fake()->numerify('##').'TEST'.fake()->unique()->numerify('##########'),
            'last_invoice_date' => fake()->dateTimeBetween('-1 year'),
            'last_login_at' => fake()->dateTimeBetween('-1 year'),
            'is_fraudulent' => false,
            'fraud_reasons' => [],
        ];
    }
}
