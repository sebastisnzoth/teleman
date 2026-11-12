<?php

namespace Database\Factories;

use App\Models\TwilioGlobalConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

class TwilioGlobalConfigFactory extends Factory
{
    protected $model = TwilioGlobalConfig::class;

    public function definition(): array
    {
        return [
            'account_sid' => 'AC' . $this->faker->regexify('[a-f0-9]{32}'),
            'auth_token' => $this->faker->regexify('[a-f0-9]{32}'),
            'is_active' => true,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn() => [
            'verified_at' => now(),
        ]);
    }
}
