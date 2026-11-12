<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TelephonyProvider;
use App\Helpers\PhoneNumberHelper;
use App\Http\Controllers\Controller;
use App\Models\PhoneNumber;
use App\Models\TelephonyProviderConfig;
use App\Models\TwilioGlobalConfig;
use App\Services\CreditService;
use App\Services\PhoneNumberService;
use App\Services\PricingService;
use App\Services\Telephony\TelephonyManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class PhoneNumberController extends Controller
{
    protected $phoneNumberService;
    protected $creditService;
    protected $pricingService;

    public function __construct(
        PhoneNumberService $phoneNumberService,
        CreditService $creditService,
        PricingService $pricingService,
        private readonly TelephonyManager $telephony,
    ) {
        $this->phoneNumberService = $phoneNumberService;
        $this->creditService = $creditService;
        $this->pricingService = $pricingService;
    }

    /**
     * Display phone number inventory
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', PhoneNumber::class);

        $query = PhoneNumber::with(['user', 'requester', 'approver']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by country
        if ($request->filled('country')) {
            $query->where('country_code', $request->country);
        }

        // Filter by area code
        if ($request->filled('area_code')) {
            $query->where('area_code', $request->area_code);
        }

        // Search by number
        if ($request->filled('search')) {
            $query->where('number', 'like', '%' . $request->search . '%');
        }

        $numbers = $query->latest()
            ->paginate($request->per_page ?? 15)
            ->withQueryString();

        $statistics = $this->phoneNumberService->getStatistics();

        // Which provider's account this page is showing. Everything below
        // that reads Twilio directly is skipped for any other provider —
        // the Twilio branch is exactly the code that was here before this
        // page knew there could be another one.
        $isPlivo = $this->telephony->provider() === TelephonyProvider::PLIVO;
        $plivoCredentials = $isPlivo ? $this->getPlivoCredentials() : null;
        $twilioConfig = $isPlivo ? null : $this->getTwilioConfig();

        // Fetch Twilio numbers with configuration status (always fetch fresh from Twilio API)
        $twilioNumbers = $isPlivo
            ? $this->getPlivoNumbers($plivoCredentials)
            : $this->getTwilioNumbers();

        if (! $isPlivo) {
            // Auto-sync Twilio numbers to database in background for better data consistency
            $this->syncTwilioNumbersToDatabase($twilioNumbers);
        }

        // Get ALL assigned phone numbers (not just paginated ones) for matching with Twilio numbers
        $allAssignedNumbers = PhoneNumber::where('status', 'assigned')
            ->with('user')
            ->get()
            ->keyBy('number'); // Key by phone number for quick lookup

        // Enhance Twilio numbers with assignment information
        $twilioNumbers = collect($twilioNumbers)->map(function ($twilioNumber) use ($allAssignedNumbers) {
            $assignedNumber = $allAssignedNumbers->get($twilioNumber['phone_number']);
            
            if ($assignedNumber) {
                $twilioNumber['assigned_to'] = [
                    'id' => $assignedNumber->id,
                    'user_id' => $assignedNumber->user_id,
                    'user_name' => $assignedNumber->user ? $assignedNumber->user->name : null,
                    'assigned_at' => $assignedNumber->assigned_at,
                ];
            } else {
                $twilioNumber['assigned_to'] = null;
            }
            
            return $twilioNumber;
        })->toArray();

        // Get customer and admin users for assignment
        $users = \App\Models\User::select('users.id', 'users.name', 'users.email')
            ->join('role_user', 'users.id', '=', 'role_user.user_id')
            ->join('roles', 'role_user.role_id', '=', 'roles.id')
            ->whereIn('roles.slug', ['customer', 'admin'])
            ->distinct()
            ->orderBy('users.name')
            ->get();

        return Inertia::render('Admin/PhoneNumbers/Index', [
            'numbers' => $numbers,
            'stats' => $statistics,
            'filters' => $request->only(['status', 'country', 'area_code', 'search']),
            'twilioNumbers' => $twilioNumbers,
            'twilioConfig' => $twilioConfig,
            // Whether the locked provider has credentials to work with —
            // what the page gates its whole body on. Twilio's answer is
            // the presence of the configuration it has always been given
            // here, so nothing about a Twilio installation's page moves;
            // Plivo's is its saved account.
            'providerConfigured' => $isPlivo ? $plivoCredentials !== null : $twilioConfig !== null,
            'users' => $users,
        ]);
    }

    /**
     * This installation's Plivo account credentials, or null when it has
     * none saved yet. Read-only, and deliberately not
     * TelephonyProviderConfig::forProvider(), which would create the row:
     * rendering a page must not write one. Mirrors how PhoneNumberService
     * resolves the same credentials.
     *
     * @return array{auth_id: string, auth_token: string}|null
     */
    private function getPlivoCredentials(): ?array
    {
        $config = TelephonyProviderConfig::where('name', TelephonyProvider::PLIVO->value)->first();
        $authId = $config?->credential('auth_id');
        $authToken = $config?->credential('auth_token');

        if ($authId === null || $authToken === null) {
            return null;
        }

        return ['auth_id' => $authId, 'auth_token' => $authToken];
    }

    /**
     * Plivo's answer to getTwilioNumbers(): the numbers this installation's
     * account already owns, in the shape the page's account table reads.
     *
     * Guarded the same way — a provider that cannot be reached logs and
     * yields an empty list, never a 500 on the admin's inventory page.
     *
     * Two deliberate differences from the Twilio branch, both because
     * Plivo works differently rather than because anything is missing:
     * a Plivo number carries no per-number voice webhook (it is attached
     * to this installation's one Application, whose id is reported here
     * for the same reason Twilio reports the TwiML app it matched), so
     * every listed number is already configured; and Plivo spells numbers
     * without a leading '+', which has to become E.164 before the
     * assignment lookup below can match a row in phone_numbers.
     *
     * @param  array{auth_id: string, auth_token: string}|null  $credentials
     */
    private function getPlivoNumbers(?array $credentials): array
    {
        if ($credentials === null) {
            Log::warning('No Plivo credentials saved for this installation');

            return [];
        }

        try {
            $numbers = $this->telephony->phoneNumbers()->initialize($credentials)->listNumbers();
        } catch (\Exception $e) {
            Log::error('Failed to fetch Plivo numbers', [
                'error' => $e->getMessage(),
                'class' => get_class($e),
            ]);

            return [];
        }

        $applicationId = TelephonyProviderConfig::where('name', TelephonyProvider::PLIVO->value)->value('app_id');

        return array_map(function (array $number) use ($applicationId) {
            $phoneNumber = PhoneNumberHelper::toE164((string) ($number['number'] ?? ''));

            return [
                'sid' => (string) ($number['number_sid'] ?? $number['number'] ?? ''),
                'phone_number' => $phoneNumber,
                'friendly_name' => ($number['friendly_name'] ?? '') ?: $phoneNumber,
                'voice_application_sid' => $applicationId,
                'voice_url' => null,
                'status_callback' => null,
                'capabilities' => [
                    'voice' => $this->toBool($number['capabilities']['voice'] ?? false),
                    'sms' => $this->toBool($number['capabilities']['sms'] ?? false),
                    'mms' => $this->toBool($number['capabilities']['mms'] ?? false),
                ],
                'is_configured' => true,
            ];
        }, $numbers);
    }

    /**
     * Plivo's SDK documents its capability fields as compared against the
     * literal string 'true', and the literal string 'false' is truthy in
     * PHP — so "this number supports SMS" is never asserted on anything
     * looser than an explicit true. Same rule, and the same reason, as
     * PhoneNumberService::toBool().
     */
    private function toBool(mixed $value): bool
    {
        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['true', '1'], true);
        }

        return (bool) $value;
    }

    /**
     * Get Twilio numbers with configuration status
     */
    private function getTwilioNumbers()
    {
        try {
            $config = \App\Models\TwilioGlobalConfig::active();
            
            if (!$config) {
                Log::warning('No active Twilio configuration found');
                return [];
            }

            if (!$config->account_sid || !$config->auth_token) {
                Log::error('Twilio configuration missing credentials');
                return [];
            }

            $client = new \Twilio\Rest\Client(
                $config->account_sid,
                $config->getDecryptedAuthToken()
            );

            // Fetch latest numbers from Twilio API
            $numbers = $client->incomingPhoneNumbers->read();
            
            if (empty($numbers)) {
                Log::info('No phone numbers found in Twilio account', [
                    'account_sid' => $config->account_sid
                ]);
                return [];
            }
            
            Log::info('Fetching Twilio numbers', [
                'account_sid' => $config->account_sid,
                'count' => count($numbers)
            ]);
            
            $twilioNumbers = [];
            foreach ($numbers as $number) {
                $twilioNumbers[] = [
                    'sid' => $number->sid,
                    'phone_number' => $number->phoneNumber,
                    'friendly_name' => $number->friendlyName ?? $number->phoneNumber,
                    'voice_application_sid' => $number->voiceApplicationSid,
                    'voice_url' => $number->voiceUrl,
                    'status_callback' => $number->statusCallback,
                    'capabilities' => [
                        'voice' => $number->capabilities->voice ?? false,
                        'sms' => $number->capabilities->sms ?? false,
                        'mms' => $number->capabilities->mms ?? false,
                    ],
                    'is_configured' => $number->voiceApplicationSid === $config->twiml_app_sid,
                ];
            }

            Log::info('Twilio numbers fetched successfully', [
                'count' => count($twilioNumbers),
                'account_sid' => $config->account_sid
            ]);
            return $twilioNumbers;
        } catch (\Twilio\Exceptions\AuthenticationException $e) {
            Log::error('Twilio authentication failed', [
                'error' => $e->getMessage(),
                'status' => $e->getStatusCode()
            ]);
            return [];
        } catch (\Exception $e) {
            Log::error('Failed to fetch Twilio numbers', [
                'error' => $e->getMessage(),
                'class' => get_class($e)
            ]);
            return [];
        }
    }

    /**
     * Get Twilio configuration
     */
    private function getTwilioConfig()
    {
        $config = \App\Models\TwilioGlobalConfig::active();
        
        if (!$config) {
            return null;
        }

        return [
            'account_sid' => $config->account_sid,
            'twiml_app_sid' => $config->twiml_app_sid,
            'webhook_url' => $config->webhook_url,
            'is_active' => $config->is_active,
        ];
    }

    /**
     * Sync Twilio numbers to database
     * This runs automatically when viewing the inventory page
     */
    private function syncTwilioNumbersToDatabase($twilioNumbers)
    {
        if (empty($twilioNumbers)) {
            return;
        }

        try {
            $config = \App\Models\TwilioGlobalConfig::active();
            if (!$config) {
                return;
            }

            // Create Twilio client to fetch pricing
            $twilioClient = new \Twilio\Rest\Client(
                $config->account_sid,
                $config->getDecryptedAuthToken()
            );

            // Group numbers by country code to fetch pricing efficiently
            $pricingCache = [];

            foreach ($twilioNumbers as $twilioNumber) {
                // Determine country code from phone number
                $countryCode = 'US'; // Default
                if (substr($twilioNumber['phone_number'], 1, 2) === '1') {
                    $countryCode = 'US';
                } elseif (substr($twilioNumber['phone_number'], 1, 2) === '44') {
                    $countryCode = 'GB';
                } elseif (substr($twilioNumber['phone_number'], 1, 2) === '61') {
                    $countryCode = 'AU';
                } elseif (substr($twilioNumber['phone_number'], 1, 2) === '91') {
                    $countryCode = 'IN';
                }

                // Fetch pricing for this country if not cached
                if (!isset($pricingCache[$countryCode])) {
                    try {
                        $pricing = $twilioClient->pricing->v2->phoneNumbers
                            ->countries($countryCode)
                            ->fetch();
                        
                        $monthlyPrice = 1.00; // Default
                        if (isset($pricing->phoneNumberPrices) && count($pricing->phoneNumberPrices) > 0) {
                            $firstPrice = $pricing->phoneNumberPrices[0];
                            $monthlyPrice = (float) $firstPrice->currentPrice;
                        }
                        $pricingCache[$countryCode] = $monthlyPrice;
                    } catch (\Exception $e) {
                        Log::warning('Failed to fetch pricing for country', [
                            'country' => $countryCode,
                            'error' => $e->getMessage()
                        ]);
                        $pricingCache[$countryCode] = 1.00;
                    }
                }

                PhoneNumber::updateOrCreate(
                    ['number' => $twilioNumber['phone_number']],
                    [
                        'twilio_sid' => $twilioNumber['sid'],
                        'formatted_number' => $twilioNumber['phone_number'],
                        'friendly_name' => $twilioNumber['friendly_name'],
                        'country_code' => $countryCode,
                        'area_code' => PhoneNumberHelper::extractAreaCode($twilioNumber['phone_number']),
                        'status' => PhoneNumber::where('number', $twilioNumber['phone_number'])->value('status') ?? 'available',
                        'capabilities' => $twilioNumber['capabilities'],
                        'monthly_cost' => $pricingCache[$countryCode],
                    ]
                );
            }

            Log::info('Twilio numbers synced to database', [
                'count' => count($twilioNumbers),
                'account_sid' => $config->account_sid
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to sync Twilio numbers to database', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Sync available numbers from Twilio
     */
    public function sync(Request $request)
    {
        $this->authorize('sync', PhoneNumber::class);

        $request->validate([
            'country_code' => 'required|string|size:2',
            'area_code' => 'nullable|string|max:10',
        ]);

        try {
            $numbers = $this->phoneNumberService->syncAvailableNumbers(
                $request->country_code,
                $request->area_code
            );

            // Check if no numbers found
            if (empty($numbers)) {
                return response()->json([
                    'success' => true,
                    'message' => 'No numbers found for the selected country/area code.',
                    'numbers' => [],
                ]);
            }

            // Format numbers for response - use collect() to ensure it's a collection
            $syncedNumbers = collect($numbers)->map(function ($number) {
                return [
                    'id' => $number->id,
                    'number' => $number->number,
                    'formatted_number' => $number->formatted_number,
                    'friendly_name' => $number->friendly_name,
                    'area_code' => $number->area_code,
                    'capabilities' => $number->capabilities,
                ];
            });

            return response()->json([
                'success' => true,
                'message' => count($numbers) . ' numbers synced successfully.',
                'numbers' => $syncedNumbers,
            ]);
        } catch (\Twilio\Exceptions\RestException $e) {
            // Handle Twilio-specific errors
            Log::warning('Twilio sync error', [
                'error' => $e->getMessage(),
                'code' => $e->getStatusCode(),
                'country_code' => $request->country_code,
                'area_code' => $request->area_code,
            ]);

            // Check if it's a 404 (resource not found - no numbers available)
            if ($e->getStatusCode() == 404) {
                return response()->json([
                    'success' => true,
                    'message' => 'No numbers found for the selected country/area code.',
                    'numbers' => [],
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync numbers: ' . $e->getMessage(),
            ], 500);
        } catch (\Exception $e) {
            Log::error('Failed to sync numbers', [
                'error' => $e->getMessage(),
                'country_code' => $request->country_code,
                'area_code' => $request->area_code,
            ]);
            
            // Check if error message contains "not found" or "404"
            if (stripos($e->getMessage(), 'not found') !== false || stripos($e->getMessage(), '404') !== false) {
                return response()->json([
                    'success' => true,
                    'message' => 'No numbers found for the selected country/area code.',
                    'numbers' => [],
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync numbers: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Configure selected phone numbers
     */
    public function configure(Request $request)
    {
        $this->authorize('sync', PhoneNumber::class);

        $request->validate([
            'phone_numbers' => 'required|array|min:1',
            'phone_numbers.*' => 'required|string',
        ]);

        if ($this->telephony->provider() === TelephonyProvider::PLIVO) {
            return $this->configureForPlivo($request->phone_numbers);
        }

        $config = \App\Models\TwilioGlobalConfig::active();

        if (!$config) {
            return redirect()->back()->with('error', 'No active Twilio configuration found. Please configure Twilio in Settings.');
        }

        if (!$config->twiml_app_sid) {
            return redirect()->back()->with('error', 'TwiML Application not configured. Please run: php artisan twilio:configure');
        }

        try {
            $client = new \Twilio\Rest\Client(
                $config->account_sid,
                $config->getDecryptedAuthToken()
            );

            $configured = 0;
            $errors = [];

            foreach ($request->phone_numbers as $phoneNumber) {
                try {
                    // Find the phone number SID
                    $numbers = $client->incomingPhoneNumbers->read(['phoneNumber' => $phoneNumber]);
                    
                    if (empty($numbers)) {
                        $errors[] = "Phone number {$phoneNumber} not found";
                        continue;
                    }

                    $numberSid = $numbers[0]->sid;

                    // Update to use TwiML Application
                    $client->incomingPhoneNumbers($numberSid)
                        ->update([
                            'voiceApplicationSid' => $config->twiml_app_sid,
                        ]);

                    $configured++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to configure {$phoneNumber}: " . $e->getMessage();
                }
            }

            if ($configured > 0) {
                $message = "Successfully configured {$configured} phone number(s) for Teleman AI WebRTC";
                if (!empty($errors)) {
                    $message .= '. Some errors: ' . implode(', ', $errors);
                }
                return redirect()->back()->with('success', $message);
            } else {
                return redirect()->back()->with('error', 'Failed to configure phone numbers: ' . implode(', ', $errors));
            }
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to configure phone numbers: ' . $e->getMessage());
        }
    }

    /**
     * Plivo's mirror of the Twilio branch above: attach each selected
     * number to this installation's Application. Unlike Twilio, which
     * looks numbers up live against the Twilio account itself, this
     * resolves each string against the local phone_numbers table —
     * PhoneNumberService::configureForWebRTC() needs a PhoneNumber model,
     * and there is no equivalent "search Plivo's own inventory by number"
     * call already wired up worth adding just for this lookup.
     *
     * @param  array<int, string>  $phoneNumbers
     */
    private function configureForPlivo(array $phoneNumbers)
    {
        $configured = 0;
        $errors = [];

        foreach ($phoneNumbers as $phoneNumberString) {
            $phoneNumber = PhoneNumber::where('number', $phoneNumberString)->first();

            if (!$phoneNumber) {
                $errors[] = "Phone number {$phoneNumberString} not found";
                continue;
            }

            try {
                $this->phoneNumberService->configureForWebRTC($phoneNumber, null);
                $configured++;
            } catch (\Exception $e) {
                $errors[] = "Failed to configure {$phoneNumberString}: " . $e->getMessage();
            }
        }

        if ($configured > 0) {
            $message = "Successfully configured {$configured} phone number(s) for Teleman AI WebRTC";
            if (!empty($errors)) {
                $message .= '. Some errors: ' . implode(', ', $errors);
            }

            return redirect()->back()->with('success', $message);
        }

        return redirect()->back()->with('error', 'Failed to configure phone numbers: ' . implode(', ', $errors));
    }

    /**
     * Release a phone number back to Twilio
     */
    public function release(PhoneNumber $phoneNumber)
    {
        $this->authorize('release', $phoneNumber);

        try {
            $this->phoneNumberService->releaseNumber($phoneNumber);

            return redirect()->back()->with('success', 'Phone number released successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to release number: ' . $e->getMessage());
        }
    }

    /**
     * Revoke/unassign a phone number from user (keeps in inventory)
     */
    public function revoke(PhoneNumber $phoneNumber)
    {
        $this->authorize('release', $phoneNumber); // Reuse release permission

        try {
            $userName = $phoneNumber->user ? $phoneNumber->user->name : 'user';
            $this->phoneNumberService->revokeNumber($phoneNumber);

            return redirect()->back()->with('success', "Phone number revoked from {$userName} successfully.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to revoke number: ' . $e->getMessage());
        }
    }

    /**
     * Get available numbers (API endpoint)
     */
    public function available(Request $request)
    {
        $this->authorize('viewAny', PhoneNumber::class);

        $numbers = $this->phoneNumberService->getAvailableNumbers(
            $request->country_code,
            $request->area_code,
            $request->has_voice ?? null,
            $request->has_sms ?? null
        );

        return response()->json([
            'numbers' => $numbers,
        ]);
    }

    /**
     * Show statistics (API endpoint)
     */
    public function statistics()
    {
        $this->authorize('viewCosts', PhoneNumber::class);

        return response()->json([
            'statistics' => $this->phoneNumberService->getStatistics(),
        ]);
    }

    /**
     * Get assignment price information for confirmation dialog
     */
    public function getAssignPrice(Request $request)
    {
        $this->authorize('sync', PhoneNumber::class);

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'phone_number_id' => 'nullable|exists:phone_numbers,id',
            'phone_number' => ['nullable', 'string', 'regex:/^\+[1-9]\d{1,14}$/'],
        ]);

        $user = \App\Models\User::findOrFail($request->user_id);
        $isPlivo = $this->telephony->provider() === TelephonyProvider::PLIVO;

        // Resolve the phone number
        $needsPurchase = false;
        $countryCode = 'US';
        $phoneNumberDisplay = '';
        $alreadyOwned = false;

        if ($request->filled('phone_number_id')) {
            $phoneNumber = PhoneNumber::findOrFail($request->phone_number_id);
            $countryCode = $phoneNumber->country_code ?? 'US';
            $phoneNumberDisplay = $phoneNumber->formatted_number ?? $phoneNumber->number;
            $needsPurchase = $isPlivo ? !$phoneNumber->provider_number_sid : !$phoneNumber->twilio_sid;

            // Check if Twilio already owns this number. Plivo has no
            // equivalent live pre-check here: provider_number_sid,
            // checked just above, is already the authoritative local
            // answer for "does this installation own it".
            if (!$isPlivo && !$phoneNumber->twilio_sid) {
                try {
                    $config = \App\Models\TwilioGlobalConfig::active();
                    if ($config) {
                        $client = new \Twilio\Rest\Client($config->account_sid, $config->getDecryptedAuthToken());
                        $twilioNumbers = $client->incomingPhoneNumbers->read(['phoneNumber' => $phoneNumber->number]);
                        if (!empty($twilioNumbers)) {
                            $alreadyOwned = true;
                            $needsPurchase = false;
                        }
                    }
                } catch (\Exception $e) {
                    // Ignore - we'll try to purchase later
                }
            }
        } elseif ($request->filled('phone_number')) {
            $phoneNumberDisplay = $request->phone_number;
            $existing = PhoneNumber::where('number', $request->phone_number)->first();
            if ($existing) {
                $countryCode = $existing->country_code ?? 'US';
                $needsPurchase = $isPlivo ? !$existing->provider_number_sid : !$existing->twilio_sid;
            } else {
                $needsPurchase = true;
            }
        }

        // Calculate pricing
        $pricing = $this->pricingService->calculatePhoneNumberCost($countryCode);
        $chargeAmount = $pricing['charge'] ?? 1.00;
        $monthlyCost = $pricing['base_cost'] ?? 1.00;

        return response()->json([
            'needs_purchase' => $needsPurchase,
            'already_owned' => $alreadyOwned,
            'monthly_cost' => $monthlyCost,
            'charge_amount' => round($chargeAmount, 2),
            'user_balance' => round($user->credit_balance, 2),
            'can_afford' => $this->creditService->canAfford($user, $chargeAmount),
            'user_name' => $user->name,
            'phone_number' => $phoneNumberDisplay,
            'country_code' => $countryCode,
        ]);
    }

    /**
     * Manually assign a phone number to a user
     */
    public function assignNumber(Request $request)
    {
        $this->authorize('sync', PhoneNumber::class);

        // Clean up empty strings to null BEFORE validation
        if ($request->phone_number_id === '' || $request->phone_number_id === null) {
            $request->merge(['phone_number_id' => null]);
        }
        if ($request->phone_number === '' || $request->phone_number === null) {
            $request->merge(['phone_number' => null]);
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'phone_number_id' => 'nullable|exists:phone_numbers,id',
            'phone_number' => ['nullable', 'string', 'regex:/^\+[1-9]\d{1,14}$/'],
            'friendly_name' => 'nullable|string|max:255',
            'skip_credit_deduction' => 'nullable|boolean',
        ]);

        // Ensure at least one of phone_number_id or phone_number is provided
        if (!$request->filled('phone_number_id') && !$request->filled('phone_number')) {
            return redirect()->back()->with('error', 'Please provide either an existing phone number or a new phone number to purchase.');
        }

        try {
            $user = \App\Models\User::findOrFail($request->user_id);
            $skipCreditDeduction = (bool) $request->input('skip_credit_deduction', false);
            
            Log::info('Assigning phone number', [
                'user_id' => $request->user_id,
                'phone_number_id' => $request->phone_number_id,
                'phone_number' => $request->phone_number,
                'friendly_name' => $request->friendly_name,
                'skip_credit_deduction' => $skipCreditDeduction,
            ]);
            
            // Use database transaction for atomic operation
            $result = DB::transaction(function () use ($request, $user, $skipCreditDeduction) {
                $phoneNumber = null;
                $wasPurchased = false;

                // Resolve the phone number record
                if ($request->filled('phone_number_id')) {
                    $phoneNumber = PhoneNumber::where('id', $request->phone_number_id)
                        ->lockForUpdate()
                        ->firstOrFail();
                    
                    if ($phoneNumber->status !== 'available') {
                        throw new \Exception('This number is not available for assignment.');
                    }
                } elseif ($request->filled('phone_number')) {
                    $existing = PhoneNumber::where('number', $request->phone_number)
                        ->lockForUpdate()
                        ->first();
                    
                    if ($existing) {
                        if ($existing->status !== 'available') {
                            throw new \Exception('This number is already assigned to another user.');
                        }
                        $phoneNumber = $existing;
                    }
                }

                $provider = $this->telephony->provider();
                $isPlivo = $provider === \App\Enums\TelephonyProvider::PLIVO;

                // Step 1: Check if a purchase from the locked provider is needed.
                $needsPurchase = false;
                $alreadyPurchased = $phoneNumber && ($isPlivo ? $phoneNumber->provider_number_sid : $phoneNumber->twilio_sid);

                if ($phoneNumber && !$alreadyPurchased) {
                    if ($isPlivo) {
                        // No live "already owned at Plivo" pre-check:
                        // purchaseNumber() below surfaces a clear Plivo
                        // error if this account already owns the number,
                        // rather than silently adopting it the way the
                        // Twilio branch does.
                        $needsPurchase = true;
                    } else {
                        // Check if Twilio already owns this number
                        $config = \App\Models\TwilioGlobalConfig::active();
                        if ($config) {
                            try {
                                $client = new \Twilio\Rest\Client($config->account_sid, $config->getDecryptedAuthToken());
                                $twilioNumbers = $client->incomingPhoneNumbers->read(['phoneNumber' => $phoneNumber->number]);
                                if (!empty($twilioNumbers)) {
                                    $phoneNumber->twilio_sid = $twilioNumbers[0]->sid;
                                    $phoneNumber->save();
                                } else {
                                    $needsPurchase = true;
                                }
                            } catch (\Exception $e) {
                                $needsPurchase = true;
                            }
                        }
                    }
                } elseif (!$phoneNumber) {
                    // New number - needs purchase
                    $needsPurchase = true;
                }

                // Early affordability check, before Step 2 spends real
                // money at the provider. A provider purchase cannot be
                // undone by this method later deciding the customer
                // cannot afford it — the transaction rollback that
                // follows only discards the *local* row, never the
                // charge already made at Twilio or Plivo — so the
                // customer's balance must be confirmed before that
                // purchase is attempted, not only after.
                //
                // $predictedCountryCode reproduces exactly what Step 4
                // below already computes, without requiring the purchase
                // to have happened first. When $needsPurchase is true,
                // PhoneNumberService::purchaseNumber() (Twilio) and
                // ::purchaseNumberFromPlivo() (Plivo) both end up writing
                // 'US' regardless of which of their own branches actually
                // runs — a fresh buy always does, and Plivo's
                // already-owned adoption path (recordPlivoNumber())
                // writes the same literal 'US' rather than leaving the
                // column alone. When $needsPurchase is false because this
                // step's own Twilio-adoption branch above just adopted an
                // existing row, country_code is genuinely untouched, so
                // the existing value is correct unchanged. Either way this
                // predicts the same value Step 4 would use, not an
                // approximation of it. Step 4's own check is left
                // completely unchanged as the authoritative one; this can
                // only fail faster than it did before, never differently.
                if (!$skipCreditDeduction) {
                    $predictedCountryCode = $needsPurchase ? 'US' : ($phoneNumber->country_code ?? 'US');
                    $predictedPricing = $this->pricingService->calculatePhoneNumberCost($predictedCountryCode);
                    $predictedCharge = $predictedPricing['charge'] ?? 1.00;

                    if (!$this->creditService->canAfford($user, $predictedCharge)) {
                        throw new \Exception(
                            sprintf(
                                'User has insufficient credits. Balance: $%.2f, Required: $%.2f. Use "Skip credit deduction" to assign without charging.',
                                $user->credit_balance,
                                $predictedCharge
                            )
                        );
                    }
                }

                // Step 2: Purchase from the locked provider if needed.
                // purchaseNumber() itself branches on the locked provider.
                if ($needsPurchase) {
                    $numberString = $phoneNumber ? $phoneNumber->number : $request->phone_number;

                    $purchased = $this->phoneNumberService->purchaseNumber(
                        $numberString,
                        $request->friendly_name
                    );

                    if (!$phoneNumber) {
                        $phoneNumber = $purchased;
                    } else {
                        $phoneNumber->refresh();
                    }

                    $wasPurchased = true;
                }

                // Step 3: Configure for WebRTC (Twilio) / the Plivo
                // Application — a self-healing step regardless of whether
                // this request just purchased the number.
                if ($isPlivo) {
                    try {
                        $this->phoneNumberService->configureForWebRTC($phoneNumber, null);
                    } catch (\Exception $e) {
                        Log::warning('Failed to configure for the Plivo application during assign', [
                            'number' => $phoneNumber->number,
                            'error' => $e->getMessage(),
                        ]);
                    }
                } else {
                    $config = \App\Models\TwilioGlobalConfig::active();
                    if ($config && $config->twiml_app_sid && $phoneNumber->twilio_sid) {
                        try {
                            $this->phoneNumberService->configureForWebRTC($phoneNumber, $config->twiml_app_sid);
                        } catch (\Exception $e) {
                            Log::warning('Failed to configure for WebRTC during assign', [
                                'number' => $phoneNumber->number,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }

                // Step 4: Deduct credits from target user (unless skipped by admin)
                $transaction = null;
                if (!$skipCreditDeduction) {
                    $countryCode = $phoneNumber->country_code ?? 'US';
                    $pricing = $this->pricingService->calculatePhoneNumberCost($countryCode);
                    $customerCharge = $pricing['charge'] ?? 1.00;
                    $actualCost = $pricing['base_cost'] ?? 1.00;

                    if (!$this->creditService->canAfford($user, $customerCharge)) {
                        throw new \Exception(
                            sprintf(
                                'User has insufficient credits. Balance: $%.2f, Required: $%.2f. Use "Skip credit deduction" to assign without charging.',
                                $user->credit_balance,
                                $customerCharge
                            )
                        );
                    }

                    $transaction = $this->creditService->deductCredit(
                        $user,
                        $customerCharge,
                        sprintf('Phone number purchase: %s (assigned by admin)', $phoneNumber->number),
                        [
                            'actual_cost' => $actualCost,
                            'profit_amount' => round($customerCharge - $actualCost, 4),
                            'profit_percentage' => $actualCost > 0 ? round((($customerCharge - $actualCost) / $actualCost) * 100, 2) : 0,
                            'service_type' => 'phone_number',
                            'reference_type' => PhoneNumber::class,
                            'reference_id' => $phoneNumber->id,
                            'metadata' => [
                                'phone_number_id' => $phoneNumber->id,
                                'phone_number' => $phoneNumber->number,
                                'country_code' => $countryCode,
                                'assigned_by_admin' => true,
                                'admin_id' => auth()->id(),
                            ],
                        ]
                    );

                    Log::info('Credits deducted for admin-assigned number', [
                        'number' => $phoneNumber->number,
                        'user_id' => $user->id,
                        'amount' => $customerCharge,
                        'transaction_id' => $transaction->id,
                    ]);
                }

                // Step 5: Assign the number
                $phoneNumber->update([
                    'user_id' => $user->id,
                    'status' => 'assigned',
                    'assigned_at' => now(),
                    'approved_by_id' => auth()->id(),
                ]);

                $chargeMsg = $skipCreditDeduction
                    ? ' (no credit charge)'
                    : ($transaction ? sprintf(' ($%.2f charged)', $transaction->amount) : '');

                $purchaseMsg = $wasPurchased ? 'purchased, ' : '';

                return [
                    'success' => true,
                    'message' => sprintf(
                        'Phone number %s %sassigned to %s successfully%s.',
                        $phoneNumber->formatted_number ?? $phoneNumber->number,
                        $purchaseMsg,
                        $user->name,
                        $chargeMsg
                    ),
                ];
            });
            
            return redirect()->back()->with('success', $result['message']);
            
        } catch (\Exception $e) {
            Log::error('Failed to assign phone number: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to assign number: ' . $e->getMessage());
        }
    }

    /**
     * Sync pricing for all phone numbers from Twilio
     */
    public function syncPricing()
    {
        try {
            $config = TwilioGlobalConfig::active();
            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => 'Twilio configuration not found',
                ], 404);
            }

            // Get all unique country codes from phone numbers
            $countryCodes = PhoneNumber::select('country_code')
                ->distinct()
                ->pluck('country_code')
                ->toArray();

            if (empty($countryCodes)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No phone numbers found to update',
                ]);
            }

            $updated = 0;
            $errors = [];

            // Fetch pricing and update numbers for each country
            foreach ($countryCodes as $countryCode) {
                try {
                    $response = \Illuminate\Support\Facades\Http::withBasicAuth(
                        $config->account_sid,
                        $config->getDecryptedAuthToken()
                    )->get("https://pricing.twilio.com/v1/PhoneNumbers/Countries/{$countryCode}");

                    if ($response->successful()) {
                        $data = $response->json();
                        $phoneNumberPrices = $data['phone_number_prices'] ?? [];
                        
                        if (!empty($phoneNumberPrices)) {
                            $monthlyPrice = null;
                            foreach ($phoneNumberPrices as $priceData) {
                                if (isset($priceData['base_price'])) {
                                    $monthlyPrice = (float) $priceData['base_price'];
                                    break;
                                }
                            }

                            if ($monthlyPrice !== null && $monthlyPrice > 0) {
                                $count = PhoneNumber::where('country_code', $countryCode)->count();
                                PhoneNumber::where('country_code', $countryCode)
                                    ->update(['monthly_cost' => $monthlyPrice]);
                                $updated += $count;
                                
                                // Update cache
                                \Illuminate\Support\Facades\Cache::put(
                                    "twilio_pricing_{$countryCode}",
                                    $monthlyPrice,
                                    now()->addHours(24)
                                );
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $errors[] = "{$countryCode}: {$e->getMessage()}";
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Updated pricing for {$updated} phone numbers",
                'updated' => $updated,
                'errors' => $errors,
            ]);

        } catch (\Exception $e) {
            Log::error('Error syncing phone number pricing', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to sync pricing: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get list of countries where Twilio supports phone numbers
     */
    public function getSupportedCountries()
    {
        try {
            $config = \App\Models\TwilioGlobalConfig::active();
            
            if (!$config) {
                return response()->json([
                    'countries' => []
                ]);
            }

            $client = new \Twilio\Rest\Client(
                $config->account_sid,
                $config->getDecryptedAuthToken()
            );

            // Fetch all supported countries using availablePhoneNumbers
            $countries = $client->availablePhoneNumbers->read();

            // Transform to a clean array with country code and name
            $countryList = array_map(function($country) {
                return [
                    'code' => $country->countryCode,
                    'name' => $country->country,
                ];
            }, $countries);

            // Sort by name
            usort($countryList, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });

            return response()->json([
                'countries' => $countryList
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to fetch supported countries', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'countries' => []
            ], 500);
        }
    }
}
