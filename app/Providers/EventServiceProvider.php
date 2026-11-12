<?php

namespace App\Providers;

use App\Events\CRM\CallCompletedEvent;
use App\Events\CRM\LeadQualifiedEvent;
use App\Events\CRM\ContactAddedEvent;
use App\Events\CRM\ContactUpdatedEvent;
use App\Events\CRM\CampaignStartedEvent;
use App\Events\CRM\DtmfResponseEvent;
use App\Listeners\CRM\DispatchCallCompletedWebhook;
use App\Listeners\CRM\DispatchLeadQualifiedWebhook;
use App\Listeners\CRM\DispatchContactAddedWebhook;
use App\Listeners\CRM\DispatchContactUpdatedWebhook;
use App\Listeners\CRM\DispatchCampaignStartedWebhook;
use App\Listeners\CRM\DispatchDtmfResponseWebhook;
use App\Services\CronMonitorService;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        CallCompletedEvent::class => [
            DispatchCallCompletedWebhook::class,
        ],
        LeadQualifiedEvent::class => [
            DispatchLeadQualifiedWebhook::class,
        ],
        ContactAddedEvent::class => [
            DispatchContactAddedWebhook::class,
        ],
        ContactUpdatedEvent::class => [
            DispatchContactUpdatedWebhook::class,
        ],
        CampaignStartedEvent::class => [
            DispatchCampaignStartedWebhook::class,
        ],
        DtmfResponseEvent::class => [
            DispatchDtmfResponseWebhook::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        Event::listen(Looping::class, function (Looping $event): void {
            app(CronMonitorService::class)->recordQueueHeartbeat($event->connectionName, $event->queue);
        });

        Event::listen(JobProcessing::class, function (JobProcessing $event): void {
            app(CronMonitorService::class)->recordQueueJobStarted($event->job);
        });

        Event::listen(JobProcessed::class, function (JobProcessed $event): void {
            app(CronMonitorService::class)->recordQueueJobFinished($event->job, true);
        });

        Event::listen(JobFailed::class, function (JobFailed $event): void {
            app(CronMonitorService::class)->recordQueueJobFinished(
                $event->job,
                false,
                $event->exception->getMessage()
            );
        });
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
