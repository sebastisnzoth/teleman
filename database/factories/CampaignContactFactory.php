<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CampaignContact;
use Illuminate\Database\Eloquent\Factories\Factory;

class CampaignContactFactory extends Factory
{
    protected $model = CampaignContact::class;

    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'phone_number' => $this->faker->e164PhoneNumber(),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->safeEmail(),
            'status' => 'pending',
            'call_attempts' => 0,
        ];
    }

    public function calling(): static
    {
        return $this->state(fn() => [
            'status' => 'calling',
            'last_call_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn() => [
            'status' => 'completed',
            'call_attempts' => 1,
        ]);
    }

    public function failed(int $attempts = 1): static
    {
        return $this->state(fn() => [
            'status' => 'failed',
            'call_attempts' => $attempts,
        ]);
    }
}
