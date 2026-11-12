<?php

namespace App\Listeners\CRM;

use App\Events\CRM\DtmfResponseEvent;
use App\Services\CrmWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;

class DispatchDtmfResponseWebhook implements ShouldQueue
{
    public function __construct(
        private CrmWebhookService $webhookService
    ) {
    }

    public function handle(DtmfResponseEvent $event): void
    {
        $this->webhookService->dispatch(
            'dtmf_response',
            $event->toArray(),
            $event->call->user_id
        );
    }
}
