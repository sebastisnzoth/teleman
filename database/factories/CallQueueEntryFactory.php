<?php

namespace Database\Factories;

use App\Models\CallQueueEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CallQueueEntryFactory extends Factory
{
    protected $model = CallQueueEntry::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'caller_number' => $this->faker->e164PhoneNumber(),
            'call_sid' => 'CA' . $this->faker->regexify('[a-f0-9]{32}'),
            'queue_name' => 'queue_user_1',
            'status' => 'waiting',
            'position' => 1,
            'queued_at' => now(),
        ];
    }

    public function waiting(): static
    {
        return $this->state(fn () => [
            'status' => 'waiting',
        ]);
    }

    public function connecting(): static
    {
        return $this->state(fn () => [
            'status' => 'connecting',
        ]);
    }

    public function connected(): static
    {
        return $this->state(fn () => [
            'status' => 'connected',
            'connected_at' => now(),
            'wait_duration_seconds' => $this->faker->numberBetween(5, 120),
        ]);
    }

    public function abandoned(): static
    {
        return $this->state(fn () => [
            'status' => 'abandoned',
            'abandoned_at' => now(),
            'wait_duration_seconds' => $this->faker->numberBetween(5, 60),
        ]);
    }

    public function forUser(User $user): static
    {
        $ownerId = $user->isAgent() ? $user->parent_user_id : $user->id;
        return $this->state(fn () => [
            'user_id' => $ownerId,
            'queue_name' => 'queue_user_' . $ownerId,
        ]);
    }
}
