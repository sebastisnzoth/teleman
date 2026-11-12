<?php

namespace App\Console\Commands;

use App\Models\SmsMessage;
use App\Models\User;
use App\Services\CreditService;
use App\Services\PricingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuditUnbilledSmsMessages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:audit-unbilled {--bill : Actually bill users for unbilled SMS} {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit and optionally bill users for historical unbilled SMS messages';

    private CreditService $creditService;
    private PricingService $pricingService;

    public function __construct(CreditService $creditService, PricingService $pricingService)
    {
        parent::__construct();
        $this->creditService = $creditService;
        $this->pricingService = $pricingService;
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting SMS billing audit...');
        
        // Build query for unbilled outbound messages
        $query = SmsMessage::with(['conversation.phoneNumber.user'])
            ->where('direction', 'outbound')
            ->where('status', 'delivered')
            ->where('credits_deducted', false);

        // Apply date filters if provided
        if ($from = $this->option('from')) {
            $query->where('created_at', '>=', $from);
            $this->info("Filtering from: {$from}");
        }

        if ($to = $this->option('to')) {
            $query->where('created_at', '<=', $to);
            $this->info("Filtering to: {$to}");
        }

        $unbilledMessages = $query->get();

        if ($unbilledMessages->isEmpty()) {
            $this->info('✓ No unbilled SMS messages found!');
            return Command::SUCCESS;
        }

        $this->warn("Found {$unbilledMessages->count()} unbilled SMS messages");
        
        // Group by user for reporting
        $messagesByUser = $unbilledMessages->groupBy(function ($message) {
            return $message->conversation?->phoneNumber?->user_id;
        });

        // Calculate totals
        $totalMessages = 0;
        $totalEstimatedCost = 0;
        $totalEstimatedRevenue = 0;

        $table = [];
        
        foreach ($messagesByUser as $userId => $messages) {
            if (!$userId) {
                $this->warn("Skipping {$messages->count()} messages with no user association");
                continue;
            }

            $user = User::find($userId);
            if (!$user) {
                $this->warn("User {$userId} not found, skipping {$messages->count()} messages");
                continue;
            }

            $userTotalCost = 0;
            $userTotalRevenue = 0;

            foreach ($messages as $message) {
                $countryCode = $this->extractCountryCode($message->receiver_phone);
                $segments = $message->num_segments ?? 1;
                
                $pricing = $this->pricingService->calculateSmsCost(
                    $countryCode,
                    $segments,
                    $user->pricing_tier ?? 'standard'
                );

                $userTotalCost += $pricing['base_cost'];
                $userTotalRevenue += $pricing['charge'];
            }

            $totalMessages += $messages->count();
            $totalEstimatedCost += $userTotalCost;
            $totalEstimatedRevenue += $userTotalRevenue;

            $table[] = [
                'User ID' => $userId,
                'User Name' => $user->name,
                'Messages' => $messages->count(),
                'Estimated Cost' => '$' . number_format($userTotalCost, 4),
                'Should Charge' => '$' . number_format($userTotalRevenue, 4),
                'Profit' => '$' . number_format($userTotalRevenue - $userTotalCost, 4),
                'Balance' => '$' . number_format($user->credit_balance, 2),
            ];
        }

        // Display audit results
        $this->table(
            ['User ID', 'User Name', 'Messages', 'Estimated Cost', 'Should Charge', 'Profit', 'Balance'],
            $table
        );

        $this->newLine();
        $this->info('=== SUMMARY ===');
        $this->info("Total Unbilled Messages: {$totalMessages}");
        $this->info("Total Estimated Cost: $" . number_format($totalEstimatedCost, 4));
        $this->info("Total Should Charge: $" . number_format($totalEstimatedRevenue, 4));
        $this->info("Total Potential Profit: $" . number_format($totalEstimatedRevenue - $totalEstimatedCost, 4));
        $this->info("Net Loss (if not billed): $" . number_format($totalEstimatedCost, 4));

        // Offer to bill users
        if ($this->option('bill')) {
            if (!$this->confirm('Do you want to bill users for these unbilled SMS messages?', false)) {
                $this->info('Billing cancelled.');
                return Command::SUCCESS;
            }

            return $this->billUnbilledMessages($unbilledMessages);
        } else {
            $this->newLine();
            $this->comment('💡 To actually bill users, run: php artisan sms:audit-unbilled --bill');
        }

        return Command::SUCCESS;
    }

    /**
     * Bill users for unbilled messages
     */
    private function billUnbilledMessages($messages): int
    {
        $this->info('Starting billing process...');
        
        $billed = 0;
        $failed = 0;
        $insufficient = 0;

        $bar = $this->output->createProgressBar($messages->count());

        foreach ($messages as $message) {
            try {
                $conversation = $message->conversation;
                if (!$conversation) {
                    $failed++;
                    $bar->advance();
                    continue;
                }

                $phoneNumber = $conversation->phoneNumber;
                if (!$phoneNumber) {
                    $failed++;
                    $bar->advance();
                    continue;
                }

                $user = $phoneNumber->user;
                if (!$user) {
                    $failed++;
                    $bar->advance();
                    continue;
                }

                $countryCode = $this->extractCountryCode($message->receiver_phone);
                $segments = $message->num_segments ?? 1;
                
                $pricing = $this->pricingService->calculateSmsCost(
                    $countryCode,
                    $segments,
                    $user->pricing_tier ?? 'standard'
                );

                if (!$this->creditService->canAfford($user, $pricing['charge'])) {
                    $insufficient++;
                    Log::warning('Insufficient credits for retroactive SMS billing', [
                        'user_id' => $user->id,
                        'message_id' => $message->id,
                        'required' => $pricing['charge'],
                        'balance' => $user->credit_balance,
                    ]);
                    $bar->advance();
                    continue;
                }

                DB::beginTransaction();
                try {
                    $transaction = $this->creditService->deductForSms(
                        $user,
                        $message,
                        $pricing['base_cost'],
                        $pricing['charge']
                    );

                    $message->update([
                        'credits_deducted' => true,
                        'transaction_id' => $transaction->id,
                    ]);

                    DB::commit();
                    $billed++;

                } catch (\Exception $e) {
                    DB::rollBack();
                    throw $e;
                }

            } catch (\Exception $e) {
                $failed++;
                Log::error('Failed to bill SMS message', [
                    'message_id' => $message->id,
                    'error' => $e->getMessage(),
                ]);
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("✓ Successfully billed: {$billed} messages");
        
        if ($insufficient > 0) {
            $this->warn("⚠ Insufficient funds: {$insufficient} messages");
        }
        
        if ($failed > 0) {
            $this->error("✗ Failed: {$failed} messages");
        }

        return Command::SUCCESS;
    }

    /**
     * Extract country code from phone number
     */
    private function extractCountryCode(string $phoneNumber): string
    {
        $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        if (str_starts_with($cleaned, '1')) return 'US';
        if (str_starts_with($cleaned, '44')) return 'GB';
        if (str_starts_with($cleaned, '61')) return 'AU';
        if (str_starts_with($cleaned, '91')) return 'IN';
        if (str_starts_with($cleaned, '49')) return 'DE';
        if (str_starts_with($cleaned, '33')) return 'FR';
        if (str_starts_with($cleaned, '81')) return 'JP';
        if (str_starts_with($cleaned, '55')) return 'BR';
        if (str_starts_with($cleaned, '52')) return 'MX';
        
        return 'US';
    }
}
