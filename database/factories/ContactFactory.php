<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'phone_number' => $this->faker->unique()->e164PhoneNumber(),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->safeEmail(),
            'status' => 'active',
            'language' => 'en',
            'data_quality_score' => 100,
        ];
    }

    public function optedOut(): static
    {
        return $this->state(fn() => [
            'opted_out' => true,
            'opted_out_at' => now(),
        ]);
    }
}
