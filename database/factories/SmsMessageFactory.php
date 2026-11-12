<?php

namespace Database\Factories;

use App\Models\SmsConversation;
use App\Models\SmsMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmsMessageFactory extends Factory
{
    protected $model = SmsMessage::class;

    public function definition(): array
    {
        return [
            'conversation_id' => SmsConversation::factory(),
            'direction' => 'outbound',
            'message_body' => fake()->sentence(),
            'sender_phone' => '+1' . fake()->numerify('##########'),
            'receiver_phone' => '+1' . fake()->numerify('##########'),
            'status' => 'sent',
            'num_segments' => 1,
            'cost' => 0.0079,
            'metadata' => ['country_code' => 'US'],
        ];
    }

    public function inbound(): static
    {
        return $this->state(fn () => ['direction' => 'inbound']);
    }

    public function delivered(): static
    {
        return $this->state(fn () => [
            'status' => 'delivered',
            'delivered_at' => now(),
        ]);
    }
}
