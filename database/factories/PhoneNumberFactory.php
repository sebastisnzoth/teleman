<?php

namespace Database\Factories;

use App\Models\PhoneNumber;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PhoneNumber>
 */
class PhoneNumberFactory extends Factory
{
    protected $model = PhoneNumber::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $areaCode = fake()->randomElement(['415', '510', '650', '408', '925']);
        
        return [
            'number' => '+1' . $areaCode . fake()->unique()->numerify('#######'),
            'friendly_name' => 'US Number (' . $areaCode . ')',
            'country_code' => 'US',
            'area_code' => $areaCode,
            'status' => 'available',
            'twilio_sid' => 'PN' . fake()->uuid(),
            'user_id' => null,
            'requested_by' => null,
            'approved_by' => null,
            'capabilities' => [
                'voice' => true,
                'sms' => true,
                'mms' => false,
            ],
            'monthly_cost' => fake()->randomFloat(2, 1.00, 5.00),
            'requested_at' => null,
            'approved_at' => null,
            'assigned_at' => null,
            'released_at' => null,
        ];
    }

    /**
     * Indicate that the phone number is available.
     */
    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'available',
            'user_id' => null,
            'requested_by' => null,
            'approved_by' => null,
        ]);
    }

    /**
     * Indicate that the phone number is requested.
     */
    public function requested(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'requested',
            'requested_by' => User::factory(),
            'requested_at' => now(),
        ]);
    }

    /**
     * Indicate that the phone number is assigned.
     */
    public function assigned(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'assigned',
            'user_id' => User::factory(),
            'approved_by' => User::factory(),
            'assigned_at' => now(),
            'approved_at' => now(),
        ]);
    }

    /**
     * Indicate that the phone number is released.
     */
    public function released(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'released',
            'released_at' => now(),
        ]);
    }

    /**
     * Indicate voice capability only.
     */
    public function voiceOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'capabilities' => [
                'voice' => true,
                'sms' => false,
                'mms' => false,
            ],
        ]);
    }

    /**
     * Indicate SMS capability only.
     */
    public function smsOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'capabilities' => [
                'voice' => false,
                'sms' => true,
                'mms' => false,
            ],
        ]);
    }
}
