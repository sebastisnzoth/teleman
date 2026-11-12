<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\User;
use App\Services\ContactDataCleaningService;
use Illuminate\Console\Command;

class TestContactCleaning extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:contact-cleaning';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test AI contact data cleaning and validation';

    /**
     * Execute the console command.
     */
    public function handle(ContactDataCleaningService $cleaningService)
    {
        $this->info('🧹 Testing AI Contact Data Cleaning...');
        $this->newLine();

        // Get or create test user
        $user = User::first();
        if (!$user) {
            $this->error('No users found. Please create a user first.');
            return 1;
        }

        // Clean up any previous test contacts
        Contact::where('user_id', $user->id)
            ->where('email', 'like', '%test-cleanup%')
            ->delete();

        // Generate unique phone numbers for this test run
        $timestamp = time();
        
        // Sample contacts with various data quality issues
        $testContacts = [
            [
                'name' => 'Missing Country Code',
                'data' => [
                    'phone_number' => '415555' . substr($timestamp, -4),
                    'first_name' => 'john',
                    'last_name' => 'DOE',
                    'email' => "john.doe.test-cleanup-{$timestamp}@example.com",
                    'company' => 'acme corp',
                ],
            ],
            [
                'name' => 'All Lowercase Names',
                'data' => [
                    'phone_number' => '+1-415-555-' . substr($timestamp, -4),
                    'first_name' => 'sarah',
                    'last_name' => 'smith',
                    'email' => "sarah.test-cleanup-{$timestamp}@test.com",
                    'company' => 'tech solutions llc',
                ],
            ],
            [
                'name' => 'Inconsistent Formatting',
                'data' => [
                    'phone_number' => '(415) 555-' . (substr($timestamp, -4) + 1),
                    'first_name' => 'MIKE',
                    'last_name' => 'johnson',
                    'email' => "mike.test-cleanup-{$timestamp}@company",
                    'company' => 'ABC INC',
                ],
            ],
            [
                'name' => 'Clean Data',
                'data' => [
                    'phone_number' => '+1415555' . (substr($timestamp, -4) + 2),
                    'first_name' => 'Emily',
                    'last_name' => 'Chen',
                    'email' => "emily.chen.test-cleanup-{$timestamp}@example.com",
                    'company' => 'Global Industries',
                    'job_title' => 'Marketing Director',
                ],
            ],
        ];

        $results = [];

        foreach ($testContacts as $test) {
            $this->info("📞 Test Case: {$test['name']}");
            $this->line("Original Data:");
            $this->line(json_encode($test['data'], JSON_PRETTY_PRINT));
            $this->newLine();

            // Create contact
            $contact = Contact::create(array_merge(
                ['user_id' => $user->id],
                $test['data']
            ));

            // Clean contact
            $analysis = $cleaningService->cleanContact($contact, false);

            // Refresh to see updates
            $contact->refresh();

            $this->info("📊 Quality Score: {$analysis['quality_score']}/100");
            
            if (!empty($analysis['issues'])) {
                $this->warn("⚠️  Issues Found (" . count($analysis['issues']) . "):");
                foreach ($analysis['issues'] as $issue) {
                    $severity = $issue['severity'] ?? 'unknown';
                    $this->line("  - [{$severity}] {$issue['field']}: {$issue['issue']}");
                }
            } else {
                $this->info("✅ No issues found!");
            }

            if (!empty($analysis['suggestions'])) {
                $this->info("💡 AI Suggestions:");
                foreach ($analysis['suggestions'] as $field => $value) {
                    $original = $test['data'][$field] ?? 'N/A';
                    $this->line("  {$field}: {$original} → {$value}");
                }
            }

            $this->info("🎲 Duplicate Risk: {$analysis['duplicate_risk']}");
            $this->info("✓ Valid: " . ($analysis['is_valid'] ? 'Yes' : 'No'));

            $results[] = [
                $test['name'],
                $analysis['quality_score'],
                count($analysis['issues']),
                count($analysis['suggestions']),
                $analysis['duplicate_risk'],
            ];

            $this->newLine();
            $this->line('---');
            $this->newLine();
        }

        // Summary table
        $this->info('📈 Summary of All Tests:');
        $this->table(
            ['Test Case', 'Quality Score', 'Issues', 'Suggestions', 'Duplicate Risk'],
            $results
        );

        // Get quality statistics
        $stats = $cleaningService->getQualityStatistics($user->id);
        $this->newLine();
        $this->info('📊 Quality Statistics:');
        $this->line("Total Contacts: {$stats['total_contacts']}");
        $this->line("Checked: {$stats['checked_contacts']}");
        $this->line("Average Score: {$stats['average_score']}");
        $this->line("High Quality (≥80): {$stats['high_quality']}");
        $this->line("Medium Quality (60-79): {$stats['medium_quality']}");
        $this->line("Low Quality (<60): {$stats['low_quality']}");

        $this->newLine();
        $this->info('✅ Test completed!');
        $this->info('💡 Contact data is now validated and ready for campaigns.');

        return 0;
    }
}
