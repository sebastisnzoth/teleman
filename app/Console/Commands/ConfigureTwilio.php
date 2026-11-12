<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TwilioGlobalConfig;
use Illuminate\Console\Command;
use Twilio\Rest\Client;

class ConfigureTwilio extends Command
{
    protected $signature = 'twilio:configure 
                          {--account-sid= : Twilio Account SID}
                          {--auth-token= : Twilio Auth Token}
                          {--api-key= : Twilio API Key SID (for token generation)}
                          {--api-secret= : Twilio API Key Secret}
                          {--twiml-app= : TwiML Application SID}
                          {--webhook-url= : Webhook base URL}';

    protected $description = 'Configure global Twilio settings for WebRTC calling';

    public function handle(): int
    {
        $this->info('🔧 Twilio Global Configuration Setup');
        $this->newLine();

        // Get or prompt for values
        $accountSid = $this->option('account-sid') ?? $this->ask('Enter your Twilio Account SID (starts with AC)');
        $authToken = $this->option('auth-token') ?? $this->secret('Enter your Twilio Auth Token');
        
        // Verify credentials
        $this->info('Verifying credentials...');
        
        try {
            $client = new Client($accountSid, $authToken);
            $account = $client->api->v2010->accounts($accountSid)->fetch();
            $this->info("✓ Connected to account: {$account->friendlyName}");
        } catch (\Exception $e) {
            $this->error('✗ Failed to verify credentials: ' . $e->getMessage());
            return Command::FAILURE;
        }

        // API Key for token generation
        $apiKeySid = $this->option('api-key');
        $apiKeySecret = $this->option('api-secret');
        
        if (!$apiKeySid || !$apiKeySecret) {
            $this->newLine();
            $this->info('Creating API Key for token generation...');
            
            try {
                $apiKey = $client->newKeys->create([
                    'friendlyName' => 'Teleman AI WebRTC Key - ' . now()->format('Y-m-d H:i:s'),
                ]);
                
                $apiKeySid = $apiKey->sid;
                $apiKeySecret = $apiKey->secret;
                
                $this->info("✓ API Key created: {$apiKeySid}");
                $this->warn("⚠ API Secret (SAVE THIS - won't be shown again): {$apiKeySecret}");
            } catch (\Exception $e) {
                $this->error('✗ Failed to create API Key: ' . $e->getMessage());
                $this->warn('You can create an API Key manually at: https://console.twilio.com/us1/develop/api-keys');
                return Command::FAILURE;
            }
        }

        // TwiML Application
        $twimlAppSid = $this->option('twiml-app');
        
        if (!$twimlAppSid) {
            $this->newLine();
            $webhookUrl = $this->option('webhook-url') ?? $this->ask('Enter your webhook base URL (e.g., https://your-domain.com)');
            
            $this->info('Creating TwiML Application...');
            
            try {
                $voiceUrl = rtrim($webhookUrl, '/') . '/twiml/manual-call';
                $statusCallback = rtrim($webhookUrl, '/') . '/webhooks/twilio/call-status';
                
                $application = $client->applications->create([
                    'voiceUrl' => $voiceUrl,
                    'voiceMethod' => 'POST',
                    'statusCallback' => $statusCallback,
                    'statusCallbackMethod' => 'POST',
                    'friendlyName' => 'Teleman AI WebRTC - ' . now()->format('Y-m-d H:i:s'),
                ]);
                
                $twimlAppSid = $application->sid;
                $this->info("✓ TwiML App created: {$twimlAppSid}");
            } catch (\Exception $e) {
                $this->error('✗ Failed to create TwiML App: ' . $e->getMessage());
                $this->warn('You can create a TwiML App manually at: https://console.twilio.com/us1/develop/voice/manage/twiml-apps');
                return Command::FAILURE;
            }
        } else {
            $webhookUrl = $this->option('webhook-url') ?? $this->ask('Enter your webhook base URL (e.g., https://your-domain.com)');
        }

        // Save configuration
        $this->newLine();
        $this->info('Saving configuration...');
        
        // Deactivate all existing configs
        TwilioGlobalConfig::query()->update(['is_active' => false]);
        
        // Create new config
        $config = TwilioGlobalConfig::create([
            'account_sid' => $accountSid,
            'auth_token' => $authToken,
            'api_key_sid' => $apiKeySid,
            'api_key_secret' => $apiKeySecret,
            'twiml_app_sid' => $twimlAppSid,
            'webhook_url' => rtrim($webhookUrl ?? '', '/'),
            'is_active' => true,
            'verified_at' => now(),
        ]);

        $this->newLine();
        $this->info('✅ Twilio configuration saved successfully!');
        $this->newLine();
        $this->table(
            ['Setting', 'Value'],
            [
                ['Account SID', $config->account_sid],
                ['API Key SID', $config->api_key_sid],
                ['TwiML App SID', $config->twiml_app_sid],
                ['Webhook URL', $config->webhook_url],
                ['Status', 'Active'],
            ]
        );

        // Configure phone numbers automatically
        $this->newLine();
        if ($this->confirm('Do you want to configure your Twilio phone numbers now?', true)) {
            $this->info('Configuring phone numbers with TwiML webhooks...');
            $this->newLine();
            
            try {
                $numbers = $client->incomingPhoneNumbers->read();
                
                if (empty($numbers)) {
                    $this->warn('No phone numbers found in your Twilio account.');
                } else {
                    $this->info('Found ' . count($numbers) . ' phone number(s)');
                    $this->newLine();
                    
                    $voiceUrl = rtrim($webhookUrl ?? '', '/') . '/twiml/manual-call';
                    $statusCallbackUrl = rtrim($webhookUrl ?? '', '/') . '/webhooks/twilio/call-status';
                    
                    $configured = 0;
                    
                    foreach ($numbers as $number) {
                        $this->line("📞 Configuring {$number->phoneNumber}...");
                        
                        try {
                            $client->incomingPhoneNumbers($number->sid)
                                ->update([
                                    'voiceApplicationSid' => $twimlAppSid,
                                ]);
                            
                            $this->info("   ✓ Configured successfully");
                            $configured++;
                        } catch (\Exception $e) {
                            $this->error("   ✗ Failed: " . $e->getMessage());
                        }
                    }
                    
                    $this->newLine();
                    $this->info("✅ Configured {$configured} phone number(s) successfully!");
                }
            } catch (\Exception $e) {
                $this->error('Failed to configure phone numbers: ' . $e->getMessage());
                $this->warn('You can configure them later by running: php artisan twilio:configure-phones --all');
            }
        }

        $this->newLine();
        $this->info('Next steps:');
        $this->line('1. Go to Settings > Twilio in your admin panel');
        $this->line('2. Test the softphone dialer');
        $this->line('3. Try making inbound/outbound calls');
        
        if (!$this->confirm('Do you want to configure your Twilio phone numbers now?', true)) {
            $this->newLine();
            $this->warn('Remember to configure phone numbers later:');
            $this->line('   php artisan twilio:configure-phones --all');
        }

        return Command::SUCCESS;
    }
}
