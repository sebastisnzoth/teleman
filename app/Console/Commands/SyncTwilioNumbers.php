<?php

namespace App\Console\Commands;

use App\Services\PhoneNumberService;
use Illuminate\Console\Command;

class SyncTwilioNumbers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'twilio:sync-numbers 
                            {--country=US : Country code to sync numbers from}
                            {--area-code= : Optional area code to filter}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync available phone numbers from Twilio';

    protected $phoneNumberService;

    /**
     * Create a new command instance.
     */
    public function __construct(PhoneNumberService $phoneNumberService)
    {
        parent::__construct();
        $this->phoneNumberService = $phoneNumberService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $countryCode = $this->option('country');
        $areaCode = $this->option('area-code');

        $this->info("Syncing available phone numbers from Twilio...");
        $this->info("Country: {$countryCode}" . ($areaCode ? " | Area Code: {$areaCode}" : ""));

        try {
            $numbers = $this->phoneNumberService->syncAvailableNumbers($countryCode, $areaCode);

            $this->info("✓ Successfully synced " . count($numbers) . " phone numbers from Twilio");

            if (count($numbers) > 0) {
                $this->table(
                    ['Number', 'Country', 'Area Code', 'Capabilities'],
                    collect($numbers)->map(function ($number) {
                        return [
                            $number->number,
                            $number->country_code,
                            $number->area_code,
                            ($number->hasVoiceCapability() ? 'Voice ' : '') . 
                            ($number->hasSmsCapability() ? 'SMS' : ''),
                        ];
                    })->toArray()
                );
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to sync numbers: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
