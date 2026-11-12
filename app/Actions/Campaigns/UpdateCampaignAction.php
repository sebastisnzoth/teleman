<?php

declare(strict_types=1);

namespace App\Actions\Campaigns;

use App\Models\Campaign;
use Illuminate\Support\Facades\DB;

class UpdateCampaignAction
{
    /**
     * Update an existing campaign
     */
    public function execute(Campaign $campaign, array $data): Campaign
    {
        return DB::transaction(function () use ($campaign, $data) {
            // Only allow updates if campaign is in draft or paused status
            if (!in_array($campaign->status, ['draft', 'paused'])) {
                throw new \Exception('Cannot update campaign in ' . $campaign->status . ' status');
            }

            $campaign->update([
                'name' => $data['name'] ?? $campaign->name,
                'type' => $data['type'] ?? $campaign->type,
                'message' => $data['message'] ?? $campaign->message,
                'expected_variables' => $data['expected_variables'] ?? $campaign->expected_variables,
                'campaign_variables' => $data['campaign_variables'] ?? $campaign->campaign_variables,
                'phone_number_id' => array_key_exists('phone_number_id', $data) ? $data['phone_number_id'] : $campaign->phone_number_id,
                'ai_agent_id' => array_key_exists('ai_agent_id', $data) ? $data['ai_agent_id'] : $campaign->ai_agent_id,
                'assigned_agent_id' => array_key_exists('assigned_agent_id', $data) ? $data['assigned_agent_id'] : $campaign->assigned_agent_id,
                'audio_file_id' => $data['audio_file_id'] ?? $campaign->audio_file_id,
                'language' => $data['language'] ?? $campaign->language,
                'voice' => $data['voice'] ?? $campaign->voice,
                'from_number' => $data['from_number'] ?? $campaign->from_number,
                'enable_recording' => $data['enable_recording'] ?? $campaign->enable_recording,
                'enable_dtmf' => $data['enable_dtmf'] ?? $campaign->enable_dtmf,
                'max_concurrent_calls' => $data['max_concurrent_calls'] ?? $campaign->max_concurrent_calls,
                'retry_attempts' => $data['retry_attempts'] ?? $campaign->retry_attempts,
                'retry_delay_minutes' => $data['retry_delay_minutes'] ?? $campaign->retry_delay_minutes,
                'scheduled_at' => $data['scheduled_at'] ?? $campaign->scheduled_at,
            ]);

            return $campaign->fresh();
        });
    }
}
