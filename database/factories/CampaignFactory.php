<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->sentence(3),
            'type' => 'text_to_speech',
            'status' => 'draft',
            'message' => $this->faker->paragraph(),
            'voice_gender' => 'female',
            'voice_language' => 'en-US',
            'enable_recording' => false,
            'enable_dtmf' => false,
            'enable_amd' => false,
            'max_concurrent_calls' => 5,
            'retry_attempts' => 3,
            'retry_delay_minutes' => 5,
            'total_contacts' => 0,
            'total_called' => 0,
            'total_answered' => 0,
            'total_failed' => 0,
        ];
    }

    public function running(): static
    {
        return $this->state(fn() => [
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn() => [
            'status' => 'completed',
            'started_at' => now()->subHours(2),
            'completed_at' => now(),
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn() => [
            'status' => 'paused',
            'started_at' => now()->subHour(),
            'paused_at' => now(),
        ]);
    }

    public function assignedTo(User $agent): static
    {
        return $this->state(fn() => [
            'assigned_agent_id' => $agent->id,
        ]);
    }
}
