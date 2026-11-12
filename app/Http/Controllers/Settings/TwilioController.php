<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\TwilioAccountMigration;
use App\Models\TwilioGlobalConfig;
use App\Services\TwilioAccountMigrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Twilio\Rest\Client;

class TwilioController extends Controller
{
    /**
     * Show Twilio configuration page
     */
    public function show(Request $request): Response
    {
        // Only admin can view Twilio settings
        $user = $request->user();
        
        if (!$user->isAdmin()) {
            return Inertia::render('settings/twilio', [
                'config' => null,
                'canManageTwilio' => false,
            ]);
        }

        $config = TwilioGlobalConfig::active();
        
        $latestMigration = TwilioAccountMigration::where('created_at', '>=', now()->subDay())
            ->latest()
            ->first();

        return Inertia::render('settings/twilio', [
            'config' => $config ? [
                'id' => $config->id,
                'account_sid' => $config->account_sid,
                'api_key_sid' => $config->api_key_sid,
                'twiml_app_sid' => $config->twiml_app_sid,
                'webhook_url' => $config->webhook_url,
                'is_active' => $config->is_active,
                'verified_at' => $config->verified_at?->toDateTimeString(),
            ] : null,
            'canManageTwilio' => true,
            'latestMigration' => $latestMigration ? [
                'id' => $latestMigration->id,
                'old_account_sid' => $latestMigration->old_account_sid,
                'new_account_sid' => $latestMigration->new_account_sid,
                'numbers_matched' => $latestMigration->numbers_matched,
                'numbers_deactivated' => $latestMigration->numbers_deactivated,
                'numbers_new' => $latestMigration->numbers_new,
                'campaigns_paused' => $latestMigration->campaigns_paused,
                'ai_agents_reset' => $latestMigration->ai_agents_reset,
                'calls_marked_legacy' => $latestMigration->calls_marked_legacy,
                'sms_numbers_deactivated' => $latestMigration->sms_numbers_deactivated,
                'created_at' => $latestMigration->created_at->toDateTimeString(),
            ] : null,
        ]);
    }
    
    /**
     * Configure global Twilio settings
     */
    public function configure(Request $request)
    {
        // Only admin can configure Twilio
        if (!$request->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'account_sid' => 'required|string|starts_with:AC',
            'auth_token' => 'required|string|min:32',
        ]);
        
        // Trim whitespace from credentials (common copy-paste issue)
        $validated['account_sid'] = trim($validated['account_sid']);
        $validated['auth_token'] = trim($validated['auth_token']);
        
        // Initialize API key and TwiML App as null - will be created fresh
        $validated['api_key_sid'] = null;
        $validated['api_key_secret'] = null;
        $validated['twiml_app_sid'] = null;
        
        // Check if auth token looks like a placeholder
        if (str_contains(strtolower($validated['auth_token']), 'your twilio') || 
            str_contains(strtolower($validated['auth_token']), 'placeholder')) {
            return back()->withErrors([
                'auth_token' => 'Please enter your actual Twilio Auth Token, not the placeholder text.'
            ]);
        }
        
        // Auto-generate webhook URL from current application URL
        $validated['webhook_url'] = rtrim(config('app.url'), '/');

        try {
            // Verify credentials
            $client = new Client(
                $validated['account_sid'],
                $validated['auth_token']
            );
            
            // Test the connection - use a simpler method that just validates credentials
            try {
                // Try to list incoming phone numbers (this is a basic read operation)
                // Just fetch one to test if credentials are valid
                $numbers = $client->incomingPhoneNumbers->read([], 1);
                
                Log::info('Twilio authentication successful', [
                    'account_sid' => $validated['account_sid'],
                ]);
            } catch (\Twilio\Exceptions\AuthenticationException $e) {
                Log::error('Twilio authentication failed', [
                    'account_sid' => $validated['account_sid'],
                    'auth_token_length' => strlen($validated['auth_token']),
                    'error' => $e->getMessage(),
                    'error_code' => $e->getCode(),
                ]);
                return back()->withErrors([
                    'auth_token' => 'Authentication failed. The Account SID and Auth Token do not match. Please copy BOTH from the same Twilio account. Auth Token must be exactly 32 characters (current: ' . strlen($validated['auth_token']) . ').'
                ]);
            } catch (\Exception $e) {
                Log::error('Twilio connection error', [
                    'account_sid' => $validated['account_sid'],
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e),
                ]);
                return back()->withErrors([
                    'account_sid' => 'Connection failed: ' . $e->getMessage()
                ]);
            }
            
            // Create API Key if not provided
            if (empty($validated['api_key_sid']) || empty($validated['api_key_secret'])) {
                try {
                    $apiKey = $client->newKeys->create([
                        'friendlyName' => 'Teleman AI WebRTC Key - ' . now()->format('Y-m-d H:i:s'),
                    ]);
                    
                    $validated['api_key_sid'] = $apiKey->sid;
                    $validated['api_key_secret'] = $apiKey->secret;
                } catch (\Exception $e) {
                    return back()->with('error', 'Failed to create API Key: ' . $e->getMessage());
                }
            }
            
            // Prepare TwiML App URLs
            // Use manual-call endpoint as the primary voice URL
            // This handles BOTH inbound (external phones) AND outbound (browser) calls
            $voiceUrl = rtrim($validated['webhook_url'], '/') . '/twiml/manual-call';
            $statusCallback = rtrim($validated['webhook_url'], '/') . '/webhooks/twilio/call-status';
            // Fallback URL for error handling
            $voiceFallbackUrl = rtrim($validated['webhook_url'], '/') . '/twiml/inbound-call';
            
            // Create or update TwiML App
            if (empty($validated['twiml_app_sid'])) {
                // Create new TwiML App
                try {
                    $application = $client->applications->create([
                        'voiceUrl' => $voiceUrl,
                        'voiceMethod' => 'POST',
                        'voiceFallbackUrl' => $voiceFallbackUrl,
                        'voiceFallbackMethod' => 'POST',
                        'statusCallback' => $statusCallback,
                        'statusCallbackMethod' => 'POST',
                        'friendlyName' => 'Teleman AI WebRTC - ' . now()->format('Y-m-d H:i:s'),
                    ]);
                    
                    $validated['twiml_app_sid'] = $application->sid;
                    
                    Log::info('TwiML App created', [
                        'app_sid' => $application->sid,
                        'voice_url' => $voiceUrl,
                    ]);
                } catch (\Exception $e) {
                    return back()->with('error', 'Failed to create TwiML App: ' . $e->getMessage());
                }
            } else {
                // Update existing TwiML App to ensure URLs are correct
                try {
                    $client->applications($validated['twiml_app_sid'])
                        ->update([
                            'voiceUrl' => $voiceUrl,
                            'voiceMethod' => 'POST',
                            'voiceFallbackUrl' => $voiceFallbackUrl,
                            'voiceFallbackMethod' => 'POST',
                            'statusCallback' => $statusCallback,
                            'statusCallbackMethod' => 'POST',
                        ]);
                    
                    Log::info('TwiML App updated', [
                        'app_sid' => $validated['twiml_app_sid'],
                        'voice_url' => $voiceUrl,
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Failed to update existing TwiML App, will use as-is', [
                        'app_sid' => $validated['twiml_app_sid'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // Detect account change and run migration if needed
            $currentConfig = TwilioGlobalConfig::active();
            $migrationSummary = null;

            if ($currentConfig && $currentConfig->account_sid !== $validated['account_sid']) {
                try {
                    $migrationService = app(TwilioAccountMigrationService::class);
                    $migration = $migrationService->migrate(
                        oldAccountSid: $currentConfig->account_sid,
                        newAccountSid: $validated['account_sid'],
                        newAuthToken: $validated['auth_token'],
                        performedById: $request->user()->id,
                    );
                    $migrationSummary = [
                        'id' => $migration->id,
                        'numbers_matched' => $migration->numbers_matched,
                        'numbers_deactivated' => $migration->numbers_deactivated,
                        'numbers_new' => $migration->numbers_new,
                        'campaigns_paused' => $migration->campaigns_paused,
                        'ai_agents_reset' => $migration->ai_agents_reset,
                        'calls_marked_legacy' => $migration->calls_marked_legacy,
                        'sms_numbers_deactivated' => $migration->sms_numbers_deactivated,
                    ];

                    Log::info('Twilio account migration completed', $migrationSummary);
                } catch (\Exception $e) {
                    Log::error('Twilio account migration failed', [
                        'error' => $e->getMessage(),
                        'old_sid' => $currentConfig->account_sid,
                        'new_sid' => $validated['account_sid'],
                    ]);
                    return back()->withErrors([
                        'account_sid' => 'Account migration failed: ' . $e->getMessage() . ' Your previous configuration is unchanged.',
                    ]);
                }
            }

            // Delete all old Twilio configurations to prevent conflicts
            $oldConfigs = TwilioGlobalConfig::where('account_sid', '!=', $validated['account_sid'])->get();
            foreach ($oldConfigs as $oldConfig) {
                Log::info('Deleting old Twilio configuration', [
                    'old_account_sid' => $oldConfig->account_sid,
                    'new_account_sid' => $validated['account_sid']
                ]);
                $oldConfig->delete();
            }

            // Deactivate any remaining configs (safety measure)
            TwilioGlobalConfig::query()->update(['is_active' => false]);

            // Create or update config
            $config = TwilioGlobalConfig::updateOrCreate(
                ['account_sid' => $validated['account_sid']],
                [
                    'auth_token' => $validated['auth_token'],
                    'api_key_sid' => $validated['api_key_sid'],
                    'api_key_secret' => $validated['api_key_secret'],
                    'twiml_app_sid' => $validated['twiml_app_sid'],
                    'webhook_url' => rtrim($validated['webhook_url'], '/'),
                    'is_active' => true,
                    'verified_at' => now(),
                ]
            );

            if ($migrationSummary) {
                return back()->with([
                    'success' => 'Twilio configuration updated. Account migration completed successfully.',
                    'migration' => $migrationSummary,
                ]);
            }

            return back()->with('success', 'Twilio configuration saved successfully! You can now configure individual phone numbers from the Phone Number Inventory.');
        } catch (\Exception $e) {
            Log::error('Twilio configuration failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to configure Twilio: ' . $e->getMessage());
        }
    }
    
    /**
     * Configure specific phone numbers
     */
    public function configurePhones(Request $request)
    {
        // Only admin can configure Twilio
        if (!$request->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'phone_numbers' => 'required|array|min:1',
            'phone_numbers.*' => 'required|string',
        ]);
        
        $config = TwilioGlobalConfig::active();
        
        if (!$config) {
            return back()->with('error', 'No active Twilio configuration found. Please run: php artisan twilio:configure');
        }
        
        try {
            $client = new Client(
                $config->account_sid,
                $config->getDecryptedAuthToken()
            );
            
            $configured = 0;
            $errors = [];
            
            foreach ($request->phone_numbers as $phoneNumber) {
                try {
                    // Find the phone number by phone number string
                    $numbers = $client->incomingPhoneNumbers->read(['phoneNumber' => $phoneNumber]);
                    
                    if (empty($numbers)) {
                        $errors[] = "Phone number {$phoneNumber} not found";
                        continue;
                    }
                    
                    $numberSid = $numbers[0]->sid;
                    
                    // Update to use TwiML Application (this is the recommended approach)
                    $client->incomingPhoneNumbers($numberSid)
                        ->update([
                            'voiceApplicationSid' => $config->twiml_app_sid,
                        ]);
                    
                    $configured++;
                    
                    Log::info('Phone number configured manually', [
                        'phone' => $phoneNumber,
                        'sid' => $numberSid,
                        'twiml_app' => $config->twiml_app_sid,
                    ]);
                } catch (\Exception $e) {
                    $errors[] = "Failed to configure {$phoneNumber}: " . $e->getMessage();
                }
            }
            
            if ($configured > 0) {
                $message = "Successfully configured {$configured} phone number(s)";
                if (!empty($errors)) {
                    $message .= '. Some errors occurred: ' . implode(', ', $errors);
                }
                return back()->with('success', $message);
            } else {
                return back()->with('error', 'Failed to configure phone numbers: ' . implode(', ', $errors));
            }
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to configure phone numbers: ' . $e->getMessage());
        }
    }
    
    /**
     * Test call functionality
     */
    public function testCall(Request $request)
    {
        // Only admin can test calls
        if (!$request->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $request->validate([
            'phone_number' => 'required|string',
            'from_number' => 'required|string',
        ]);

        $config = TwilioGlobalConfig::active();
        
        if (!$config) {
            return back()->with('error', 'No active Twilio configuration found');
        }

        try {
            $client = new Client(
                $config->account_sid,
                $config->getDecryptedAuthToken()
            );

            // Create a simple test call
            $call = $client->calls->create(
                $request->phone_number, // To
                $request->from_number,  // From
                [
                    'url' => route('twiml.test-call'),
                    'statusCallback' => $config->webhook_url . '/webhooks/twilio/call/status/test',
                ]
            );

            Log::info('Test call initiated', [
                'call_sid' => $call->sid,
                'to' => $request->phone_number,
                'from' => $request->from_number,
                'status' => $call->status,
            ]);

            return back()->with('success', "Test call initiated! Call SID: {$call->sid}. Check your phone.");
        } catch (\Exception $e) {
            Log::error('Test call failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to initiate test call: ' . $e->getMessage());
        }
    }

    /**
     * Test webhook connectivity
     */
    public function testWebhook(Request $request)
    {
        // Only admin can test webhooks
        if (!$request->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $config = TwilioGlobalConfig::active();
        
        if (!$config) {
            return back()->with('error', 'No active Twilio configuration found');
        }
        
        $webhookUrl = $config->webhook_url . '/twiml/manual-call?call_id=test';
        
        try {
            $ch = curl_init($webhookUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Bypass-Tunnel-Reminder: true']);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode == 200) {
                return back()->with('success', "Webhook is accessible (HTTP {$httpCode})");
            } else {
                return back()->with('error', "Webhook returned HTTP {$httpCode}. Make sure your webhook URL is publicly accessible.");
            }
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to test webhook: ' . $e->getMessage());
        }
    }

    /**
     * Remove Twilio configuration
     */
    public function remove(Request $request)
    {
        // Only admin can remove Twilio configuration
        if (!$request->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $config = TwilioGlobalConfig::active();
            
            if (!$config) {
                return back()->with('error', 'No active Twilio configuration found');
            }

            // Delete the configuration
            $config->delete();

            return back()->with('success', 'Twilio configuration removed successfully');
        } catch (\Exception $e) {
            Log::error('Failed to remove Twilio configuration: ' . $e->getMessage());
            return back()->with('error', 'Failed to remove Twilio configuration: ' . $e->getMessage());
        }
    }
    
    /**
     * Sync/Update TwiML App and phone numbers with current configuration
     * This ensures all URLs are up-to-date without manual Twilio Console changes
     */
    public function sync(Request $request)
    {
        // Only admin can sync Twilio configuration
        if (!$request->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $config = TwilioGlobalConfig::active();
        
        if (!$config) {
            return back()->with('error', 'No active Twilio configuration found');
        }

        try {
            $client = new Client(
                $config->account_sid,
                $config->getDecryptedAuthToken()
            );
            
            // Prepare updated URLs
            $voiceUrl = rtrim($config->webhook_url, '/') . '/twiml/manual-call';
            $statusCallback = rtrim($config->webhook_url, '/') . '/webhooks/twilio/call-status';
            $voiceFallbackUrl = rtrim($config->webhook_url, '/') . '/twiml/inbound-call';
            
            // Update TwiML App
            if ($config->twiml_app_sid) {
                try {
                    $client->applications($config->twiml_app_sid)
                        ->update([
                            'voiceUrl' => $voiceUrl,
                            'voiceMethod' => 'POST',
                            'voiceFallbackUrl' => $voiceFallbackUrl,
                            'voiceFallbackMethod' => 'POST',
                            'statusCallback' => $statusCallback,
                            'statusCallbackMethod' => 'POST',
                        ]);
                    
                    Log::info('TwiML App synced successfully', [
                        'app_sid' => $config->twiml_app_sid,
                        'voice_url' => $voiceUrl,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to update TwiML App', [
                        'app_sid' => $config->twiml_app_sid,
                        'error' => $e->getMessage(),
                    ]);
                    return back()->with('error', 'Failed to update TwiML App: ' . $e->getMessage());
                }
            }
            
            // Update all phone numbers
            $phoneNumbersUpdated = 0;
            $phoneNumberErrors = [];
            
            try {
                $numbers = $client->incomingPhoneNumbers->read();
                
                foreach ($numbers as $number) {
                    try {
                        $client->incomingPhoneNumbers($number->sid)
                            ->update([
                                'voiceApplicationSid' => $config->twiml_app_sid,
                            ]);
                        
                        $phoneNumbersUpdated++;
                        
                        Log::info('Phone number synced', [
                            'phone' => $number->phoneNumber,
                            'sid' => $number->sid,
                        ]);
                    } catch (\Exception $e) {
                        $phoneNumberErrors[] = $number->phoneNumber;
                        Log::error('Failed to sync phone number', [
                            'phone' => $number->phoneNumber,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } catch (\Exception $e) {
                Log::error('Failed to fetch phone numbers for sync', [
                    'error' => $e->getMessage(),
                ]);
            }
            
            // Update the verified timestamp
            $config->update(['verified_at' => now()]);
            
            // Build success message
            $message = 'Configuration synced successfully!';
            if ($config->twiml_app_sid) {
                $message .= ' TwiML App updated.';
            }
            if ($phoneNumbersUpdated > 0) {
                $message .= " {$phoneNumbersUpdated} phone number(s) updated.";
            }
            if (!empty($phoneNumberErrors)) {
                $message .= ' Some phone numbers could not be updated: ' . implode(', ', $phoneNumberErrors);
            }
            
            return back()->with('success', $message);
        } catch (\Exception $e) {
            Log::error('Configuration sync failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to sync configuration: ' . $e->getMessage());
        }
    }
    
    /**
     * Get current geo permissions with all available countries
     */
    public function getGeoPermissions(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        // Try to get active config first, then fall back to any config
        $config = TwilioGlobalConfig::active() ?? TwilioGlobalConfig::first();
        
        if (!$config) {
            return response()->json(['error' => 'Please configure Twilio credentials first'], 404);
        }

        try {
            $client = new Client(
                $config->account_sid,
                $config->getDecryptedAuthToken()
            );
            
            // Get list of all available countries with their permissions
            $availableCountries = $client->voice->v1->dialingPermissions
                ->countries->read();
            
            $countries = [];
            foreach ($availableCountries as $country) {
                $countries[] = [
                    'iso_code' => $country->isoCode,
                    'name' => $country->name,
                    'continent' => $country->continent ?? 'Unknown',
                    'enabled' => $country->lowRiskNumbersEnabled || $country->highRiskSpecialNumbersEnabled || $country->highRiskTollfraudNumbersEnabled,
                ];
            }
            
            // Sort by name
            usort($countries, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
            
            return response()->json([
                'countries' => $countries,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch geo permissions: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch geo permissions: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Update geo permissions by enabling/disabling specific countries
     */
    public function updateGeoPermissions(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'enabled_countries' => 'required|array',
            'enabled_countries.*' => 'required|string|size:2', // ISO country codes
        ]);

        // Try to get active config first, then fall back to any config
        $config = TwilioGlobalConfig::active() ?? TwilioGlobalConfig::first();
        
        if (!$config) {
            return response()->json(['error' => 'Please configure Twilio credentials first'], 404);
        }

        try {
            $client = new Client(
                $config->account_sid,
                $config->getDecryptedAuthToken()
            );
            
            // First, get all available countries to disable them all
            $allCountries = $client->voice->v1->dialingPermissions
                ->countries->read();
            
            $allIsoCodes = array_map(function($country) {
                return $country->isoCode;
            }, iterator_to_array($allCountries));
            
            // Disable all countries first
            if (!empty($allIsoCodes)) {
                $client->voice->v1->dialingPermissions
                    ->bulkCountryUpdates->create([
                        'updateRequest' => implode(',', array_map(function($iso) {
                            return $iso . ':low_risk_numbers_enabled=false,high_risk_special_numbers_enabled=false,high_risk_tollfraud_numbers_enabled=false';
                        }, $allIsoCodes))
                    ]);
            }
            
            // If countries selected, enable them
            if (!empty($validated['enabled_countries'])) {
                $client->voice->v1->dialingPermissions
                    ->bulkCountryUpdates->create([
                        'updateRequest' => implode(',', array_map(function($iso) {
                            return $iso . ':low_risk_numbers_enabled=true,high_risk_special_numbers_enabled=true,high_risk_tollfraud_numbers_enabled=true';
                        }, $validated['enabled_countries']))
                    ]);
                
                Log::info('Geo permissions updated', [
                    'countries' => $validated['enabled_countries'],
                ]);
                
                session()->flash('success', count($validated['enabled_countries']) . ' countries enabled successfully');
                
                return response()->json([
                    'success' => true,
                    'message' => count($validated['enabled_countries']) . ' countries enabled',
                ]);
            } else {
                Log::info('All geo permissions disabled');
                
                session()->flash('success', 'All countries disabled successfully');
                
                return response()->json([
                    'success' => true,
                    'message' => 'All countries disabled',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to update geo permissions: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to update geo permissions: ' . $e->getMessage()], 500);
        }
    }
    
    /**
     * Enable all countries (remove geo restrictions)
     */
    public function enableAllCountries(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            abort(403, 'Unauthorized action.');
        }

        // Try to get active config first, then fall back to any config
        $config = TwilioGlobalConfig::active() ?? TwilioGlobalConfig::first();
        
        if (!$config) {
            return response()->json(['error' => 'Please configure Twilio credentials first'], 404);
        }

        try {
            $client = new Client(
                $config->account_sid,
                $config->getDecryptedAuthToken()
            );
            
            // Get all available countries
            $allCountries = $client->voice->v1->dialingPermissions
                ->countries->read();
            
            $allIsoCodes = array_map(function($country) {
                return $country->isoCode;
            }, iterator_to_array($allCountries));
            
            // Enable all countries
            if (!empty($allIsoCodes)) {
                $client->voice->v1->dialingPermissions
                    ->bulkCountryUpdates->create([
                        'updateRequest' => implode(',', array_map(function($iso) {
                            return $iso . ':low_risk_numbers_enabled=true,high_risk_special_numbers_enabled=true,high_risk_tollfraud_numbers_enabled=true';
                        }, $allIsoCodes))
                    ]);
            }
            
            Log::info('All geo permissions enabled');
            
            session()->flash('success', 'All countries enabled successfully');
            
            return response()->json([
                'success' => true,
                'message' => 'All countries enabled',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to enable all countries: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to enable all countries: ' . $e->getMessage()], 500);
        }
    }
}

