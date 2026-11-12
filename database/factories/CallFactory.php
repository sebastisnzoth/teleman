<?php

namespace Database\Factories;

use App\Models\Call;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CallFactory extends Factory
{
    protected $model = Call::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'call_type' => 'manual',
            'direction' => 'outbound',
            'from_number' => $this->faker->e164PhoneNumber(),
            'to_number' => $this->faker->e164PhoneNumber(),
            'twilio_call_sid' => 'CA' . $this->faker->regexify('[a-f0-9]{32}'),
            'status' => 'initiated',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn() => [
            'status' => 'completed',
            'duration_seconds' => $this->faker->numberBetween(10, 300),
            'started_at' => now()->subMinutes(5),
            'ended_at' => now(),
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn() => [
            'status' => 'in-progress',
            'started_at' => now()->subMinutes(2),
        ]);
    }

    public function campaign(int $campaignId, ?int $contactId = null): static
    {
        return $this->state(fn() => [
            'call_type' => 'campaign',
            'campaign_id' => $campaignId,
            'campaign_contact_id' => $contactId,
        ]);
    }
}
