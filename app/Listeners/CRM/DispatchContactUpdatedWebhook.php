<?php

namespace App\Listeners\CRM;

use App\Events\CRM\ContactUpdatedEvent;
use App\Services\CrmWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;

class DispatchContactUpdatedWebhook implements ShouldQueue
{
    public function __construct(
        private CrmWebhookService $webhookService
    ) {
    }

    public function handle(ContactUpdatedEvent $event): void
    {
        $this->webhookService->dispatch(
            'contact_updated',
            $event->toArray(),
            $event->contact->user_id
        );
    }
}
