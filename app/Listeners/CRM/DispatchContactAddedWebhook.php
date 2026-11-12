<?php

namespace App\Listeners\CRM;

use App\Events\CRM\ContactAddedEvent;
use App\Services\CrmWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;

class DispatchContactAddedWebhook implements ShouldQueue
{
    public function __construct(
        private CrmWebhookService $webhookService
    ) {
    }

    public function handle(ContactAddedEvent $event): void
    {
        $this->webhookService->dispatch(
            'contact_added',
            $event->toArray(),
            $event->contact->user_id
        );
    }
}
