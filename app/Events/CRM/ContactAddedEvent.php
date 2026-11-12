<?php

namespace App\Events\CRM;

use App\Models\Contact;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ContactAddedEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Contact $contact,
        public string $source = 'manual'
    ) {
    }

    /**
     * Get the event data for webhooks
     */
    public function toArray(): array
    {
        return [
            'event' => 'contact_added',
            'timestamp' => now()->toIso8601String(),
            'user_id' => $this->contact->user_id,
            'data' => [
                'contact' => [
                    'id' => $this->contact->id,
                    'phone' => $this->contact->phone_number,
                    'first_name' => $this->contact->first_name,
                    'last_name' => $this->contact->last_name,
                    'email' => $this->contact->email,
                    'company' => $this->contact->company,
                    'job_title' => $this->contact->job_title,
                    'notes' => $this->contact->notes,
                    'timezone' => $this->contact->timezone,
                    'language' => $this->contact->language,
                    'custom_fields' => $this->contact->custom_fields,
                    'status' => $this->contact->status,
                    'source' => $this->contact->source ?? $this->source,
                    'created_at' => $this->contact->created_at->toIso8601String(),
                ],
            ],
        ];
    }
}
