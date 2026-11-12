<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AiAgent;
use App\Models\AiAgentCall;
use App\Models\Call;
use App\Models\Campaign;
use App\Models\PhoneNumber;
use App\Models\SmsPhoneNumber;
use App\Models\TwilioAccountMigration;
use App\Models\TwilioGlobalConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client as TwilioClient;

class TwilioAccountMigrationService
{
    /**
     * Check if the new credentials represent a different Twilio account.
     */
    public function isAccountChange(string $newAccountSid): bool
    {
        $currentConfig = TwilioGlobalConfig::active();

        if (!$currentConfig) {
            return false; // First-time setup, no migration needed
        }

        return $currentConfig->account_sid !== $newAccountSid;
    }

    /**
     * Execute the full account migration within a transaction.
     */
    public function migrate(
        string $oldAccountSid,
        string $newAccountSid,
        string $newAuthToken,
        int $performedById
    ): TwilioAccountMigration {
        return DB::transaction(function () use ($oldAccountSid, $newAccountSid, $newAuthToken, $performedById) {
            $client = new TwilioClient($newAccountSid, $newAuthToken);

            // 1. Fetch phone numbers from the new Twilio account
            $newAccountNumbers = $this->fetchNewAccountNumbers($client);

            Log::info('Twilio account migration: fetched new account numbers', [
                'old_sid' => $oldAccountSid,
                'new_sid' => $newAccountSid,
                'new_numbers_count' => count($newAccountNumbers),
            ]);

            // 2. Migrate phone numbers (match, deactivate, discover new)
            $phoneResult = $this->migratePhoneNumbers($oldAccountSid, $newAccountSid, $newAccountNumbers);

            // 3. Pause campaigns using deactivated numbers
            $pausedCampaigns = $this->pauseAffectedCampaigns($phoneResult['deactivated_ids']);

            // 4. Reset AI agents with deactivated numbers
            $aiAgentsReset = $this->resetAiAgents($phoneResult['deactivated_numbers']);

            // 5. Mark all existing calls as legacy
            $legacyCalls = $this->markLegacyCalls($oldAccountSid);

            // 6. Deactivate SMS phone numbers
            $smsDeactivated = $this->deactivateSmsPhoneNumbers($oldAccountSid);

            // 7. Create audit record
            $migration = TwilioAccountMigration::create([
                'old_account_sid' => $oldAccountSid,
                'new_account_sid' => $newAccountSid,
                'performed_by' => $performedById,
                'numbers_matched' => count($phoneResult['matched_ids']),
                'numbers_deactivated' => count($phoneResult['deactivated_ids']),
                'numbers_new' => count($phoneResult['new_ids']),
                'campaigns_paused' => count($pausedCampaigns),
                'ai_agents_reset' => $aiAgentsReset,
                'calls_marked_legacy' => $legacyCalls,
                'sms_numbers_deactivated' => $smsDeactivated,
                'details' => [
                    'matched_numbers' => $phoneResult['matched_ids'],
                    'deactivated_numbers' => $phoneResult['deactivated_ids'],
                    'new_numbers' => $phoneResult['new_ids'],
                    'paused_campaigns' => $pausedCampaigns,
                ],
            ]);

            Log::info('Twilio account migration completed', [
                'migration_id' => $migration->id,
                'numbers_matched' => $migration->numbers_matched,
                'numbers_deactivated' => $migration->numbers_deactivated,
                'numbers_new' => $migration->numbers_new,
                'campaigns_paused' => $migration->campaigns_paused,
                'ai_agents_reset' => $migration->ai_agents_reset,
                'calls_marked_legacy' => $migration->calls_marked_legacy,
            ]);

            return $migration;
        });
    }

    /**
     * Fetch all incoming phone numbers from the new Twilio account.
     *
     * @return array<string, string> Keyed by E.164 number string => Twilio SID
     */
    protected function fetchNewAccountNumbers(TwilioClient $client): array
    {
        $numbers = [];

        $incomingNumbers = $client->incomingPhoneNumbers->read();

        foreach ($incomingNumbers as $number) {
            $numbers[$number->phoneNumber] = $number->sid;
        }

        return $numbers;
    }

    /**
     * Migrate phone numbers: match by number string, deactivate unmatched, discover new.
     *
     * @return array{matched_ids: int[], deactivated_ids: int[], deactivated_numbers: string[], new_ids: int[]}
     */
    protected function migratePhoneNumbers(string $oldAccountSid, string $newAccountSid, array $newAccountNumbers): array
    {
        $matchedIds = [];
        $deactivatedIds = [];
        $deactivatedNumbers = [];
        $newIds = [];

        // Get all direct Twilio phone numbers (skip SIP trunk numbers)
        $existingNumbers = PhoneNumber::where('source', 'twilio_direct')
            ->orWhereNull('source')
            ->get();

        $existingNumberStrings = [];

        foreach ($existingNumbers as $phoneNumber) {
            $existingNumberStrings[] = $phoneNumber->number;

            if (isset($newAccountNumbers[$phoneNumber->number])) {
                // MATCHED: Number exists in both old and new accounts
                $phoneNumber->update([
                    'twilio_sid' => $newAccountNumbers[$phoneNumber->number],
                    'account_sid' => $newAccountSid,
                ]);
                $matchedIds[] = $phoneNumber->id;

                Log::info('Phone number matched in new account', [
                    'number' => $phoneNumber->number,
                    'old_sid' => $phoneNumber->getOriginal('twilio_sid'),
                    'new_sid' => $newAccountNumbers[$phoneNumber->number],
                ]);
            } else {
                // UNMATCHED: Number only exists in old account — deactivate
                $deactivatedNumbers[] = $phoneNumber->number;
                $phoneNumber->update([
                    'status' => 'released',
                    'twilio_sid' => null,
                    'account_sid' => null,
                    'user_id' => null,
                    'released_at' => now(),
                ]);
                $deactivatedIds[] = $phoneNumber->id;

                Log::info('Phone number deactivated (not in new account)', [
                    'number' => $phoneNumber->number,
                    'phone_number_id' => $phoneNumber->id,
                ]);
            }
        }

        // Discover NEW numbers (in new account but not in our DB)
        foreach ($newAccountNumbers as $numberString => $twilioSid) {
            if (!in_array($numberString, $existingNumberStrings)) {
                $newPhone = PhoneNumber::create([
                    'number' => $numberString,
                    'friendly_name' => $numberString,
                    'status' => 'available',
                    'source' => 'twilio_direct',
                    'twilio_sid' => $twilioSid,
                    'account_sid' => $newAccountSid,
                    'capabilities' => ['voice' => true, 'sms' => true, 'mms' => false],
                ]);
                $newIds[] = $newPhone->id;

                Log::info('New phone number discovered from new account', [
                    'number' => $numberString,
                    'twilio_sid' => $twilioSid,
                ]);
            }
        }

        return [
            'matched_ids' => $matchedIds,
            'deactivated_ids' => $deactivatedIds,
            'deactivated_numbers' => $deactivatedNumbers,
            'new_ids' => $newIds,
        ];
    }

    /**
     * Pause running/scheduled campaigns that use deactivated phone numbers.
     *
     * @param int[] $deactivatedPhoneNumberIds
     * @return array<array{id: int, name: string, old_status: string}>
     */
    protected function pauseAffectedCampaigns(array $deactivatedPhoneNumberIds): array
    {
        if (empty($deactivatedPhoneNumberIds)) {
            return [];
        }

        $affectedCampaigns = Campaign::whereIn('phone_number_id', $deactivatedPhoneNumberIds)
            ->whereIn('status', ['running', 'scheduled'])
            ->get();

        $result = [];

        foreach ($affectedCampaigns as $campaign) {
            $oldStatus = $campaign->status;
            $newStatus = $oldStatus === 'running' ? 'paused' : 'draft';

            try {
                $campaign->transitionTo($newStatus);
                $result[] = [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'old_status' => $oldStatus,
                ];

                Log::info('Campaign paused due to account migration', [
                    'campaign_id' => $campaign->id,
                    'campaign_name' => $campaign->name,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ]);
            } catch (\InvalidArgumentException $e) {
                Log::warning('Failed to transition campaign during migration', [
                    'campaign_id' => $campaign->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $result;
    }

    /**
     * Reset AI agents that use deactivated phone numbers.
     *
     * @param string[] $deactivatedNumberStrings
     */
    protected function resetAiAgents(array $deactivatedNumberStrings): int
    {
        if (empty($deactivatedNumberStrings)) {
            return 0;
        }

        // Reset AI agents whose phone_number field matches deactivated numbers
        $count = AiAgent::whereIn('phone_number', $deactivatedNumberStrings)
            ->update([
                'twilio_auto_configured' => false,
                'twilio_webhook_sid' => null,
            ]);

        // Clear ai_agent_id on deactivated phone numbers
        PhoneNumber::whereIn('number', $deactivatedNumberStrings)
            ->whereNotNull('ai_agent_id')
            ->update(['ai_agent_id' => null]);

        return $count;
    }

    /**
     * Mark all existing calls as legacy so they won't be synced against the new account.
     */
    protected function markLegacyCalls(string $oldAccountSid): int
    {
        $callsMarked = Call::whereNull('legacy_account_sid')
            ->whereNotNull('twilio_call_sid')
            ->update(['legacy_account_sid' => $oldAccountSid]);

        $aiCallsMarked = AiAgentCall::whereNull('legacy_account_sid')
            ->whereNotNull('call_sid')
            ->update(['legacy_account_sid' => $oldAccountSid]);

        return $callsMarked + $aiCallsMarked;
    }

    /**
     * Deactivate SMS phone numbers tied to the old Twilio account.
     */
    protected function deactivateSmsPhoneNumbers(string $oldAccountSid): int
    {
        return SmsPhoneNumber::where('provider', 'twilio')
            ->where(function ($query) use ($oldAccountSid) {
                $query->whereNull('account_sid')
                    ->orWhere('account_sid', $oldAccountSid);
            })
            ->update([
                'is_active' => false,
                'account_sid' => $oldAccountSid,
            ]);
    }
}
