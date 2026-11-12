<?php

namespace App\Events\CRM;

use App\Models\Call;
use App\Models\Contact;
use App\Models\Campaign;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallCompletedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Call $call,
        public Contact $contact,
        public Campaign $campaign
    ) {
    }

    /**
     * Get the event data for webhooks
     */
    public function toArray(): array
    {
        return [
            'event' => 'call_completed',
            'timestamp' => now()->toIso8601String(),
            'user_id' => $this->call->user_id,
            'data' => [
                'call_id' => $this->call->id,
                'contact' => [
                    'id' => $this->contact->id,
                    'phone' => $this->contact->phone_number,
                    'first_name' => $this->contact->first_name,
                    'last_name' => $this->contact->last_name,
                    'email' => $this->contact->email,
                    'company' => $this->contact->company,
                    'job_title' => $this->contact->job_title,
                    'timezone' => $this->contact->timezone,
                    'language' => $this->contact->language,
                    'custom_fields' => $this->contact->custom_fields,
                ],
                'call' => [
                    'status' => $this->call->status,
                    'duration_seconds' => $this->call->duration_seconds,
                    'answered_by' => $this->call->answered_by,
                    'sentiment' => $this->call->sentiment,
                    'sentiment_confidence' => $this->call->sentiment_confidence,
                    'lead_score' => $this->call->lead_score,
                    'lead_quality' => $this->call->lead_quality,
                    'ai_summary' => $this->call->ai_summary,
                    'key_intents' => $this->call->key_intents,
                    'transcript' => $this->call->transcript_text,
                    'recording_url' => $this->call->recording_url,
                    'dtmf_digits' => $this->call->dtmf_digits,
                    'started_at' => $this->call->started_at?->toIso8601String(),
                    'ended_at' => $this->call->ended_at?->toIso8601String(),
                ],
                'campaign' => [
                    'id' => $this->campaign->id,
                    'name' => $this->campaign->name,
                    'type' => $this->campaign->type,
                ],
            ],
        ];
    }
}
