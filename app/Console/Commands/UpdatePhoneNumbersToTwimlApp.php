<?php

namespace App\Console\Commands;

use App\Models\TwilioGlobalConfig;
use Illuminate\Console\Command;
use Twilio\Rest\Client;

class UpdatePhoneNumbersToTwimlApp extends Command
{
    protected $signature = 'twilio:update-phone-numbers {numbers?* : Specific phone numbers to update (optional)}';
    protected $description = 'Update specific phone numbers to use the configured TwiML Application';

    public function handle()
    {
        $this->info('🔧 Updating Phone Numbers to use TwiML Application');
        $this->newLine();

        $config = TwilioGlobalConfig::active();

        if (!$config) {
            $this->error('No active Twilio configuration found. Run: php artisan twilio:configure');
            return 1;
        }

        if (!$config->twiml_app_sid) {
            $this->error('No TwiML Application SID configured.');
            return 1;
        }

        $this->info("Using TwiML App SID: {$config->twiml_app_sid}");
        $this->newLine();

        try {
            $client = new Client(
                $config->account_sid,
                $config->getDecryptedAuthToken()
            );

            // Fetch the TwiML App to show its name
            $app = $client->applications($config->twiml_app_sid)->fetch();
            $this->info("TwiML App Name: {$app->friendlyName}");
            $this->newLine();

            // Get specific numbers to update (from command arguments)
            $numbersToUpdate = $this->argument('numbers');

            // SECURITY: By default, only update numbers in our database
            // Get managed phone numbers from database
            $managedNumbers = \App\Models\PhoneNumber::whereIn('status', ['available', 'assigned'])
                ->whereNotNull('twilio_sid')
                ->pluck('number')
                ->toArray();

            if (empty($managedNumbers)) {
                $this->warn('No managed phone numbers found in database.');
                $this->info('Add phone numbers via: php artisan phone:add or through the web interface');
                return 0;
            }

            $this->info('Found ' . count($managedNumbers) . ' managed phone number(s) in database');

            // Get all phone numbers from Twilio
            $allNumbers = $client->incomingPhoneNumbers->read();

            if (empty($allNumbers)) {
                $this->warn('No phone numbers found in Twilio account.');
                return 0;
            }

            // If specific numbers provided, filter to only those
            if (!empty($numbersToUpdate)) {
                $this->info('Updating only specified phone numbers: ' . implode(', ', $numbersToUpdate));
                $this->newLine();
                
                // Check that specified numbers are in our database
                $notManaged = array_diff($numbersToUpdate, $managedNumbers);
                if (!empty($notManaged)) {
                    $this->warn('WARNING: These numbers are NOT in your database: ' . implode(', ', $notManaged));
                    if (!$this->confirm('Continue anyway?', false)) {
                        $this->info('Cancelled.');
                        return 0;
                    }
                }
                
                $numbers = array_filter($allNumbers, function($num) use ($numbersToUpdate) {
                    return in_array($num->phoneNumber, $numbersToUpdate);
                });
                
                if (empty($numbers)) {
                    $this->error('None of the specified phone numbers were found in your Twilio account.');
                    return 1;
                }
            } else {
                // Default: Only update numbers that are in our database
                $this->info('Updating only managed phone numbers from database');
                $this->newLine();
                
                $numbers = array_filter($allNumbers, function($num) use ($managedNumbers) {
                    return in_array($num->phoneNumber, $managedNumbers);
                });
                
                if (empty($numbers)) {
                    $this->warn('None of your managed numbers were found in Twilio.');
                    return 0;
                }
            }

            $this->info('Processing ' . count($numbers) . ' phone number(s)');
            $this->newLine();

            $updated = 0;
            $skipped = 0;

            foreach ($numbers as $number) {
                $this->line("📞 {$number->phoneNumber} ({$number->friendlyName})");

                // Check current configuration
                if ($number->voiceApplicationSid === $config->twiml_app_sid) {
                    $this->line("   ✓ Already configured with correct TwiML App");
                    $skipped++;
                } else {
                    // Update the phone number to use the TwiML Application
                    $client->incomingPhoneNumbers($number->sid)->update([
                        'voiceApplicationSid' => $config->twiml_app_sid,
                    ]);

                    $this->line("   ✓ Updated to use: {$app->friendlyName}");
                    $updated++;
                }

                $this->newLine();
            }

            $this->newLine();
            $this->info("✅ Complete!");
            $this->info("   Updated: {$updated} phone number(s)");
            $this->info("   Skipped: {$skipped} phone number(s) (already configured)");

            return 0;

        } catch (\Exception $e) {
            $this->error('Failed to update phone numbers: ' . $e->getMessage());
            return 1;
        }
    }
}
