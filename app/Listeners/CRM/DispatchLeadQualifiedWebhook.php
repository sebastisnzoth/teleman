<?php

namespace App\Listeners\CRM;

use App\Events\CRM\LeadQualifiedEvent;
use App\Services\CrmWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;

class DispatchLeadQualifiedWebhook implements ShouldQueue
{
    public function __construct(
        private CrmWebhookService $webhookService
    ) {
    }

    public function handle(LeadQualifiedEvent $event): void
    {
        $this->webhookService->dispatch(
            'lead_qualified',
            $event->toArray(),
            $event->call->user_id
        );
    }
}
