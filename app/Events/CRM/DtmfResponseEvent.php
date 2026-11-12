<?php

namespace App\Events\CRM;

use App\Models\Call;
use App\Models\Contact;
use App\Models\Campaign;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DtmfResponseEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Call $call,
        public Contact $contact,
        public Campaign $campaign,
        public string $digits
    ) {
    }

    /**
     * Get the event data for webhooks
     */
    public function toArray(): array
    {
        return [
            'event' => 'dtmf_response',
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
                ],
                'dtmf' => [
                    'digits' => $this->digits,
                    'all_digits' => $this->call->dtmf_digits,
                    'pressed_at' => now()->toIso8601String(),
                ],
                'campaign' => [
                    'id' => $this->campaign->id,
                    'name' => $this->campaign->name,
                    'dtmf_actions' => $this->campaign->dtmf_actions,
                ],
            ],
        ];
    }
}
