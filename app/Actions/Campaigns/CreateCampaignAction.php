<?php

declare(strict_types=1);

namespace App\Actions\Campaigns;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateCampaignAction
{
    /**
     * Create a new campaign
     */
    public function execute(User $user, array $data): Campaign
    {
        return DB::transaction(function () use ($user, $data) {
            // Determine initial status based on launch type
            $status = 'draft';
            $scheduledAt = null;

            if (isset($data['launch_type'])) {
                if ($data['launch_type'] === 'scheduled') {
                    $status = 'scheduled';
                    $scheduledAt = $data['scheduled_at'] ?? null;
                } elseif ($data['launch_type'] === 'instant') {
                    $status = 'running';
                }
            }

            $campaign = Campaign::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'type' => $data['type'], // 'text_to_speech' or 'voice_to_voice'
                'status' => $status,
                'message' => $data['message'] ?? null,
                'expected_variables' => $data['expected_variables'] ?? [],
                'campaign_variables' => $data['campaign_variables'] ?? [],
                'phone_number_id' => $data['phone_number_id'] ?? null,
                'ai_agent_id' => $data['ai_agent_id'] ?? null,
                'assigned_agent_id' => $data['assigned_agent_id'] ?? null,
                'audio_file_id' => $data['audio_file_id'] ?? null,
                'language' => $data['language'] ?? 'en-US',
                'voice' => $data['voice'] ?? 'Polly.Joanna',
                'from_number' => $data['from_number'] ?? $user->getActiveTwilioCredential()?->phone_number,
                'enable_recording' => $data['enable_recording'] ?? false,
                'enable_dtmf' => $data['enable_dtmf'] ?? false,
                'max_concurrent_calls' => $data['max_concurrent_calls'] ?? 5,
                'retry_attempts' => $data['retry_attempts'] ?? 3,
                'retry_delay_minutes' => $data['retry_delay_minutes'] ?? 5,
                'scheduled_at' => $scheduledAt,
                'started_at' => $status === 'running' ? now() : null,
            ]);

            return $campaign;
        });
    }
}
