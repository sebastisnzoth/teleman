<?php

namespace App\Events\CRM;

use App\Models\Call;
use App\Models\Contact;
use App\Models\Campaign;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LeadQualifiedEvent
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
            'event' => 'lead_qualified',
            'timestamp' => now()->toIso8601String(),
            'user_id' => $this->call->user_id,
            'data' => [
                'contact' => [
                    'id' => $this->contact->id,
                    'phone' => $this->contact->phone_number,
                    'first_name' => $this->contact->first_name,
                    'last_name' => $this->contact->last_name,
                    'email' => $this->contact->email,
                    'company' => $this->contact->company,
                    'job_title' => $this->contact->job_title,
                    'engagement_score' => $this->contact->engagement_score,
                ],
                'qualification' => [
                    'lead_score' => $this->call->lead_score,
                    'lead_quality' => $this->call->lead_quality,
                    'sentiment' => $this->call->sentiment,
                    'sentiment_confidence' => $this->call->sentiment_confidence,
                    'ai_summary' => $this->call->ai_summary,
                    'key_intents' => $this->call->key_intents,
                    'reason' => $this->getQualificationReason(),
                    'recommended_action' => $this->getRecommendedAction(),
                ],
                'call' => [
                    'id' => $this->call->id,
                    'duration_seconds' => $this->call->duration_seconds,
                    'transcript' => $this->call->transcript_text,
                    'recording_url' => $this->call->recording_url,
                ],
                'campaign' => [
                    'id' => $this->campaign->id,
                    'name' => $this->campaign->name,
                ],
            ],
        ];
    }

    /**
     * Get the reason for lead qualification
     */
    private function getQualificationReason(): string
    {
        $reasons = [];

        if ($this->call->sentiment === 'positive') {
            $reasons[] = 'Positive sentiment detected';
        }

        if ($this->call->lead_score >= 8) {
            $reasons[] = 'High lead score (' . $this->call->lead_score . '/10)';
        }

        if ($this->call->lead_quality === 'hot') {
            $reasons[] = 'Hot lead classification';
        }

        if (!empty($this->call->key_intents)) {
            $reasons[] = 'Strong buying signals detected';
        }

        return implode(', ', $reasons) ?: 'Qualified based on AI analysis';
    }

    /**
     * Get recommended action
     */
    private function getRecommendedAction(): string
    {
        if ($this->call->lead_quality === 'hot') {
            return 'immediate_followup';
        }

        if ($this->call->lead_score >= 8) {
            return 'priority_followup';
        }

        return 'standard_followup';
    }
}
