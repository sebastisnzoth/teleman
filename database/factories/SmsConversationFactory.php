<?php

namespace Database\Factories;

use App\Models\PhoneNumber;
use App\Models\SmsConversation;
use Illuminate\Database\Eloquent\Factories\Factory;

class SmsConversationFactory extends Factory
{
    protected $model = SmsConversation::class;

    public function definition(): array
    {
        return [
            'phone_number_id' => PhoneNumber::factory(),
            'contact_phone' => '+1' . fake()->numerify('##########'),
            'status' => 'active',
            'last_message_at' => now(),
        ];
    }
}
