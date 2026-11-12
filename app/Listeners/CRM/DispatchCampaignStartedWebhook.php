<?php

namespace App\Listeners\CRM;

use App\Events\CRM\CampaignStartedEvent;
use App\Services\CrmWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;

class DispatchCampaignStartedWebhook implements ShouldQueue
{
    public function __construct(
        private CrmWebhookService $webhookService
    ) {
    }

    public function handle(CampaignStartedEvent $event): void
    {
        $this->webhookService->dispatch(
            'campaign_started',
            $event->toArray(),
            $event->campaign->user_id
        );
    }
}
