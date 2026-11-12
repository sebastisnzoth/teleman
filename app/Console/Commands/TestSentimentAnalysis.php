<?php

namespace App\Console\Commands;

use App\Models\Call;
use App\Models\User;
use App\Services\SentimentAnalysisService;
use Illuminate\Console\Command;

class TestSentimentAnalysis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:sentiment-analysis';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test AI sentiment analysis with sample transcripts';

    /**
     * Execute the console command.
     */
    public function handle(SentimentAnalysisService $sentimentService)
    {
        $this->info('🤖 Testing AI Sentiment Analysis...');
        $this->newLine();

        // Sample transcripts for testing different scenarios
        $sampleTranscripts = [
            [
                'scenario' => 'Hot Lead - Very Interested',
                'transcript' => "Agent: Hi, this is Sarah from XYZ Company. I'm calling to tell you about our new promotional offer on solar panels. Are you interested in reducing your electricity bills?\n\nCustomer: Oh yes! Actually, I've been thinking about solar panels for months now. I really want to go green and save money. Can you tell me more about the financing options? I'm ready to schedule an installation as soon as possible.\n\nAgent: That's wonderful! Let me explain our zero-interest financing plan...",
            ],
            [
                'scenario' => 'Warm Lead - Some Interest',
                'transcript' => "Agent: Hello, I'm calling from ABC Insurance. We have some great new health insurance plans I'd like to discuss with you.\n\nCustomer: Well, I already have insurance, but I'm not entirely happy with my current provider. The premiums are quite high. What kind of rates are you offering?\n\nAgent: I understand. Our plans are very competitive and include dental coverage...\n\nCustomer: That sounds interesting. Can you send me some information by email? I'd like to review it with my spouse before making any decisions.",
            ],
            [
                'scenario' => 'Cold Lead - Polite but Uninterested',
                'transcript' => "Agent: Good afternoon, I'm calling from XYZ Financial Services about our investment opportunities.\n\nCustomer: Hi. Um, I appreciate the call, but I'm really not interested in any investments right now. I'm actually quite satisfied with my current financial situation and my advisor.\n\nAgent: I understand. Perhaps I could just send you some information for future reference?\n\nCustomer: I don't think so, but thank you for thinking of me. I really need to go now. Have a good day.",
            ],
            [
                'scenario' => 'Negative - Annoyed Customer',
                'transcript' => "Agent: Hello, this is Mike from Tech Solutions calling about—\n\nCustomer: Are you kidding me? This is the third call I've gotten from your company this week! I've already told you people I'm not interested! Stop calling me!\n\nAgent: I apologize for the inconvenience, sir. Let me make sure we—\n\nCustomer: Just remove my number from your list right now! This is harassment! I'm going to report you if you call again!\n\nAgent: I understand your frustration. I'll immediately add you to our do-not-call list.",
            ],
        ];

        $this->info('Creating test calls with transcripts...');
        
        // Get or create a test user
        $user = User::first();
        if (!$user) {
            $this->error('No users found in database. Please create a user first.');
            return 1;
        }

        $results = [];

        foreach ($sampleTranscripts as $sample) {
            $this->info("📞 Testing: {$sample['scenario']}");
            
            // Create a call record with transcript
            $call = Call::create([
                'user_id' => $user->id,
                'call_type' => 'manual',
                'direction' => 'outbound',
                'to_number' => '+1234567890',
                'from_number' => '+0987654321',
                'status' => 'completed',
                'duration_seconds' => 120,
                'transcript_text' => $sample['transcript'],
                'transcript_status' => 'completed',
                'started_at' => now()->subMinutes(10),
                'ended_at' => now()->subMinutes(8),
            ]);

            // Analyze sentiment
            $success = $sentimentService->processCall($call);
            
            // Refresh to get updated data
            $call->refresh();

            if ($success) {
                $results[] = [
                    'scenario' => $sample['scenario'],
                    'sentiment' => $call->sentiment,
                    'confidence' => $call->sentiment_confidence . '%',
                    'lead_quality' => $call->lead_quality,
                    'lead_score' => $call->lead_score . '/10',
                    'summary' => $call->ai_summary,
                    'intents' => implode(', ', $call->key_intents ?? []),
                ];

                $this->info("✅ Sentiment: {$call->sentiment} ({$call->sentiment_confidence}%)");
                $this->info("🎯 Lead Quality: {$call->lead_quality} (Score: {$call->lead_score}/10)");
                $this->info("📝 Summary: {$call->ai_summary}");
                if ($call->key_intents) {
                    $this->info("🔑 Key Intents: " . implode(', ', $call->key_intents));
                }
            } else {
                $this->error("❌ Failed to analyze sentiment");
            }
            
            $this->newLine();
        }

        // Display summary table
        $this->info('📊 Summary of All Test Results:');
        $this->table(
            ['Scenario', 'Sentiment', 'Confidence', 'Lead Quality', 'Lead Score', 'Key Intents'],
            array_map(function($r) {
                return [
                    $r['scenario'],
                    $r['sentiment'],
                    $r['confidence'],
                    $r['lead_quality'],
                    $r['lead_score'],
                    substr($r['intents'], 0, 50) . (strlen($r['intents']) > 50 ? '...' : ''),
                ];
            }, $results)
        );

        $this->newLine();
        $this->info('✅ Test completed! Check the frontend at /admin/call-logs or /my-calls');
        $this->info('💡 You can now filter by sentiment and lead quality using the dropdown filters.');

        return 0;
    }
}
