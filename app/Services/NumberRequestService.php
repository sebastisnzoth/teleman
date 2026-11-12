<?php

namespace App\Services;

use App\Enums\TelephonyProvider;
use App\Models\NumberRequest;
use App\Models\PhoneNumber;
use App\Models\TwilioGlobalConfig;
use App\Models\User;
use App\Notifications\NumberRequestApproved;
use App\Notifications\NumberRequestCreated;
use App\Notifications\NumberRequestRejected;
use App\Services\Telephony\TelephonyManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Twilio\Rest\Client as TwilioClient;

class NumberRequestService
{
    protected $creditService;

    protected $pricingService;

    /**
     * Delegated to for the Plivo purchase path only (see
     * purchaseNumberFromTwilio() below for why Twilio's own path stays
     * separate and untouched) — reuses PhoneNumberService's Application-
     * attachment logic rather than duplicating it here.
     */
    public function __construct(
        CreditService $creditService,
        PricingService $pricingService,
        private readonly TelephonyManager $telephony,
        private readonly PhoneNumberService $phoneNumberService,
    ) {
        $this->creditService = $creditService;
        $this->pricingService = $pricingService;
    }

    /**
     * Create a number request with instant purchase and assignment
     * Purchases from Twilio immediately, charges customer, and assigns number
     */
    public function createRequest($phoneNumber, $customer, $notes = null)
    {
        if ($phoneNumber instanceof PhoneNumber) {
            $phoneNumberModel = $phoneNumber;
        } else {
            $phoneNumberModel = PhoneNumber::findOrFail($phoneNumber);
        }

        // Verify number is available
        if ($phoneNumberModel->status !== 'available') {
            throw new \Exception('This phone number is not available for request.');
        }

        $customerId = $customer->id ?? $customer;
        $customerModel = $customer instanceof User ? $customer : User::findOrFail($customerId);

        // Check if customer already has a pending/approved request for this number
        $existingRequest = NumberRequest::where('phone_number_id', $phoneNumberModel->id)
            ->where('customer_id', $customerId)
            ->whereIn('status', ['pending', 'approved'])
            ->first();

        if ($existingRequest) {
            throw new \Exception('You already have a request for this number.');
        }

        // Calculate costs
        $countryCode = $phoneNumberModel->country_code ?? 'US';
        $pricing = $this->pricingService->calculatePhoneNumberCost($countryCode);
        $customerCharge = $pricing['charge'] ?? 1.00; // What customer pays
        $actualCost = $pricing['base_cost'] ?? 1.00; // What Twilio charges us

        // Check if customer has sufficient balance
        if (! $this->creditService->canAfford($customerModel, $customerCharge)) {
            throw new \Exception(
                sprintf(
                    'Insufficient credit balance. You need $%.2f but have $%.2f. Please add credits to continue.',
                    $customerCharge,
                    $customerModel->credit_balance
                )
            );
        }

        // Use database transaction to ensure atomicity
        return DB::transaction(function () use (
            $phoneNumberModel,
            $customerId,
            $customerModel,
            $notes,
            $customerCharge,
            $actualCost,
            $countryCode
        ) {
            try {
                // CRITICAL: Lock the phone number record to prevent race conditions
                $phoneNumberModel = PhoneNumber::where('id', $phoneNumberModel->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                // Double-check availability after lock (prevent race condition)
                if ($phoneNumberModel->status !== 'available') {
                    throw new \Exception('This phone number is no longer available. It may have been purchased by another user.');
                }

                // Mark as being processed to prevent duplicate purchases
                $phoneNumberModel->update([
                    'status' => 'requested',
                    'requested_by' => $customerId,
                ]);

                // Step 1: Purchase from the locked provider if not already
                // purchased. The Plivo branch is new; the Twilio branch
                // below is untouched from before this method knew about
                // Plivo.
                if ($this->telephony->provider() === TelephonyProvider::PLIVO) {
                    if (! $phoneNumberModel->provider_number_sid) {
                        Log::info('Purchasing number from Plivo', [
                            'number' => $phoneNumberModel->number,
                            'customer_id' => $customerId,
                        ]);

                        $this->phoneNumberService->purchaseNumber($phoneNumberModel, $phoneNumberModel->friendly_name);

                        $phoneNumberModel->refresh();

                        if (! $phoneNumberModel->provider_number_sid) {
                            throw new \Exception('Plivo purchase completed but the number identifier was not saved. Please contact support.');
                        }
                    }
                } elseif (! $phoneNumberModel->twilio_sid) {
                    Log::info('Purchasing number from Twilio', [
                        'number' => $phoneNumberModel->number,
                        'customer_id' => $customerId,
                    ]);

                    $this->purchaseNumberFromTwilio($phoneNumberModel);

                    // Reload to get the updated twilio_sid
                    $phoneNumberModel->refresh();

                    // Verify purchase was successful
                    if (! $phoneNumberModel->twilio_sid) {
                        throw new \Exception('Twilio purchase completed but SID was not saved. Please contact support.');
                    }
                }

                // Step 2: Deduct credits from customer ONLY after successful Twilio purchase
                $transaction = $this->creditService->deductCredit(
                    $customerModel,
                    $customerCharge,
                    sprintf('Phone number purchase: %s', $phoneNumberModel->number),
                    [
                        'actual_cost' => $actualCost,
                        'profit_amount' => round($customerCharge - $actualCost, 4),
                        'profit_percentage' => $actualCost > 0 ? round((($customerCharge - $actualCost) / $actualCost) * 100, 2) : 0,
                        'service_type' => 'phone_number',
                        'reference_type' => PhoneNumber::class,
                        'reference_id' => $phoneNumberModel->id,
                        'metadata' => [
                            'phone_number_id' => $phoneNumberModel->id,
                            'phone_number' => $phoneNumberModel->number,
                            'country_code' => $countryCode,
                            'twilio_sid' => $phoneNumberModel->twilio_sid,
                            'purchase_type' => 'instant',
                        ],
                    ]
                );

                Log::info('Credits deducted for phone number purchase', [
                    'number' => $phoneNumberModel->number,
                    'customer_id' => $customerId,
                    'amount' => $customerCharge,
                    'transaction_id' => $transaction->id,
                ]);

                // Step 3: Create approved request record (auto-approved)
                $request = NumberRequest::create([
                    'phone_number_id' => $phoneNumberModel->id,
                    'customer_id' => $customerId,
                    'status' => 'approved', // Auto-approved
                    'customer_notes' => $notes,
                    'requested_at' => now(),
                    'processed_at' => now(), // Processed immediately
                    'admin_notes' => sprintf(
                        'Auto-approved via instant purchase. Charged: $%.2f',
                        $customerCharge
                    ),
                ]);

                // Step 4: Assign number to customer immediately
                $phoneNumberModel->update([
                    'status' => 'assigned',
                    'user_id' => $customerId,
                    'requested_by' => $customerId,
                    'assigned_at' => now(),
                    'monthly_cost' => $actualCost, // Store actual cost for future charges
                ]);

                // Step 5: Send success notification to customer
                try {
                    $customerModel->notify(new NumberRequestApproved($request));
                } catch (\Exception $e) {
                    Log::error('Failed to send instant purchase notification to customer', [
                        'error' => $e->getMessage(),
                        'customer_id' => $customerId,
                    ]);
                }

                // Step 6: Notify admin of instant purchase (informational only)
                try {
                    $this->notifyAdminOfInstantPurchase($request);
                } catch (\Exception $e) {
                    Log::error('Failed to notify admin of instant purchase', [
                        'error' => $e->getMessage(),
                    ]);
                }

                Log::info('Phone number instantly purchased and assigned successfully', [
                    'number' => $phoneNumberModel->number,
                    'customer_id' => $customerId,
                    'twilio_sid' => $phoneNumberModel->twilio_sid,
                    'charge' => $customerCharge,
                    'transaction_id' => $transaction->id,
                ]);

                return $request;

            } catch (\Exception $e) {
                // Database transaction will auto-rollback on exception
                Log::error('Failed to complete instant phone number purchase', [
                    'number' => $phoneNumberModel->number,
                    'customer_id' => $customerId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // Provide user-friendly error messages
                if (strpos($e->getMessage(), 'Insufficient credit') !== false) {
                    throw $e; // Pass through insufficient credit error
                } elseif (strpos($e->getMessage(), 'already purchased') !== false) {
                    throw new \Exception('This number has already been purchased. Please refresh and try another number.');
                } elseif (strpos($e->getMessage(), 'Twilio') !== false) {
                    throw new \Exception('Failed to purchase from Twilio: '.$e->getMessage().'. Please contact support if this persists.');
                } else {
                    throw new \Exception('Failed to purchase phone number. Please try again or contact support. Error: '.$e->getMessage());
                }
            }
        });
    }

    /**
     * Approve a number request
     * Purchases from the locked provider if not already purchased, then
     * assigns to customer
     */
    public function approveRequest(NumberRequest $request, $admin, $notes = null)
    {
        if (! $request->canBeProcessed()) {
            throw new \Exception('This request cannot be approved.');
        }

        $phoneNumber = $request->phoneNumber;
        $adminId = $admin->id ?? $admin;

        // Check if number needs to be purchased from the locked provider.
        // Plivo branch is new; the Twilio branch is untouched.
        if ($this->telephony->provider() === TelephonyProvider::PLIVO) {
            if (! $phoneNumber->provider_number_sid) {
                $this->phoneNumberService->purchaseNumber($phoneNumber, $phoneNumber->friendly_name);
                $phoneNumber->refresh();
            }
        } elseif (! $phoneNumber->twilio_sid) {
            $this->purchaseNumberFromTwilio($phoneNumber);
        }

        // Approve the request and assign to customer
        $request->update([
            'status' => 'approved',
            'admin_id' => $adminId,
            'admin_notes' => $notes,
            'processed_at' => now(),
        ]);

        // Assign number to customer
        $phoneNumber->update([
            'status' => 'assigned',
            'user_id' => $request->customer_id,
            // Column is approved_by. 'approved_by_id' matched nothing, so mass
            // assignment dropped it silently and the approving admin was never
            // recorded on the number.
            'approved_by' => $adminId,
            'approved_at' => now(),
            'assigned_at' => now(),
        ]);

        // Notify customer
        try {
            $request->customer->notify(new NumberRequestApproved($request));
        } catch (\Exception $e) {
            // Log notification error but don't fail the approval
            logger()->error('Failed to send approval notification: '.$e->getMessage());
        }

        return $request;
    }

    /**
     * Purchase a phone number from Twilio
     */
    protected function purchaseNumberFromTwilio(PhoneNumber $phoneNumber)
    {
        $config = TwilioGlobalConfig::active();

        if (! $config) {
            throw new \Exception('Twilio not configured. Cannot purchase number.');
        }

        try {
            $client = new TwilioClient(
                $config->account_sid,
                $config->getDecryptedAuthToken()
            );

            // Purchase the number
            $purchased = $client->incomingPhoneNumbers->create([
                'phoneNumber' => $phoneNumber->number,
                'friendlyName' => $phoneNumber->friendly_name ?? $phoneNumber->number,
            ]);

            // Configure for WebRTC if TwiML App exists
            if ($config->twiml_app_sid) {
                $client->incomingPhoneNumbers($purchased->sid)
                    ->update([
                        'voiceApplicationSid' => $config->twiml_app_sid,
                    ]);
            }

            // Update phone number record with Twilio SID
            $phoneNumber->update([
                'twilio_sid' => $purchased->sid,
            ]);

            logger()->info('Successfully purchased number from Twilio', [
                'number' => $phoneNumber->number,
                'sid' => $purchased->sid,
            ]);

            return $purchased;

        } catch (\Exception $e) {
            logger()->error('Failed to purchase number from Twilio: '.$e->getMessage(), [
                'number' => $phoneNumber->number,
            ]);
            throw new \Exception('Failed to purchase number from Twilio: '.$e->getMessage());
        }
    }

    /**
     * Reject a number request
     */
    public function rejectRequest(NumberRequest $request, $admin, $notes)
    {
        if (! $request->canBeProcessed()) {
            throw new \Exception('This request cannot be rejected.');
        }

        $adminId = $admin->id ?? $admin;

        $request->update([
            'status' => 'rejected',
            'admin_id' => $adminId,
            'admin_notes' => $notes,
            'processed_at' => now(),
        ]);

        // Release number back to available
        $request->phoneNumber->update([
            'status' => 'available',
            'requested_by' => null,
        ]);

        // Notify customer
        try {
            $request->customer->notify(new NumberRequestRejected($request));
        } catch (\Exception $e) {
            logger()->error('Failed to send rejection notification: '.$e->getMessage());
        }

        return $request;
    }

    /**
     * Cancel a request (by customer)
     */
    public function cancelRequest(NumberRequest $request)
    {
        if (! $request->canBeCancelled()) {
            throw new \Exception('This request cannot be cancelled.');
        }

        $request->update([
            'status' => 'cancelled',
            'processed_at' => now(),
        ]);

        // Release number back to available
        $request->phoneNumber->update([
            'status' => 'available',
            'requested_by' => null,
        ]);

        return $request;
    }

    /**
     * Get pending requests count
     */
    public function getPendingCount()
    {
        return NumberRequest::pending()->count();
    }

    /**
     * Get requests for a customer
     */
    public function getCustomerRequests($customer)
    {
        $customerId = $customer->id ?? $customer;

        return NumberRequest::where('customer_id', $customerId)
            ->with(['phoneNumber', 'admin']);
    }

    /**
     * Get all pending requests (for admin)
     */
    public function getPendingRequests()
    {
        return NumberRequest::pending()
            ->with(['phoneNumber', 'customer'])
            ->orderBy('requested_at', 'asc')
            ->get();
    }

    /**
     * Notify admin of new request
     */
    protected function notifyAdminOfRequest(NumberRequest $request)
    {
        try {
            $admins = User::whereHas('roles', function ($q) {
                $q->where('slug', 'admin');
            })->get();

            foreach ($admins as $admin) {
                $admin->notify(new NumberRequestCreated($request));
            }
        } catch (\Exception $e) {
            logger()->error('Failed to notify admin of request: '.$e->getMessage());
        }
    }

    /**
     * Notify admin of instant purchase (informational)
     */
    protected function notifyAdminOfInstantPurchase(NumberRequest $request)
    {
        try {
            $admins = User::whereHas('roles', function ($q) {
                $q->where('slug', 'admin');
            })->get();

            foreach ($admins as $admin) {
                // Send informational notification about the instant purchase
                $admin->notify(new NumberRequestApproved($request));
            }
        } catch (\Exception $e) {
            logger()->error('Failed to notify admin of instant purchase: '.$e->getMessage());
        }
    }

    /**
     * Get request statistics
     */
    public function getStatistics()
    {
        return [
            'pending' => NumberRequest::pending()->count(),
            'approved_today' => NumberRequest::approved()
                ->whereDate('processed_at', today())
                ->count(),
            'rejected_today' => NumberRequest::rejected()
                ->whereDate('processed_at', today())
                ->count(),
            'total_approved' => NumberRequest::approved()->count(),
            'total_rejected' => NumberRequest::rejected()->count(),
        ];
    }
}
