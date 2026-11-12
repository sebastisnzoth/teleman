<?php

namespace Database\Factories;

use App\Models\NumberRequest;
use App\Models\PhoneNumber;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NumberRequest>
 */
class NumberRequestFactory extends Factory
{
    protected $model = NumberRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone_number_id' => PhoneNumber::factory(),
            'customer_id' => User::factory(),
            'status' => 'pending',
            'admin_id' => null,
            'processed_at' => null,
            'customer_notes' => fake()->optional()->sentence(),
            'admin_notes' => null,
            'requested_at' => now(),
        ];
    }

    /**
     * Indicate that the request is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'admin_id' => null,
            'processed_at' => null,
            'admin_notes' => null,
        ]);
    }

    /**
     * Indicate that the request is approved.
     */
    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'admin_id' => User::factory(),
            'processed_at' => now(),
            'admin_notes' => fake()->optional()->sentence(),
        ]);
    }

    /**
     * Indicate that the request is rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'admin_id' => User::factory(),
            'processed_at' => now(),
            'admin_notes' => fake()->sentence(),
        ]);
    }

    /**
     * Indicate that the request is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'admin_id' => null,
            'processed_at' => now(),
        ]);
    }
}
