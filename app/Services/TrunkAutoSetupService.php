<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserSipTrunk;
use App\Models\TrunkPhoneNumber;
use App\Models\PhoneNumber;
use App\Models\TrunkHealthLog;
use Twilio\Rest\Client as TwilioClient;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * Twilio Elastic SIP Trunking - Automatic Setup Service
 *
 * BYOT (Bring Your Own Trunk) setup:
 * - User provides Twilio Account SID + Auth Token
 * - App automatically creates trunk, configures routing, imports numbers
 * - Runs as a queued job for reliable, non-blocking setup
 *
 * API References:
 * - Trunking API: https://www.twilio.com/docs/sip-trunking/api/trunks
 * - Origination: https://www.twilio.com/docs/sip-trunking/origination
 * - Termination: https://www.twilio.com/docs/sip-trunking/termination
 */
class TrunkAutoSetupService
{
    /**
     * Run the full setup on an existing trunk record.
     * Called from SetupSipTrunkJob (async queue).
     */
    public function runSetup(UserSipTrunk $trunk): void
    {
        $user = $trunk->user;

        try {
            // Step 1: Validate credentials (10%)
            $this->updateProgress($trunk, 'credentials', 10);
            $client = $this->validateCredentials(
                $trunk->twilio_account_sid,
                decrypt($trunk->twilio_auth_token)
            );
            Log::info("SIP Trunk Setup: Credentials validated", ['trunk_id' => $trunk->id]);

            // Step 2: Create SIP Trunk (30%)
            $this->updateProgress($trunk, 'creating_trunk', 30);
            $twilioTrunk = $this->createTrunk($client, $user, $trunk);
            Log::info("SIP Trunk Setup: Trunk created", ['trunk_id' => $trunk->id, 'trunk_sid' => $twilioTrunk->sid]);

            // Step 3: Configure Origination / Webhooks (50%)
            $this->updateProgress($trunk, 'configuring_origination', 50);
            $this->configureOrigination($client, $user, $trunk, $twilioTrunk);
            Log::info("SIP Trunk Setup: Origination configured", ['trunk_id' => $trunk->id]);

            // Step 4: Configure Termination — non-fatal (60%)
            $this->updateProgress($trunk, 'configuring_termination', 60);
            $this->configureTermination($client, $user, $trunk, $twilioTrunk);
            Log::info("SIP Trunk Setup: Termination configured", ['trunk_id' => $trunk->id]);

            // Step 5: Import & Assign Numbers (80%)
            $this->updateProgress($trunk, 'importing_numbers', 80);
            $numbersCount = $this->importAndAssignNumbers($client, $trunk, $twilioTrunk);
            Log::info("SIP Trunk Setup: Numbers imported", ['trunk_id' => $trunk->id, 'count' => $numbersCount]);

            // Step 6: Test Connection (90%)
            $this->updateProgress($trunk, 'testing', 90);
            $testResults = $this->testTrunkConnection($client, $trunk);
            Log::info("SIP Trunk Setup: Connection tested", ['trunk_id' => $trunk->id, 'results' => $testResults]);

            // Step 7: Complete (100%) — determine health from test results
            $healthStatus = $this->determineHealthStatus($testResults);

            $trunk->update([
                'setup_step' => 'completed',
                'setup_progress' => 100,
                'status' => 'active',
                'health_status' => $healthStatus,
                'last_health_check_at' => now(),
                'last_error' => null,
            ]);

            Log::info("SIP Trunk Setup: Completed", [
                'trunk_id' => $trunk->id,
                'health_status' => $healthStatus,
            ]);

        } catch (Exception $e) {
            Log::error("SIP Trunk Setup Failed", [
                'trunk_id' => $trunk->id,
                'user_id' => $user->id,
                'step' => $trunk->setup_step,
                'error' => $e->getMessage(),
            ]);

            // Cleanup orphaned Twilio resources on failure
            $this->cleanupFailedSetup($trunk);

            $trunk->update([
                'status' => 'error',
                'last_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Step 1: Validate Twilio credentials
     */
    private function validateCredentials(string $accountSid, string $authToken): TwilioClient
    {
        try {
            $client = new TwilioClient($accountSid, $authToken);
            $account = $client->api->v2010->accounts($accountSid)->fetch();

            if ($account->status !== 'active') {
                throw new Exception('Twilio account is not active. Status: ' . $account->status);
            }

            return $client;

        } catch (\Twilio\Exceptions\AuthenticationException $e) {
            throw new Exception('Invalid Twilio credentials. Please check your Account SID and Auth Token.');
        } catch (\Twilio\Exceptions\TwilioException $e) {
            throw new Exception('Twilio API error: ' . $e->getMessage());
        }
    }

    /**
     * Step 2: Create SIP Trunk in user's Twilio account
     */
    private function createTrunk(TwilioClient $client, User $user, UserSipTrunk $trunk): object
    {
        $domainName = $this->generateDomainName($user);

        try {
            $createParams = [
                'friendlyName' => $trunk->trunk_friendly_name,
                'domainName' => $domainName,
                'secure' => true,
                'cnamLookupEnabled' => false,
            ];

            // Add disaster recovery URL
            try {
                $createParams['disasterRecoveryUrl'] = url('/sip/trunk/disaster-recovery');
                $createParams['disasterRecoveryMethod'] = 'POST';
            } catch (\Exception $e) {
                Log::info('Disaster recovery URL not configured for trunk');
            }

            $twilioTrunk = $client->trunking->v1->trunks->create($createParams);

            $trunk->update([
                'trunk_sid' => $twilioTrunk->sid,
                'trunk_domain_name' => $twilioTrunk->domainName,
            ]);

            return $twilioTrunk;

        } catch (Exception $e) {
            throw new Exception('Failed to create SIP trunk: ' . $e->getMessage());
        }
    }

    /**
     * Step 3: Configure Origination
     *
     * For BYOT, origination is skipped — inbound calls are handled via
     * phone number voice webhooks, not trunk origination URLs.
     */
    private function configureOrigination(TwilioClient $client, User $user, UserSipTrunk $trunk, object $twilioTrunk): void
    {
        try {
            if (!$user->webhook_token) {
                $user->webhook_token = Str::random(40);
                $user->save();
            }

            $webhookUrl = route('sip.trunk.voice', ['webhook_token' => $user->webhook_token]);

            $trunk->update([
                'origination_sip_url' => $webhookUrl,
            ]);

            Log::info('SIP Trunk: Origination skipped (BYOT — inbound via number webhooks)', [
                'trunk_id' => $trunk->id,
            ]);

        } catch (Exception $e) {
            throw new Exception('Failed to configure origination: ' . $e->getMessage());
        }
    }

    /**
     * Step 4: Configure Termination
     *
     * Two modes:
     * 1. Local PBX Routing: Configure Twilio to route calls to user's local PBX
     * 2. Standard: Termination for SIP INVITE-based outbound calls from app server
     *
     * REST API calls (used by campaigns/AI agents) don't require trunk termination.
     * If it fails, setup continues.
     */
    private function configureTermination(TwilioClient $client, User $user, UserSipTrunk $trunk, object $twilioTrunk): void
    {
        try {
            if ($trunk->routes_to_local_pbx && $trunk->local_pbx_ip) {
                // NEW: Configure for local PBX routing
                $this->configureLocalPbxTermination($client, $trunk, $twilioTrunk);
            } else {
                // EXISTING: Standard termination for app-based calls
                $serverIp = config('app.server_ip');
                if ($serverIp) {
                    $this->setupIpAclTermination($client, $trunk, $twilioTrunk, $serverIp);
                } else {
                    $this->setupCredentialTermination($client, $trunk, $twilioTrunk);
                }
            }
        } catch (Exception $e) {
            // Non-fatal: termination isn't needed for REST API calls
            Log::warning("SIP Trunk: Termination config failed (non-fatal)", [
                'trunk_id' => $trunk->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Configure termination to route calls to user's local PBX
     *
     * This creates a Termination URI in Twilio pointing to the user's PBX,
     * and adds the PBX IP to an IP Access Control List so it can send calls to Twilio.
     */
    private function configureLocalPbxTermination(TwilioClient $client, UserSipTrunk $trunk, object $twilioTrunk): void
    {
        $accountSid = $client->getAccountSid();

        // 1. Get the PBX SIP URI
        $pbxUri = $trunk->getLocalPbxUri();

        if (!$pbxUri) {
            throw new Exception('Local PBX URI not configured');
        }

        // 2. Configure Termination URI in Twilio to point to user's PBX
        try {
            $terminationSettings = $client->trunking->v1
                ->trunks($twilioTrunk->sid)
                ->update([
                    'cnamLookupEnabled' => false,
                ]);

            Log::info("Local PBX termination URI set", [
                'trunk_id' => $trunk->id,
                'pbx_uri' => $pbxUri,
            ]);

        } catch (Exception $e) {
            Log::warning("Could not set termination URI: " . $e->getMessage());
        }

        // 3. Create IP Access Control List for user's PBX
        // This allows the PBX to send outbound calls TO Twilio
        $ipAcl = $client->api->v2010->accounts($accountSid)
            ->sip
            ->ipAccessControlLists
            ->create("PBX Access - User {$trunk->user_id}");

        $client->api->v2010->accounts($accountSid)
            ->sip
            ->ipAccessControlLists($ipAcl->sid)
            ->ipAddresses
            ->create(
                'User PBX Server',
                $trunk->local_pbx_ip,
                ['cidrPrefixLength' => 32]
            );

        // 4. Attach IP ACL to trunk
        $client->trunking->v1
            ->trunks($twilioTrunk->sid)
            ->ipAccessControlLists
            ->create($ipAcl->sid);

        // 5. Save configuration
        $trunk->update([
            'termination_method' => 'ip_acl',
            'ip_acl_sid' => $ipAcl->sid,
            'twilio_termination_uri' => $pbxUri,
        ]);

        Log::info("Local PBX termination configured successfully", [
            'trunk_id' => $trunk->id,
            'pbx_uri' => $pbxUri,
            'pbx_ip' => $trunk->local_pbx_ip,
            'ip_acl_sid' => $ipAcl->sid,
        ]);
    }

    /**
     * Setup IP ACL-based termination
     */
    private function setupIpAclTermination(TwilioClient $client, UserSipTrunk $trunk, object $twilioTrunk, string $serverIp): void
    {
        $accountSid = $client->getAccountSid();

        $ipAcl = $client->api->v2010->accounts($accountSid)
            ->sip
            ->ipAccessControlLists
            ->create('App Server Access - User ' . $trunk->user_id);

        $client->api->v2010->accounts($accountSid)
            ->sip
            ->ipAccessControlLists($ipAcl->sid)
            ->ipAddresses
            ->create(
                'Production Server',
                $serverIp,
                ['cidrPrefixLength' => 32]
            );

        $client->trunking->v1
            ->trunks($twilioTrunk->sid)
            ->ipAccessControlLists
            ->create($ipAcl->sid);

        $trunk->update([
            'termination_method' => 'ip_acl',
            'ip_acl_sid' => $ipAcl->sid,
        ]);
    }

    /**
     * Setup credential-based termination
     */
    private function setupCredentialTermination(TwilioClient $client, UserSipTrunk $trunk, object $twilioTrunk): void
    {
        $username = 'user' . $trunk->user_id . '_' . Str::random(8);
        $password = Str::random(32);

        $credList = $client->api->v2010
            ->accounts($client->getAccountSid())
            ->sip
            ->credentialLists
            ->create($username . '_creds');

        $client->api->v2010
            ->accounts($client->getAccountSid())
            ->sip
            ->credentialLists($credList->sid)
            ->credentials
            ->create($username, $password);

        // Note: Twilio PHP SDK uses "credentialsLists" (with 's') not "credentialLists"
        $client->trunking->v1
            ->trunks($twilioTrunk->sid)
            ->credentialsLists
            ->create($credList->sid);

        $trunk->update([
            'termination_method' => 'credentials',
            'credential_list_sid' => $credList->sid,
            'termination_username' => $username,
            'termination_password' => encrypt($password),
        ]);
    }

    /**
     * Step 5: Import user's phone numbers and assign to trunk
     * Uses updateOrCreate to handle retries and existing numbers safely.
     */
    private function importAndAssignNumbers(TwilioClient $client, UserSipTrunk $trunk, object $twilioTrunk): int
    {
        $count = 0;
        $user = $trunk->user;

        if (!$user->webhook_token) {
            $user->webhook_token = Str::random(40);
            $user->save();
        }

        $voiceUrl = route('sip.trunk.voice', ['webhook_token' => $user->webhook_token]);

        try {
            $phoneNumbers = $client->incomingPhoneNumbers->read();

            foreach ($phoneNumbers as $number) {
                try {
                    DB::transaction(function () use ($client, $twilioTrunk, $trunk, $number, $voiceUrl, &$count) {
                        // 1. Assign number to trunk in Twilio
                        try {
                            $client->trunking->v1
                                ->trunks($twilioTrunk->sid)
                                ->phoneNumbers
                                ->create($number->sid);
                        } catch (Exception $e) {
                            // Number may already be assigned to this trunk (retry scenario)
                            if (str_contains($e->getMessage(), 'already associated') || $e->getCode() === 409) {
                                Log::info("Number {$number->sid} already assigned to trunk, continuing");
                            } else {
                                throw $e;
                            }
                        }

                        // 2. Set voice URL for inbound call routing
                        try {
                            $client->incomingPhoneNumbers($number->sid)->update([
                                'voiceUrl' => $voiceUrl,
                                'voiceMethod' => 'POST',
                            ]);
                        } catch (Exception $e) {
                            Log::warning("Could not set voice URL for {$number->sid}: " . $e->getMessage());
                        }

                        // 3. Store in trunk_phone_numbers table (updateOrCreate for retry safety)
                        $trunkNumber = TrunkPhoneNumber::updateOrCreate(
                            [
                                'trunk_id' => $trunk->id,
                                'phone_number_sid' => $number->sid,
                            ],
                            [
                                'phone_number' => $number->phoneNumber,
                                'friendly_name' => $number->friendlyName,
                                'country_code' => $this->extractCountryCode($number->phoneNumber),
                                'capabilities' => [
                                    'voice' => $number->capabilities->voice ?? false,
                                    'sms' => $number->capabilities->sms ?? false,
                                    'mms' => $number->capabilities->mms ?? false,
                                    'fax' => $number->capabilities->fax ?? false,
                                ],
                                'status' => 'active',
                            ]
                        );

                        // 4. Store in phone_numbers table (updateOrCreate for retry safety)
                        PhoneNumber::updateOrCreate(
                            [
                                'twilio_sid' => $number->sid,
                            ],
                            [
                                'number' => $number->phoneNumber,
                                'formatted_number' => $this->formatPhoneNumber($number->phoneNumber),
                                'friendly_name' => $number->friendlyName ?? $this->formatPhoneNumber($number->phoneNumber),
                                'country_code' => $this->extractCountryCode($number->phoneNumber),
                                'area_code' => $this->extractAreaCode($number->phoneNumber),
                                'status' => 'available',
                                'source' => 'sip_trunk',
                                'trunk_phone_number_id' => $trunkNumber->id,
                                'trunk_id' => $trunk->id,
                                'capabilities' => [
                                    'voice' => $number->capabilities->voice ?? false,
                                    'sms' => $number->capabilities->sms ?? false,
                                    'mms' => $number->capabilities->mms ?? false,
                                ],
                                'original_monthly_cost' => 1.00,
                                'monthly_cost' => 0.00,
                                'discount_percentage' => 100.00,
                            ]
                        );

                        $count++;
                    });
                } catch (Exception $e) {
                    // Skip individual number failures, continue with others
                    Log::warning("Failed to import number {$number->phoneNumber}: " . $e->getMessage());
                }
            }

            return $count;

        } catch (Exception $e) {
            Log::warning("Failed to import numbers for trunk {$trunk->id}: " . $e->getMessage());
            return $count; // Return however many we imported before the error
        }
    }

    /**
     * Step 6: Test trunk connection
     */
    private function testTrunkConnection(TwilioClient $client, UserSipTrunk $trunk): array
    {
        $results = [
            'trunk_exists' => false,
            'origination_configured' => false,
            'termination_configured' => false,
            'numbers_assigned' => 0,
        ];

        try {
            // Test 1: Verify trunk exists
            $client->trunking->v1->trunks($trunk->trunk_sid)->fetch();
            $results['trunk_exists'] = true;

            // Test 2: Check origination URLs
            $originationUrls = $client->trunking->v1
                ->trunks($trunk->trunk_sid)
                ->originationUrls
                ->read();
            $results['origination_configured'] = count($originationUrls) > 0;

            // Test 3: Check termination — use correct SDK accessor "credentialsLists"
            if ($trunk->termination_method === 'ip_acl') {
                $ipAcls = $client->trunking->v1
                    ->trunks($trunk->trunk_sid)
                    ->ipAccessControlLists
                    ->read();
                $results['termination_configured'] = count($ipAcls) > 0;
            } else {
                $credLists = $client->trunking->v1
                    ->trunks($trunk->trunk_sid)
                    ->credentialsLists // Fixed: was "credentialLists" (missing 's')
                    ->read();
                $results['termination_configured'] = count($credLists) > 0;
            }

            // Test 4: Check numbers assigned
            $trunkNumbers = $client->trunking->v1
                ->trunks($trunk->trunk_sid)
                ->phoneNumbers
                ->read();
            $results['numbers_assigned'] = count($trunkNumbers);

            TrunkHealthLog::create([
                'trunk_id' => $trunk->id,
                'check_type' => 'connection_test',
                'status' => 'success',
                'details' => $results,
                'checked_at' => now(),
            ]);

            return $results;

        } catch (Exception $e) {
            TrunkHealthLog::create([
                'trunk_id' => $trunk->id,
                'check_type' => 'connection_test',
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'checked_at' => now(),
            ]);

            $results['error'] = $e->getMessage();
            return $results;
        }
    }

    /**
     * Determine health status from test results
     */
    private function determineHealthStatus(array $testResults): string
    {
        if (isset($testResults['error']) || !($testResults['trunk_exists'] ?? false)) {
            return 'down';
        }

        // Trunk exists but no numbers — degraded (still functional)
        if (($testResults['numbers_assigned'] ?? 0) === 0) {
            return 'degraded';
        }

        return 'healthy';
    }

    /**
     * Cleanup orphaned Twilio resources after a failed setup
     */
    private function cleanupFailedSetup(UserSipTrunk $trunk): void
    {
        if (!$trunk->trunk_sid) {
            return; // Nothing was created in Twilio yet
        }

        try {
            $client = $trunk->getTwilioClient();

            // Remove phone numbers from trunk
            try {
                $numbers = $client->trunking->v1
                    ->trunks($trunk->trunk_sid)
                    ->phoneNumbers
                    ->read();

                foreach ($numbers as $number) {
                    try {
                        $client->trunking->v1
                            ->trunks($trunk->trunk_sid)
                            ->phoneNumbers($number->sid)
                            ->delete();
                    } catch (Exception $e) {
                        // Continue cleanup
                    }
                }
            } catch (Exception $e) {
                // Continue cleanup
            }

            // Delete the trunk from Twilio
            try {
                $client->trunking->v1->trunks($trunk->trunk_sid)->delete();
                Log::info("Cleaned up failed trunk from Twilio", ['trunk_sid' => $trunk->trunk_sid]);
            } catch (Exception $e) {
                Log::warning("Could not delete failed trunk from Twilio: " . $e->getMessage());
            }

            // Clean up IP ACL if created
            if ($trunk->ip_acl_sid) {
                try {
                    $client->api->v2010->accounts($trunk->twilio_account_sid)
                        ->sip
                        ->ipAccessControlLists($trunk->ip_acl_sid)
                        ->delete();
                } catch (Exception $e) {
                    // Continue cleanup
                }
            }

            // Clean up credential list if created
            if ($trunk->credential_list_sid) {
                try {
                    $client->api->v2010->accounts($trunk->twilio_account_sid)
                        ->sip
                        ->credentialLists($trunk->credential_list_sid)
                        ->delete();
                } catch (Exception $e) {
                    // Continue cleanup
                }
            }

        } catch (Exception $e) {
            Log::warning("Cleanup of failed trunk resources failed: " . $e->getMessage());
        }
    }

    /**
     * Update trunk setup progress
     */
    private function updateProgress(UserSipTrunk $trunk, string $step, int $progress): void
    {
        $trunk->update([
            'setup_step' => $step,
            'setup_progress' => $progress,
        ]);
    }

    /**
     * Generate unique domain name for trunk
     */
    private function generateDomainName(User $user): string
    {
        $slug = Str::slug($user->name);
        $random = Str::random(6);

        return strtolower("{$slug}-{$user->id}-{$random}.pstn.twilio.com");
    }

    /**
     * Extract country code from phone number
     */
    private function extractCountryCode(string $phoneNumber): string
    {
        if (preg_match('/^\+(\d{1,3})/', $phoneNumber, $matches)) {
            return match($matches[1]) {
                '1' => 'US',
                '44' => 'GB',
                '61' => 'AU',
                default => 'US',
            };
        }
        return 'US';
    }

    /**
     * Extract area code from phone number
     */
    private function extractAreaCode(string $phoneNumber): ?string
    {
        if (preg_match('/^\+1(\d{3})/', $phoneNumber, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Format phone number for display
     */
    private function formatPhoneNumber(string $phoneNumber): string
    {
        if (preg_match('/^\+1(\d{3})(\d{3})(\d{4})$/', $phoneNumber, $matches)) {
            return sprintf('(%s) %s-%s', $matches[1], $matches[2], $matches[3]);
        }
        return $phoneNumber;
    }

    /**
     * Sync phone numbers from Twilio (for existing trunks)
     */
    public function syncPhoneNumbers(UserSipTrunk $trunk): array
    {
        if (!$trunk->isOperational()) {
            return ['success' => false, 'error' => 'Trunk is not operational'];
        }

        $client = $trunk->getTwilioClient();
        $count = 0;

        try {
            $trunkNumbers = $client->trunking->v1
                ->trunks($trunk->trunk_sid)
                ->phoneNumbers
                ->read();

            foreach ($trunkNumbers as $number) {
                DB::transaction(function () use ($client, $number, $trunk, &$count) {
                    $fullNumber = $client->incomingPhoneNumbers($number->phoneNumberSid)->fetch();

                    $trunkNumber = TrunkPhoneNumber::updateOrCreate(
                        [
                            'trunk_id' => $trunk->id,
                            'phone_number_sid' => $number->phoneNumberSid,
                        ],
                        [
                            'phone_number' => $fullNumber->phoneNumber,
                            'friendly_name' => $fullNumber->friendlyName,
                            'country_code' => $this->extractCountryCode($fullNumber->phoneNumber),
                            'capabilities' => [
                                'voice' => $fullNumber->capabilities->voice ?? false,
                                'sms' => $fullNumber->capabilities->sms ?? false,
                                'mms' => $fullNumber->capabilities->mms ?? false,
                                'fax' => $fullNumber->capabilities->fax ?? false,
                            ],
                            'status' => 'active',
                        ]
                    );

                    PhoneNumber::updateOrCreate(
                        [
                            'twilio_sid' => $number->phoneNumberSid,
                        ],
                        [
                            'number' => $fullNumber->phoneNumber,
                            'formatted_number' => $this->formatPhoneNumber($fullNumber->phoneNumber),
                            'friendly_name' => $fullNumber->friendlyName ?? $this->formatPhoneNumber($fullNumber->phoneNumber),
                            'country_code' => $this->extractCountryCode($fullNumber->phoneNumber),
                            'area_code' => $this->extractAreaCode($fullNumber->phoneNumber),
                            'status' => 'available',
                            'source' => 'sip_trunk',
                            'trunk_phone_number_id' => $trunkNumber->id,
                            'trunk_id' => $trunk->id,
                            'capabilities' => [
                                'voice' => $fullNumber->capabilities->voice ?? false,
                                'sms' => $fullNumber->capabilities->sms ?? false,
                                'mms' => $fullNumber->capabilities->mms ?? false,
                            ],
                            'original_monthly_cost' => 1.00,
                            'monthly_cost' => 0.00,
                            'discount_percentage' => 100.00,
                        ]
                    );

                    $count++;
                });
            }

            return ['success' => true, 'synced_count' => $count];

        } catch (Exception $e) {
            Log::error("Failed to sync numbers for trunk {$trunk->id}: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
