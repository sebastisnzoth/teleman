<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\User;
use App\Services\MessageVariantService;
use Illuminate\Console\Command;

class TestMessageVariants extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:message-variants';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test AI message variant generation for A/B testing';

    /**
     * Execute the console command.
     */
    public function handle(MessageVariantService $variantService)
    {
        $this->info('🎯 Testing AI Message Variant Generation...');
        $this->newLine();

        // Get or create test user
        $user = User::first();
        if (!$user) {
            $this->error('No users found. Please create a user first.');
            return 1;
        }

        // Sample campaign scenarios
        $scenarios = [
            [
                'name' => 'Solar Panel Promotion',
                'base_message' => 'Hi {{first_name}}, interested in reducing your electricity bills with solar panels? Call us back!',
                'description' => 'Promoting solar panel installation with financing options',
                'variables' => ['first_name', 'company', 'savings_amount'],
            ],
            [
                'name' => 'Insurance Follow-up',
                'base_message' => 'Hello {{first_name}}, following up on your insurance quote. We have great rates available.',
                'description' => 'Following up on insurance quote request',
                'variables' => ['first_name', 'policy_type', 'quote_amount'],
            ],
        ];

        foreach ($scenarios as $scenario) {
            $this->info("📋 Scenario: {$scenario['name']}");
            $this->line("Base Message: {$scenario['base_message']}");
            $this->newLine();

            // Create temporary campaign
            $campaign = Campaign::create([
                'user_id' => $user->id,
                'name' => $scenario['name'] . ' (Test)',
                'type' => 'text_to_speech',
                'status' => 'draft',
                'message' => $scenario['base_message'],
                'expected_variables' => $scenario['variables'],
                'from_number' => '+1234567890',
            ]);

            // Generate variants
            $variants = $variantService->generateVariants(
                $campaign,
                $scenario['base_message'],
                $scenario['description']
            );

            $this->info("✅ Generated " . count($variants) . " variants:");
            $this->newLine();

            $tableData = [];
            foreach ($variants as $variant) {
                $tableData[] = [
                    $variant->variant_label,
                    $variant->variant_name,
                    $this->truncate($variant->message_text, 60),
                    $variant->tone_description,
                ];

                $this->line("[{$variant->variant_label}] {$variant->variant_name}");
                $this->line("Message: {$variant->message_text}");
                $this->line("Tone: {$variant->tone_description}");
                $this->newLine();
            }

            // Summary table
            $this->table(
                ['Label', 'Name', 'Message Preview', 'Tone Description'],
                $tableData
            );

            $this->newLine();
            $this->line('---');
            $this->newLine();

            // Cleanup
            $campaign->delete();
        }

        $this->info('✅ Test completed!');
        $this->info('💡 Variants are ready for A/B testing. Each variant will be randomly selected during campaigns.');

        return 0;
    }

    private function truncate(string $text, int $length): string
    {
        return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
    }
}
