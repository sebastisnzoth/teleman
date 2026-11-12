<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreditService
{
    /**
     * Add credit to user's account.
     *
     * @param User $user
     * @param float $amount
     * @param string $description
     * @param array $options [
     *   'currency' => string,
     *   'reference_type' => string,
     *   'reference_id' => int,
     *   'payment_gateway' => string,
     *   'payment_id' => string,
     *   'metadata' => array,
     *   'admin_id' => int
     * ]
     * @return Transaction
     */
    public function addCredit(User $user, float $amount, string $description, array $options = []): Transaction
    {
        return DB::transaction(function () use ($user, $amount, $description, $options) {
            // Lock user row for update
            $user = User::where('id', $user->id)->lockForUpdate()->first();

            $balanceBefore = $user->credit_balance;
            $balanceAfter = $balanceBefore + $amount;

            // Update user balance
            $user->credit_balance = $balanceAfter;
            $user->save();

            // Create transaction record
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'type' => 'credit',
                'amount' => $amount,
                'currency' => $options['currency'] ?? $user->preferred_currency ?? 'USD',
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description,
                'reference_type' => $options['reference_type'] ?? null,
                'reference_id' => $options['reference_id'] ?? null,
                'payment_gateway' => $options['payment_gateway'] ?? null,
                'payment_id' => $options['payment_id'] ?? null,
                'status' => $options['status'] ?? 'completed',
                'metadata' => $options['metadata'] ?? null,
                'admin_id' => $options['admin_id'] ?? null,
                // Profit tracking fields
                'service_type' => $options['service_type'] ?? 'credit_purchase',
                'pricing_tier' => $options['pricing_tier'] ?? null,
                'cost_status' => $options['cost_status'] ?? 'confirmed',
            ]);

            Log::info('Credit added', [
                'user_id' => $user->id,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'transaction_id' => $transaction->id,
            ]);

            return $transaction;
        });
    }

    /**
     * Deduct credit from user's account.
     *
     * @param User $user
     * @param float $amount
     * @param string $description
     * @param array $options [same as addCredit]
     * @return Transaction
     * @throws \Exception if insufficient funds
     */
    public function deductCredit(User $user, float $amount, string $description, array $options = []): Transaction
    {
        // Admin users have unlimited credits - skip deduction
        if ($user->isAdmin()) {
            // Create a record for tracking purposes, but don't actually deduct
            return Transaction::create([
                'user_id' => $user->id,
                'type' => 'debit',
                'amount' => $amount,
                'currency' => $options['currency'] ?? $user->preferred_currency ?? 'USD',
                'balance_before' => $user->credit_balance,
                'balance_after' => $user->credit_balance, // No change for admin
                'description' => $description . ' (Admin - No charge)',
                'reference_type' => $options['reference_type'] ?? null,
                'reference_id' => $options['reference_id'] ?? null,
                'payment_gateway' => $options['payment_gateway'] ?? null,
                'payment_id' => $options['payment_id'] ?? null,
                'status' => $options['status'] ?? 'completed',
                'metadata' => array_merge($options['metadata'] ?? [], ['admin_unlimited' => true]),
                'admin_id' => $options['admin_id'] ?? null,
                'actual_cost' => $options['actual_cost'] ?? null,
                'profit_margin' => $options['profit_margin'] ?? null,
                'profit_amount' => $options['profit_amount'] ?? null,
            ]);
        }

        return DB::transaction(function () use ($user, $amount, $description, $options) {
            // Lock user row for update
            $user = User::where('id', $user->id)->lockForUpdate()->first();

            $balanceBefore = $user->credit_balance;
            $balanceAfter = $balanceBefore - $amount;

            // Check for sufficient funds
            if ($balanceAfter < 0) {
                throw new \Exception('Insufficient credit balance. Current balance: ' . $balanceBefore . ', Required: ' . $amount);
            }

            // Update user balance
            $user->credit_balance = $balanceAfter;
            $user->save();

            // Create transaction record
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'type' => 'debit',
                'amount' => $amount,
                'currency' => $options['currency'] ?? $user->preferred_currency ?? 'USD',
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description,
                'reference_type' => $options['reference_type'] ?? null,
                'reference_id' => $options['reference_id'] ?? null,
                'payment_gateway' => $options['payment_gateway'] ?? null,
                'payment_id' => $options['payment_id'] ?? null,
                'status' => $options['status'] ?? 'completed',
                'metadata' => $options['metadata'] ?? null,
                'admin_id' => $options['admin_id'] ?? null,
                // Profit tracking fields
                'actual_cost' => $options['actual_cost'] ?? null,
                'profit_amount' => $options['profit_amount'] ?? null,
                'profit_percentage' => $options['profit_percentage'] ?? null,
                'service_type' => $options['service_type'] ?? 'manual_adjustment',
                'pricing_tier' => $options['pricing_tier'] ?? null,
                'cost_status' => $options['cost_status'] ?? 'estimated',
            ]);

            Log::info('Credit deducted', [
                'user_id' => $user->id,
                'amount' => $amount,
                'balance_after' => $balanceAfter,
                'transaction_id' => $transaction->id,
            ]);

            // Check if low balance alert needed
            $this->checkLowBalanceAlert($user);

            return $transaction;
        });
    }

    /**
     * Get user's current balance.
     */
    public function getBalance(User $user): float
    {
        return (float) $user->credit_balance;
    }

    /**
     * Check if user can afford a specific amount.
     */
    public function canAfford(User $user, float $amount): bool
    {
        // Admin users have unlimited credits
        if ($user->isAdmin()) {
            return true;
        }

        return $user->credit_balance >= $amount;
    }

    /**
     * Calculate call cost based on duration and country.
     * Uses Twilio pricing (simplified - you should integrate with Twilio Pricing API)
     */
    public function calculateCallCost(int $durationSeconds, string $countryCode = 'US'): float
    {
        // Convert to minutes (round up)
        $durationMinutes = ceil($durationSeconds / 60);

        // Twilio approximate rates per minute (should be fetched from Twilio API)
        $rates = [
            'US' => 0.0085,
            'CA' => 0.0085,
            'GB' => 0.0120,
            'AU' => 0.0200,
            'IN' => 0.0050,
            'DE' => 0.0150,
            'FR' => 0.0150,
            'JP' => 0.0400,
            'BR' => 0.0250,
            'MX' => 0.0120,
        ];

        $ratePerMinute = $rates[$countryCode] ?? 0.0200; // Default rate
        $cost = $durationMinutes * $ratePerMinute;

        return round($cost, 4);
    }

    /**
     * Calculate SMS cost.
     * Uses Twilio pricing (simplified)
     */
    public function calculateSmsCost(string $countryCode = 'US', int $segments = 1): float
    {
        // Twilio approximate SMS rates (should be fetched from Twilio API)
        $rates = [
            'US' => 0.0079,
            'CA' => 0.0079,
            'GB' => 0.0400,
            'AU' => 0.0500,
            'IN' => 0.0350,
            'DE' => 0.0750,
            'FR' => 0.0750,
            'JP' => 0.0800,
            'BR' => 0.0450,
            'MX' => 0.0350,
        ];

        $ratePerSegment = $rates[$countryCode] ?? 0.0500; // Default rate
        $cost = $segments * $ratePerSegment;

        return round($cost, 4);
    }

    /**
     * Calculate phone number monthly cost.
     */
    public function calculatePhoneNumberMonthlyCost(string $countryCode = 'US'): float
    {
        // Twilio approximate monthly phone number costs
        $costs = [
            'US' => 1.15,
            'CA' => 1.00,
            'GB' => 1.50,
            'AU' => 2.00,
            'IN' => 3.00,
            'DE' => 2.00,
            'FR' => 2.00,
            'JP' => 5.00,
            'BR' => 3.00,
            'MX' => 2.00,
        ];

        return $costs[$countryCode] ?? 2.00; // Default cost
    }

    /**
     * Record a transaction (generic method).
     */
    public function recordTransaction(User $user, array $data): Transaction
    {
        if ($data['type'] === 'credit') {
            return $this->addCredit($user, $data['amount'], $data['description'], $data);
        } else {
            return $this->deductCredit($user, $data['amount'], $data['description'], $data);
        }
    }

    /**
     * Get user's transaction history.
     */
    public function getTransactionHistory(User $user, int $perPage = 15)
    {
        return Transaction::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Check and send low balance alert if needed.
     */
    protected function checkLowBalanceAlert(User $user): void
    {
        if (!$user->low_balance_alert_enabled) {
            return;
        }

        if ($user->credit_balance > $user->low_balance_threshold) {
            return;
        }

        // Don't spam - only send alert once per day
        if ($user->last_low_balance_alert_at && 
            $user->last_low_balance_alert_at->isToday()) {
            return;
        }

        // Send notification (implement notification later)
        // Notification::send($user, new LowBalanceNotification());

        // Update alert timestamp
        $user->last_low_balance_alert_at = now();
        $user->save();

        Log::info('Low balance alert sent', [
            'user_id' => $user->id,
            'balance' => $user->credit_balance,
            'threshold' => $user->low_balance_threshold,
        ]);
    }

    /**
     * Get user's spending summary.
     */
    public function getSpendingSummary(User $user, string $period = 'month'): array
    {
        $startDate = match($period) {
            'day' => now()->startOfDay(),
            'week' => now()->startOfWeek(),
            'month' => now()->startOfMonth(),
            'year' => now()->startOfYear(),
            default => now()->startOfMonth(),
        };

        $transactions = Transaction::where('user_id', $user->id)
            ->where('created_at', '>=', $startDate)
            ->get();

        $totalCredits = $transactions->where('type', 'credit')->sum('amount');
        $totalDebits = $transactions->where('type', 'debit')->sum('amount');

        $debitsByType = $transactions->where('type', 'debit')
            ->groupBy('reference_type')
            ->map(fn($group) => $group->sum('amount'));

        return [
            'period' => $period,
            'start_date' => $startDate->toDateString(),
            'total_credits' => round($totalCredits, 2),
            'total_debits' => round($totalDebits, 2),
            'net_change' => round($totalCredits - $totalDebits, 2),
            'breakdown' => $debitsByType->toArray(),
            'transaction_count' => $transactions->count(),
        ];
    }

    /**
     * Deduct credit for a call with profit tracking.
     *
     * @param User $user
     * @param mixed $call Call model instance
     * @param float $actualCost Actual cost from Twilio
     * @param float|null $estimatedCost Estimated cost (if pre-deducted)
     * @return Transaction
     */
    public function deductForCall(User $user, $call, float $actualCost, ?float $estimatedCost = null): Transaction
    {
        // If no estimated cost provided, use actual cost with default margin
        if ($estimatedCost === null) {
            $pricingService = app(PricingService::class);
            $pricing = $pricingService->calculateCallCost(
                $call->duration_seconds ?? 0,
                $call->metadata['country_code'] ?? 'US'
            );
            $estimatedCost = $pricing['customer_charge'];
        }

        $profitAmount = $estimatedCost - $actualCost;
        $profitPercentage = $estimatedCost > 0 ? ($profitAmount / $estimatedCost) * 100 : 0;

        $description = sprintf(
            'Call to %s - %d seconds',
            $call->to_number ?? 'Unknown',
            $call->duration_seconds ?? 0
        );

        return $this->deductCredit($user, $estimatedCost, $description, [
            'actual_cost' => $actualCost,
            'profit_amount' => round($profitAmount, 4),
            'profit_percentage' => round($profitPercentage, 2),
            'service_type' => 'call',
            'service_reference_type' => get_class($call),
            'service_reference_id' => $call->id,
            'metadata' => [
                'call_id' => $call->id,
                'duration_seconds' => $call->duration_seconds ?? 0,
                'to_number' => $call->to_number ?? null,
                'from_number' => $call->from_number ?? null,
                'country_code' => $call->metadata['country_code'] ?? 'US',
                'estimated_cost' => $estimatedCost,
            ],
        ]);
    }

    /**
     * Deduct credit for SMS with profit tracking.
     *
     * @param User $user
     * @param mixed $sms SMS model instance
     * @param float $actualCost Actual cost from Twilio
     * @param float|null $estimatedCost Estimated cost (if pre-deducted)
     * @return Transaction
     */
    public function deductForSms(User $user, $sms, float $actualCost, ?float $estimatedCost = null): Transaction
    {
        $metadata = is_array($sms->metadata) ? $sms->metadata : [];
        $segments = $sms->num_segments ?? 1;
        $toNumber = $sms->receiver_phone ?? 'Unknown';
        $countryCode = $metadata['country_code'] ?? 'US';

        // If no estimated cost provided, calculate it
        if ($estimatedCost === null) {
            $pricingService = app(PricingService::class);
            $pricing = $pricingService->calculateSmsCost($countryCode, $segments);
            $estimatedCost = $pricing['charge'];
        }

        $profitAmount = $estimatedCost - $actualCost;
        $profitPercentage = $estimatedCost > 0 ? ($profitAmount / $estimatedCost) * 100 : 0;

        $description = sprintf('SMS to %s - %d segment(s)', $toNumber, $segments);

        return $this->deductCredit($user, $estimatedCost, $description, [
            'actual_cost' => $actualCost,
            'profit_amount' => round($profitAmount, 4),
            'profit_percentage' => round($profitPercentage, 2),
            'service_type' => 'sms',
            'service_reference_type' => get_class($sms),
            'service_reference_id' => $sms->id,
            'metadata' => [
                'sms_id' => $sms->id,
                'segments' => $segments,
                'to_number' => $toNumber,
                'country_code' => $countryCode,
                'estimated_cost' => $estimatedCost,
            ],
        ]);
    }

    /**
     * Deduct credit for phone number rental with profit tracking.
     *
     * @param User $user
     * @param mixed $phoneNumber PhoneNumber model instance
     * @param float $actualCost Actual monthly cost
     * @param float|null $estimatedCost Estimated cost
     * @return Transaction
     */
    public function deductForPhoneNumber(User $user, $phoneNumber, float $actualCost, ?float $estimatedCost = null): Transaction
    {
        // If no estimated cost provided, calculate it
        if ($estimatedCost === null) {
            $pricingService = app(PricingService::class);
            $pricing = $pricingService->calculatePhoneNumberCost(
                $phoneNumber->country_code ?? 'US'
            );
            $estimatedCost = $pricing['customer_charge'];
        }

        $profitAmount = $estimatedCost - $actualCost;
        $profitPercentage = $estimatedCost > 0 ? ($profitAmount / $estimatedCost) * 100 : 0;

        // PhoneNumber calls it `number`; a trunk number calls it `phone_number`.
        $dialable = $phoneNumber->number ?? $phoneNumber->phone_number ?? null;

        $description = sprintf(
            'Phone number rental: %s',
            $dialable ?? 'Unknown'
        );

        return $this->deductCredit($user, $estimatedCost, $description, [
            'actual_cost' => $actualCost,
            'profit_amount' => round($profitAmount, 4),
            'profit_percentage' => round($profitPercentage, 2),
            'service_type' => 'phone_number',
            'service_reference_type' => get_class($phoneNumber),
            'service_reference_id' => $phoneNumber->id,
            'metadata' => [
                'phone_number_id' => $phoneNumber->id,
                'phone_number' => $dialable,
                'country_code' => $phoneNumber->country_code ?? 'US',
                'estimated_cost' => $estimatedCost,
            ],
        ]);
    }
}
