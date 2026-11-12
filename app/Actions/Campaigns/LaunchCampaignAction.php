<?php

declare(strict_types=1);

namespace App\Actions\Campaigns;

use App\Events\CRM\CampaignStartedEvent;
use App\Jobs\ProcessCampaignJob;
use App\Models\Campaign;
use Illuminate\Support\Facades\DB;

class LaunchCampaignAction
{
    /**
     * Launch a campaign
     */
    public function execute(Campaign $campaign): Campaign
    {
        return DB::transaction(function () use ($campaign) {
            // Validate campaign can be launched
            if ($campaign->status !== 'draft' && $campaign->status !== 'paused') {
                throw new \Exception('Campaign must be in draft or paused status to launch');
            }

            // Check if campaign has contacts
            if ($campaign->campaignContacts()->count() === 0) {
                throw new \Exception('Campaign must have at least one contact to launch');
            }

            // Check if campaign has message or audio file.
            //
            // An AI agent speaks for itself, so neither is used when one is
            // attached: MakeCampaignCallJob sends the call to the agent's
            // endpoint and never reads the campaign's type, message or audio
            // file. Requiring them anyway made an agent campaign demand a
            // message that would never be played before it could launch.
            if (! $campaign->ai_agent_id) {
                if ($campaign->type === 'text_to_speech' && empty($campaign->message)) {
                    throw new \Exception('Text-to-speech campaign must have a message');
                }

                if ($campaign->type === 'voice_to_voice' && ! $campaign->audio_file_id) {
                    throw new \Exception('Voice-to-voice campaign must have an audio file');
                }
            }

            // Validate campaign variables - check for empty values
            if ($campaign->message && $campaign->campaign_variables) {
                // Extract all variables used in the message
                preg_match_all('/\{\{(\w+)\}\}/', $campaign->message, $matches);
                $usedVariables = $matches[1] ?? [];

                // Check if any used variable is a campaign variable with empty value
                foreach ($usedVariables as $varName) {
                    if (array_key_exists($varName, $campaign->campaign_variables)) {
                        $value = $campaign->campaign_variables[$varName] ?? '';
                        if (empty($value)) {
                            throw new \Exception("Campaign variable '{{$varName}}' is used in the message but has no value. Please provide a value before launching.");
                        }
                    }
                }
            }

            // Update campaign status
            $campaign->update([
                'status' => 'running',
                'started_at' => now(),
            ]);

            // Dispatch CRM event
            event(new CampaignStartedEvent($campaign));

            // Dispatch queue job to process campaign
            ProcessCampaignJob::dispatch($campaign);

            return $campaign->fresh();
        });
    }
}
