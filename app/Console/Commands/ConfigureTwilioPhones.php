<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TwilioGlobalConfig;
use Illuminate\Console\Command;
use Twilio\Rest\Client;

class ConfigureTwilioPhones extends Command
{
    protected $signature = 'twilio:configure-phones 
                            {--phone=* : Specific phone numbers to configure (optional)}
                            {--all : Configure all phone numbers}';

    protected $description = 'Configure Twilio phone numbers with proper webhooks for inbound/outbound calling';

    public function handle(): int
    {
        $this->info('🔧 Configuring Twilio Phone Numbers');
        $this->newLine();

        // Get active Twilio configuration
        $config = TwilioGlobalConfig::active();
        
        if (!$config) {
            $this->error('No active Twilio configuration found.');
            $this->info('Please run: php artisan twilio:configure');
            return self::FAILURE;
        }

        $this->info("Using configuration:");
        $this->line("  Account SID: {$config->account_sid}");
        $this->line("  Webhook URL: {$config->webhook_url}");
        $this->newLine();

        try {
            $client = new Client(
                $config->account_sid,
                $config->getDecryptedAuthToken()
            );

            // SECURITY: Only configure phone numbers that are in our database
            // unless --all flag is explicitly used
            $configureAll = $this->option('all');
            $phoneOption = $this->option('phone');
            
            if ($configureAll && empty($phoneOption)) {
                $this->warn('⚠️  WARNING: --all flag will configure ALL Twilio phone numbers!');
                $this->warn('This may override configurations for numbers used by other applications.');
                
                if (!$this->confirm('Are you ABSOLUTELY SURE you want to configure ALL numbers?', false)) {
                    $this->info('Cancelled for safety.');
                    return self::SUCCESS;
                }
                
                // Fetch all phone numbers from Twilio
                $this->info('Fetching ALL phone numbers from Twilio...');
                $numbers = $client->incomingPhoneNumbers->read();
            } else {
                // Safe mode: Only fetch numbers from our database
                $managedNumbers = \App\Models\PhoneNumber::whereIn('status', ['available', 'assigned'])
                    ->whereNotNull('twilio_sid')
                    ->get();
                
                if ($managedNumbers->isEmpty()) {
                    $this->warn('No managed phone numbers found in database.');
                    $this->info('Add phone numbers via: php artisan phone:add or through the web interface');
                    return self::SUCCESS;
                }
                
                $this->info('Found ' . $managedNumbers->count() . ' managed phone number(s) in database');
                
                // Filter if specific phones requested
                if (!empty($phoneOption)) {
                    $managedNumbers = $managedNumbers->filter(function($num) use ($phoneOption) {
                        return in_array($num->number, $phoneOption);
                    });
                    
                    if ($managedNumbers->isEmpty()) {
                        $this->error('None of the specified numbers are managed in the database.');
                        return self::FAILURE;
                    }
                }
                
                // Fetch these specific numbers from Twilio
                $numbers = [];
                foreach ($managedNumbers as $managedNum) {
                    try {
                        $twilioNumbers = $client->incomingPhoneNumbers->read([
                            'phoneNumber' => $managedNum->number
                        ]);
                        if (!empty($twilioNumbers)) {
                            $numbers[] = $twilioNumbers[0];
                        }
                    } catch (\Exception $e) {
                        $this->error("Failed to fetch {$managedNum->number}: {$e->getMessage()}");
                    }
                }
            }
            
            if (empty($numbers)) {
                $this->warn('No phone numbers to configure.');
                return self::SUCCESS;
            }

            $this->info('Configuring ' . count($numbers) . ' phone number(s)');
            $this->newLine();

            // Prepare webhook URLs
            $voiceUrl = rtrim($config->webhook_url, '/') . '/twiml/manual-call';
            $statusCallbackUrl = rtrim($config->webhook_url, '/') . '/webhooks/twilio/call-status';

            $configured = 0;
            $errors = [];

            foreach ($numbers as $number) {
                $this->line("📞 {$number->phoneNumber}");
                
                // Check if already configured
                if ($number->voiceUrl === $voiceUrl && $number->statusCallback === $statusCallbackUrl) {
                    $this->info('   ✓ Already configured correctly');
                    continue;
                }

                // Ask for confirmation if not using --all flag
                if (!$configureAll && !$this->confirm("   Configure this number?", true)) {
                    $this->line('   ⊝ Skipped');
                    continue;
                }

                try {
                    // Update phone number to use TwiML Application (recommended approach)
                    $client->incomingPhoneNumbers($number->sid)
                        ->update([
                            'voiceApplicationSid' => $config->twiml_app_sid,
                        ]);

                    $this->info('   ✓ Configured successfully (using TwiML App)');
                    $configured++;
                } catch (\Exception $e) {
                    $this->error('   ✗ Failed: ' . $e->getMessage());
                    $errors[] = $number->phoneNumber;
                }

                $this->newLine();
            }

            // Summary
            $this->newLine();
            $this->info('=== Summary ===');
            $this->line("Configured: {$configured} phone number(s)");
            
            if (!empty($errors)) {
                $this->error('Failed: ' . count($errors) . ' phone number(s)');
                foreach ($errors as $phone) {
                    $this->line("  - {$phone}");
                }
            }

            $this->newLine();
            $this->info('✅ Phone number configuration complete!');
            $this->newLine();
            $this->info('Webhook URLs configured:');
            $this->line("  Voice URL: {$voiceUrl}");
            $this->line("  Status Callback: {$statusCallbackUrl}");
            $this->newLine();
            $this->info('Next steps:');
            $this->line('1. Test inbound calling: Call one of your Twilio numbers');
            $this->line('2. Test outbound calling: Use the softphone dialer in your browser');
            $this->line('3. Check call logs in the admin panel');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Failed to configure phone numbers: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
