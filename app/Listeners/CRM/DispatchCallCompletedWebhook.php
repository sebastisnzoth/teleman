<?php

namespace App\Listeners\CRM;

use App\Events\CRM\CallCompletedEvent;
use App\Services\CrmWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;

class DispatchCallCompletedWebhook implements ShouldQueue
{
    public function __construct(
        private CrmWebhookService $webhookService
    ) {
    }

    public function handle(CallCompletedEvent $event): void
    {
        $this->webhookService->dispatch(
            'call_completed',
            $event->toArray(),
            $event->call->user_id
        );
    }
}
