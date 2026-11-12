<?php

namespace App\Events\CRM;

use App\Models\Campaign;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CampaignStartedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Campaign $campaign
    ) {
    }

    /**
     * Get the event data for webhooks
     */
    public function toArray(): array
    {
        return [
            'event' => 'campaign_started',
            'timestamp' => now()->toIso8601String(),
            'user_id' => $this->campaign->user_id,
            'data' => [
                'campaign' => [
                    'id' => $this->campaign->id,
                    'name' => $this->campaign->name,
                    'type' => $this->campaign->type,
                    'status' => $this->campaign->status,
                    'total_contacts' => $this->campaign->total_contacts,
                    'caller_id' => $this->campaign->caller_id,
                    'from_number' => $this->campaign->from_number,
                    'max_concurrent_calls' => $this->campaign->max_concurrent_calls,
                    'started_at' => $this->campaign->started_at?->toIso8601String(),
                ],
            ],
        ];
    }
}
