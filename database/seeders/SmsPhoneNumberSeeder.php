<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SmsPhoneNumber;
use App\Models\User;

class SmsPhoneNumberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the first user (admin)
        $user = User::first();
        
        if (!$user) {
            $this->command->warn('No users found. Please create a user first.');
            return;
        }

        // Get Twilio phone number from env
        $twilioPhone = env('TWILIO_PHONE_NUMBER');
        
        if (!$twilioPhone) {
            $this->command->warn('TWILIO_PHONE_NUMBER not set in .env file');
            return;
        }

        // Check if phone number already exists
        if (SmsPhoneNumber::where('phone_number', $twilioPhone)->exists()) {
            $this->command->info('Twilio phone number already registered');
            return;
        }

        // Create the SMS phone number
        $phoneNumber = SmsPhoneNumber::create([
            'user_id' => $user->id,
            'provider' => 'twilio',
            'phone_number' => $twilioPhone,
            'ai_agent_id' => null, // Will be assigned via UI
            'is_active' => true,
            'capabilities' => [
                'sms' => true,
                'mms' => true,
                'voice' => false
            ],
            'settings' => [
                'auto_respond' => false, // Will be enabled when AI agent is assigned
                'business_hours_only' => false
            ]
        ]);

        $this->command->info("✅ SMS phone number created: {$phoneNumber->phone_number}");
        $this->command->info("📱 Provider: {$phoneNumber->provider}");
        $this->command->info("👤 Owner: {$user->email}");
        $this->command->line('');
        $this->command->info('Next steps:');
        $this->command->line('1. Log in to the application');
        $this->command->line('2. Navigate to SMS Management → Phone Numbers');
        $this->command->line('3. Assign an AI agent to this phone number');
        $this->command->line('4. Configure the webhook URL in Twilio console:');
        $this->command->line('   ' . config('app.url') . '/api/sms/webhook/twilio/incoming');
    }
}
