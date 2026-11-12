<?php

namespace App\Console\Commands;

use App\Services\PhoneNumberService;
use Illuminate\Console\Command;

class TestTwilioSync extends Command
{
    protected $signature = 'twilio:test-sync {country=US} {--area-code=} {--limit=5}';
    protected $description = 'Test Twilio number sync functionality';

    public function handle(PhoneNumberService $service)
    {
        $this->info('🔍 Testing Twilio Number Sync...');
        $this->newLine();

        try {
            $country = $this->argument('country');
            $areaCode = $this->option('area-code');
            $limit = $this->option('limit');

            $this->info("Country: {$country}");
            if ($areaCode) {
                $this->info("Area Code: {$areaCode}");
            }
            $this->info("Limit: {$limit}");
            $this->newLine();

            $this->line('Fetching available numbers from Twilio...');
            
            $numbers = $service->syncAvailableNumbers($country, $areaCode, $limit);

            $this->newLine();
            $this->info("✅ Successfully synced " . count($numbers) . " number(s)");
            $this->newLine();

            if (count($numbers) > 0) {
                $this->table(
                    ['Phone Number', 'Area Code', 'Voice', 'SMS'],
                    collect($numbers)->map(function ($num) {
                        return [
                            $num->number,
                            $num->area_code,
                            $num->capabilities['voice'] ? '✓' : '✗',
                            $num->capabilities['sms'] ? '✓' : '✗',
                        ];
                    })->toArray()
                );
            } else {
                $this->warn('No numbers found. Try a different area code or country.');
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Sync failed: ' . $e->getMessage());
            return 1;
        }
    }
}
