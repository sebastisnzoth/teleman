<?php

namespace App\Console\Commands;

use App\Models\TwilioCredential;
use App\Services\TwilioService;
use Illuminate\Console\Command;

class UpdateTwimlAppMethod extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'twilio:update-twiml-method';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update TwiML app to use POST method instead of GET';

    /**
     * Execute the console command.
     */
    public function handle(TwilioService $twilioService)
    {
        $this->info('Updating TwiML apps to use POST method...');

        $credentials = TwilioCredential::whereNotNull('twiml_app_sid')
            ->where('is_active', true)
            ->get();

        if ($credentials->isEmpty()) {
            $this->warn('No active Twilio credentials with TwiML apps found.');
            return 0;
        }

        foreach ($credentials as $credential) {
            try {
                $this->info("Processing credential for user {$credential->user_id}...");
                
                $twilioService->initialize($credential);
                $client = $twilioService->getClient();

                $webhookUrl = env('TWILIO_WEBHOOK_URL', config('app.url'));
                $voiceUrl = $webhookUrl . '/twiml/manual-call';

                $client->applications($credential->twiml_app_sid)->update([
                    'voiceUrl' => $voiceUrl,
                    'voiceMethod' => 'POST',
                ]);

                $this->info("✓ Updated TwiML app {$credential->twiml_app_sid} to POST method");
            } catch (\Exception $e) {
                $this->error("✗ Failed to update TwiML app for user {$credential->user_id}: " . $e->getMessage());
            }
        }

        $this->info('TwiML app updates completed!');
        return 0;
    }
}
