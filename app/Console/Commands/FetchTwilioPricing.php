<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Pricing\ImportPricingRulesAction;
use App\Enums\TelephonyProvider;
use App\Services\Telephony\PricingProviderFactory;
use App\Services\Telephony\TelephonyManager;
use Illuminate\Console\Command;

/**
 * Populates pricing_rules from the price list of whichever telephony
 * provider this installation is locked to.
 *
 * Its name is `pricing:fetch`, and `twilio:fetch-pricing` stays registered
 * as an alias, permanently. Every installation that predates Plivo support
 * runs Twilio, and the old name is in their crontabs, their scheduler
 * entries and their own notes — the admin Pricing page has shown it for as
 * long as the command has existed. An alias is not a copy: it is this
 * command, with the same argument, options, output and exit code, so every
 * one of those keeps working with nothing to edit. The provider-neutral
 * name is for Plivo installations and for anything written from now on.
 *
 * The class keeps its name for the same reason: a scheduler entry written
 * as Schedule::command(FetchTwilioPricing::class) names the class, not the
 * command.
 *
 * Which provider and whose account is PricingProviderFactory's business;
 * what is written is ImportPricingRulesAction's. PricingProviderTest pins
 * the Twilio behaviour — every line of output included — against the
 * command as it was before it became provider-aware.
 */
class FetchTwilioPricing extends Command
{
    protected $signature = 'pricing:fetch
                            {service_type : The service type (call, sms, phone_number, or all)}
                            {--markup-type=percentage : The markup type (percentage, fixed, hybrid)}
                            {--markup-value=25 : The markup value}
                            {--markup-fixed=0 : The fixed markup amount for hybrid type}';

    /**
     * @var array<int, string>
     */
    protected $aliases = ['twilio:fetch-pricing'];

    protected $description = 'Fetch pricing from the telephony provider for all countries';

    public function handle(TelephonyManager $telephony, ImportPricingRulesAction $importPricingRules): int
    {
        // Resolved before anything is printed, so a missing account fails
        // the run up front, as it always has.
        $pricingProvider = PricingProviderFactory::forInstallation($telephony);
        $providerLabel = TelephonyProvider::from($pricingProvider->getName())->label();

        $serviceType = $this->argument('service_type');
        $markupType = $this->option('markup-type');
        $markupValue = (float) $this->option('markup-value');
        $markupFixed = (float) $this->option('markup-fixed');

        $serviceTypes = $serviceType === 'all'
            ? ['call', 'sms', 'phone_number']
            : [$serviceType];

        foreach ($serviceTypes as $type) {
            $this->info("Fetching {$type} pricing from {$providerLabel}...");

            try {
                $allPricing = [];
                $nextPage = null;
                $pageCount = 0;

                do {
                    $result = $pricingProvider->fetchPricing($type, $nextPage);

                    $allPricing = array_merge($allPricing, $result['pricing']);
                    $nextPage = $result['next_page'];
                    $pageCount++;

                    $this->info("  Fetched page {$pageCount} - ".count($result['pricing']).' countries');

                    if ($nextPage) {
                        // Twilio's next page is a URL; show just its token
                        // for readability. Other providers' cursors carry
                        // none, and show nothing here.
                        if (preg_match('/PageToken=([^&]+)/', $nextPage, $matches)) {
                            $this->comment('  Next: PageToken='.$matches[1]);
                        }
                    } else {
                        $this->comment('  No more pages - stopping');
                    }

                } while ($nextPage);

                $this->info('Total countries fetched: '.count($allPricing));
                $this->info('Processing pricing rules...');

                ['created' => $created, 'updated' => $updated] = $importPricingRules->execute(
                    $type,
                    $allPricing,
                    $markupType,
                    $markupValue,
                    $markupFixed,
                );

                $this->info("✅ {$type} pricing completed:");
                $this->info("   Created: {$created}");
                $this->info("   Updated: {$updated}");
                $this->newLine();

            } catch (\Exception $e) {
                $this->error("❌ Error fetching {$type} pricing: ".$e->getMessage());
            }
        }

        $this->info('🎉 All pricing fetched successfully!');

        return Command::SUCCESS;
    }
}
