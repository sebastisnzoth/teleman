<?php

namespace App\Services;

use App\Models\UserSipTrunk;
use App\Models\TrunkHealthLog;
use Twilio\Rest\Client as TwilioClient;
use Illuminate\Support\Facades\Log;
use Exception;

class TrunkHealthService
{
    /**
     * Perform comprehensive health check on trunk
     * 
     * @param UserSipTrunk $trunk
     * @return array
     */
    public function performHealthCheck(UserSipTrunk $trunk): array
    {
        $startTime = microtime(true);
        $issues = [];
        $healthy = true;

        try {
            $client = $trunk->getTwilioClient();

            // Test 1: API Authentication
            $apiTest = $this->testApiConnection($client, $trunk);
            if (!$apiTest['success']) {
                $healthy = false;
                $issues[] = $apiTest['error'];
            }

            // Test 2: Trunk Exists
            $trunkTest = $this->testTrunkExists($client, $trunk);
            if (!$trunkTest['success']) {
                $healthy = false;
                $issues[] = $trunkTest['error'];
            }

            // Test 3: Origination Configuration
            $originationTest = $this->testOrigination($client, $trunk);
            if (!$originationTest['success']) {
                $healthy = false;
                $issues[] = $originationTest['error'];
            }

            // Test 4: Termination Configuration
            $terminationTest = $this->testTermination($client, $trunk);
            if (!$terminationTest['success']) {
                $healthy = false;
                $issues[] = $terminationTest['error'];
            }

            // Test 5: Phone Numbers
            $numbersTest = $this->testPhoneNumbers($client, $trunk);
            if (!$numbersTest['success']) {
                // Warning only, not critical
                $issues[] = $numbersTest['error'];
            }

            $responseTime = round((microtime(true) - $startTime) * 1000);

            // Update trunk health status
            $healthStatus = $healthy ? 'healthy' : 'down';
            $trunk->updateHealthStatus($healthStatus, $healthy ? null : implode('; ', $issues));

            // Log health check
            TrunkHealthLog::create([
                'trunk_id' => $trunk->id,
                'check_type' => 'api_test',
                'status' => $healthy ? 'success' : 'failed',
                'response_time_ms' => $responseTime,
                'error_message' => $healthy ? null : implode('; ', $issues),
                'details' => [
                    'api_test' => $apiTest,
                    'trunk_test' => $trunkTest,
                    'origination_test' => $originationTest,
                    'termination_test' => $terminationTest,
                    'numbers_test' => $numbersTest,
                ],
                'checked_at' => now(),
            ]);

            return [
                'healthy' => $healthy,
                'health_status' => $healthStatus,
                'issues' => $issues,
                'response_time_ms' => $responseTime,
                'tests' => [
                    'api' => $apiTest,
                    'trunk' => $trunkTest,
                    'origination' => $originationTest,
                    'termination' => $terminationTest,
                    'numbers' => $numbersTest,
                ],
            ];

        } catch (Exception $e) {
            Log::error("Health check failed for trunk {$trunk->id}: " . $e->getMessage());

            $trunk->updateHealthStatus('down', $e->getMessage());

            TrunkHealthLog::create([
                'trunk_id' => $trunk->id,
                'check_type' => 'api_test',
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'checked_at' => now(),
            ]);

            return [
                'healthy' => false,
                'health_status' => 'down',
                'issues' => [$e->getMessage()],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test API connection and authentication
     */
    private function testApiConnection(TwilioClient $client, UserSipTrunk $trunk): array
    {
        try {
            $account = $client->api->v2010->accounts($trunk->twilio_account_sid)->fetch();

            if ($account->status !== 'active') {
                return [
                    'success' => false,
                    'error' => 'Twilio account is not active'
                ];
            }

            return ['success' => true];

        } catch (\Twilio\Exceptions\AuthenticationException $e) {
            return [
                'success' => false,
                'error' => 'Authentication failed - invalid credentials'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'API connection failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test if trunk exists and is accessible
     */
    private function testTrunkExists(TwilioClient $client, UserSipTrunk $trunk): array
    {
        try {
            $twilioTrunk = $client->trunking->v1->trunks($trunk->trunk_sid)->fetch();

            return [
                'success' => true,
                'trunk_sid' => $twilioTrunk->sid,
                'domain' => $twilioTrunk->domainName,
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Trunk not found or inaccessible'
            ];
        }
    }

    /**
     * Test origination configuration
     */
    private function testOrigination(TwilioClient $client, UserSipTrunk $trunk): array
    {
        try {
            $originationUrls = $client->trunking->v1
                ->trunks($trunk->trunk_sid)
                ->originationUrls
                ->read();

            // BYOT setup intentionally skips origination (inbound via phone number webhooks)
            if (count($originationUrls) === 0) {
                return [
                    'success' => true,
                    'urls_count' => 0,
                    'active_count' => 0,
                    'note' => 'Origination skipped (inbound via number webhooks)',
                ];
            }

            $activeUrls = array_filter($originationUrls, fn($url) => $url->enabled);

            return [
                'success' => true,
                'urls_count' => count($originationUrls),
                'active_count' => count($activeUrls),
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to check origination: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test termination configuration
     */
    private function testTermination(TwilioClient $client, UserSipTrunk $trunk): array
    {
        try {
            if ($trunk->termination_method === 'ip_acl') {
                $ipAcls = $client->trunking->v1
                    ->trunks($trunk->trunk_sid)
                    ->ipAccessControlLists
                    ->read();

                if (count($ipAcls) === 0) {
                    return [
                        'success' => false,
                        'error' => 'No IP ACLs configured'
                    ];
                }

                return [
                    'success' => true,
                    'method' => 'ip_acl',
                    'acl_count' => count($ipAcls),
                ];

            } else {
                $credLists = $client->trunking->v1
                    ->trunks($trunk->trunk_sid)
                    ->credentialsLists // Fixed: was "credentialLists" (missing 's')
                    ->read();

                if (count($credLists) === 0) {
                    return [
                        'success' => false,
                        'error' => 'No credential lists configured'
                    ];
                }

                return [
                    'success' => true,
                    'method' => 'credentials',
                    'credential_lists_count' => count($credLists),
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to check termination: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test phone numbers assignment
     */
    private function testPhoneNumbers(TwilioClient $client, UserSipTrunk $trunk): array
    {
        try {
            $trunkNumbers = $client->trunking->v1
                ->trunks($trunk->trunk_sid)
                ->phoneNumbers
                ->read();

            $numbersCount = count($trunkNumbers);

            if ($numbersCount === 0) {
                return [
                    'success' => false,
                    'error' => 'No phone numbers assigned to trunk',
                    'count' => 0,
                ];
            }

            return [
                'success' => true,
                'count' => $numbersCount,
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to check phone numbers: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get health check history
     * 
     * @param UserSipTrunk $trunk
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    public function getHealthHistory(UserSipTrunk $trunk, int $limit = 10)
    {
        return $trunk->healthLogs()
            ->orderBy('checked_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent failed health checks
     * 
     * @param UserSipTrunk $trunk
     * @param int $hours
     * @return int
     */
    public function getRecentFailures(UserSipTrunk $trunk, int $hours = 24): int
    {
        return $trunk->healthLogs()
            ->where('status', 'failed')
            ->where('checked_at', '>=', now()->subHours($hours))
            ->count();
    }

    /**
     * Check if trunk needs attention
     * 
     * @param UserSipTrunk $trunk
     * @return array
     */
    public function needsAttention(UserSipTrunk $trunk): array
    {
        $issues = [];

        // Check 1: Health status
        if ($trunk->health_status !== 'healthy') {
            $issues[] = [
                'type' => 'health',
                'severity' => 'high',
                'message' => 'Trunk health status is ' . $trunk->health_status,
            ];
        }

        // Check 2: Recent failures
        $recentFailures = $this->getRecentFailures($trunk, 24);
        if ($recentFailures > 3) {
            $issues[] = [
                'type' => 'failures',
                'severity' => 'medium',
                'message' => "{$recentFailures} health check failures in last 24 hours",
            ];
        }

        // Check 3: No recent calls
        if ($trunk->last_call_at && $trunk->last_call_at->diffInDays(now()) > 30) {
            $issues[] = [
                'type' => 'inactive',
                'severity' => 'low',
                'message' => 'No calls in last 30 days',
            ];
        }

        // Check 4: No phone numbers
        if ($trunk->phoneNumbers()->where('status', 'active')->count() === 0) {
            $issues[] = [
                'type' => 'no_numbers',
                'severity' => 'high',
                'message' => 'No active phone numbers assigned',
            ];
        }

        return [
            'needs_attention' => count($issues) > 0,
            'issues' => $issues,
            'issue_count' => count($issues),
        ];
    }
}
