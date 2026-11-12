<?php

namespace Database\Factories;

use App\Models\Sequence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sequence>
 */
class SequenceFactory extends Factory
{
    protected $model = Sequence::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->words(3, true) . ' Follow-Up',
            'description' => fake()->sentence(),
            'trigger_type' => fake()->randomElement(['no_answer', 'interested', 'callback_requested', 'not_interested', 'manual', 'completed']),
            'trigger_conditions' => [],
            'stop_conditions' => [],
            'is_active' => fake()->boolean(70),
            'use_smart_timing' => fake()->boolean(50),
            'max_enrollments' => fake()->boolean() ? fake()->numberBetween(100, 1000) : null,
            'priority' => fake()->numberBetween(1, 10),
            'total_enrolled' => 0,
            'total_completed' => 0,
            'total_converted' => 0,
            'conversion_rate' => 0.00,
        ];
    }

    /**
     * Indicate that the sequence is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the sequence is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the sequence uses smart timing.
     */
    public function smartTiming(): static
    {
        return $this->state(fn (array $attributes) => [
            'use_smart_timing' => true,
        ]);
    }

    /**
     * Sequence for no answer trigger.
     */
    public function noAnswer(): static
    {
        return $this->state(fn (array $attributes) => [
            'trigger_type' => 'no_answer',
            'name' => 'No Answer Follow-Up',
        ]);
    }

    /**
     * Sequence for interested trigger.
     */
    public function interested(): static
    {
        return $this->state(fn (array $attributes) => [
            'trigger_type' => 'interested',
            'name' => 'Interested Lead Nurture',
            'priority' => 10,
        ]);
    }
}

