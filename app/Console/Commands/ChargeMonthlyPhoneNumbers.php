<?php

namespace App\Console\Commands;

use App\Models\PhoneNumber;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CreditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChargeMonthlyPhoneNumbers extends Command
{
    protected $signature = 'phone-numbers:charge-monthly';
    protected $description = 'Charge users monthly for their assigned phone numbers';

    public function __construct(
        private readonly CreditService $creditService,
        private readonly \App\Services\PhoneNumberService $phoneNumberService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Starting monthly phone number charges...');
        
        $assignedNumbers = PhoneNumber::with('user')
            ->where('status', 'assigned')
            ->whereNotNull('user_id')
            ->get();

        if ($assignedNumbers->isEmpty()) {
            $this->info('No assigned phone numbers found.');
            return Command::SUCCESS;
        }

        $totalCharged = 0;
        $successCount = 0;
        $failureCount = 0;
        $insufficientFundsCount = 0;

        foreach ($assignedNumbers as $number) {
            try {
                $user = $number->user;
                
                if (!$user) {
                    $this->warn("Phone number {$number->number} has no assigned user, skipping...");
                    continue;
                }

                // Already paid for this month — when it was bought, or by an
                // earlier run (this can be run by hand as well as on the 1st).
                if ($this->chargedThisMonth($user, $number)) {
                    $this->line("· {$number->number} is already paid for this month, skipping.");
                    continue;
                }

                $monthlyCost = $number->monthly_cost ?? 1.00;

                // Check if user can afford
                if (!$this->creditService->canAfford($user, $monthlyCost)) {
                    $this->error("User {$user->name} ({$user->id}) has insufficient credits for {$number->number}. Required: {$monthlyCost}, Balance: {$user->credit_balance}");
                    $insufficientFundsCount++;
                    
                    // Log this for admin notification
                    Log::warning('Insufficient credits for monthly phone charge', [
                        'user_id' => $user->id,
                        'user_name' => $user->name,
                        'phone_number' => $number->number,
                        'required' => $monthlyCost,
                        'balance' => $user->credit_balance,
                    ]);
                    
                    // Automatically revoke the number if user can't pay
                    try {
                        $this->phoneNumberService->revokeNumber($number);
                        $this->info("⚠ Revoked {$number->number} from {$user->name} due to insufficient funds.");
                        
                        Log::info('Phone number revoked due to insufficient funds', [
                            'user_id' => $user->id,
                            'phone_number' => $number->number
                        ]);
                    } catch (\Exception $revokeEx) {
                        $this->error("Failed to revoke number: " . $revokeEx->getMessage());
                    }
                    
                    continue;
                }

                // Deduct the monthly cost
                DB::beginTransaction();
                try {
                    $transaction = $this->creditService->deductForPhoneNumber(
                        $user,
                        $number,
                        $monthlyCost, // actual cost from Twilio
                        $monthlyCost  // we charge the same amount (no markup on rentals)
                    );

                    $totalCharged += $monthlyCost;
                    $successCount++;

                    $this->info("✓ Charged {$user->name}: {$monthlyCost} credits for {$number->number}");
                    
                    Log::info('Monthly phone number charge successful', [
                        'user_id' => $user->id,
                        'phone_number' => $number->number,
                        'amount' => $monthlyCost,
                        'transaction_id' => $transaction->id,
                    ]);

                    DB::commit();
                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }

            } catch (\Exception $e) {
                $failureCount++;
                $this->error("✗ Failed to charge for {$number->number}: {$e->getMessage()}");
                
                Log::error('Monthly phone number charge failed', [
                    'phone_number' => $number->number,
                    'user_id' => $number->user_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info('=== Monthly Phone Number Charges Complete ===');
        $this->table(
            ['Metric', 'Count/Amount'],
            [
                ['Total Numbers', $assignedNumbers->count()],
                ['Successfully Charged', $successCount],
                ['Failed', $failureCount],
                ['Insufficient Funds', $insufficientFundsCount],
                ['Total Amount Charged', number_format($totalCharged, 2) . ' credits'],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Whether $user has already been charged for $number since the 1st —
     * the purchase and the monthly rental both leave a phone_number debit
     * naming it.
     */
    private function chargedThisMonth(User $user, PhoneNumber $number): bool
    {
        return Transaction::where('user_id', $user->id)
            ->where('type', 'debit')
            ->where('status', 'completed')
            ->where('service_type', 'phone_number')
            ->where('metadata->phone_number_id', $number->id)
            ->where('created_at', '>=', now()->startOfMonth())
            ->exists();
    }
}
