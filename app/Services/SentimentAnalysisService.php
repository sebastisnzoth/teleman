<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Call;
use Illuminate\Support\Facades\Log;

class SentimentAnalysisService
{
    public function __construct(
        private OpenRouterService $openRouterService
    ) {}

    /**
     * Process sentiment analysis for a call with transcript
     */
    public function processCall(Call $call): bool
    {
        // Validate call has transcript
        if (!$call->hasTranscript()) {
            Log::info('Sentiment analysis skipped: No transcript', ['call_id' => $call->id]);
            return false;
        }

        // Check if already analyzed
        if ($call->hasSentimentAnalysis()) {
            Log::info('Sentiment analysis skipped: Already analyzed', [
                'call_id' => $call->id,
                'analyzed_at' => $call->sentiment_analyzed_at,
            ]);
            return false;
        }

        // Check transcript length (minimum 10 characters for meaningful analysis)
        if (strlen($call->transcript_text) < 10) {
            Log::info('Sentiment analysis skipped: Transcript too short', [
                'call_id' => $call->id,
                'length' => strlen($call->transcript_text),
            ]);
            return false;
        }

        try {
            Log::info('Starting sentiment analysis', [
                'call_id' => $call->id,
                'transcript_length' => strlen($call->transcript_text),
            ]);

            // Analyze sentiment using AI
            $result = $this->openRouterService->analyzeSentiment($call->transcript_text);

            // Update call record
            $call->update([
                'sentiment' => $result['sentiment'],
                'sentiment_confidence' => $result['confidence'],
                'lead_score' => $result['lead_score'],
                'lead_quality' => $result['lead_quality'],
                'ai_summary' => $result['summary'],
                'key_intents' => $result['key_intents'],
                'sentiment_analyzed_at' => now(),
            ]);

            Log::info('Sentiment analysis completed', [
                'call_id' => $call->id,
                'sentiment' => $result['sentiment'],
                'lead_quality' => $result['lead_quality'],
                'lead_score' => $result['lead_score'],
            ]);

            // Update campaign contact if exists
            if ($call->campaignContact) {
                $this->updateCampaignContact($call);
            }

            return true;

        } catch (\Exception $e) {
            Log::error('Sentiment analysis failed', [
                'call_id' => $call->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * Update campaign contact based on sentiment analysis
     */
    private function updateCampaignContact(Call $call): void
    {
        try {
            $contact = $call->campaignContact;
            
            // Update contact status based on lead quality
            $statusMap = [
                'hot' => 'answered',
                'warm' => 'answered',
                'cold' => 'answered',
                'not_interested' => 'opted_out',
            ];

            if (isset($statusMap[$call->lead_quality])) {
                $newStatus = $statusMap[$call->lead_quality];
                
                // Only update if current status is less advanced
                if ($contact->status !== 'completed' && $contact->status !== 'opted_out') {
                    $contact->update(['status' => $newStatus]);
                }
            }

            // Add AI summary to notes
            if ($call->ai_summary) {
                $existingNotes = $contact->notes ?? '';
                $newNote = "\n\n[AI Analysis] {$call->ai_summary}";
                $newNote .= "\nLead Quality: {$call->lead_quality} (Score: {$call->lead_score}/10)";
                $newNote .= "\nSentiment: {$call->sentiment} ({$call->sentiment_confidence}% confidence)";
                
                if (!empty($call->key_intents)) {
                    $newNote .= "\nIntents: " . implode(', ', $call->key_intents);
                }
                
                $contact->update([
                    'notes' => $existingNotes . $newNote,
                ]);
            }

            Log::info('Campaign contact updated with sentiment data', [
                'call_id' => $call->id,
                'contact_id' => $contact->id,
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to update campaign contact', [
                'call_id' => $call->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Process multiple calls in batch
     */
    public function processBatch(array $callIds, int $limit = 10): array
    {
        $results = [
            'processed' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        $calls = Call::whereIn('id', $callIds)
            ->whereNotNull('transcript_text')
            ->whereNull('sentiment')
            ->limit($limit)
            ->get();

        foreach ($calls as $call) {
            try {
                if ($this->processCall($call)) {
                    $results['processed']++;
                } else {
                    $results['skipped']++;
                }
            } catch (\Exception $e) {
                $results['failed']++;
                Log::error('Batch sentiment analysis failed', [
                    'call_id' => $call->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Re-analyze a call (even if already analyzed)
     */
    public function reanalyze(Call $call): bool
    {
        if (!$call->hasTranscript()) {
            return false;
        }

        try {
            $result = $this->openRouterService->analyzeSentiment($call->transcript_text);

            $call->update([
                'sentiment' => $result['sentiment'],
                'sentiment_confidence' => $result['confidence'],
                'lead_score' => $result['lead_score'],
                'lead_quality' => $result['lead_quality'],
                'ai_summary' => $result['summary'],
                'key_intents' => $result['key_intents'],
                'sentiment_analyzed_at' => now(),
            ]);

            Log::info('Call re-analyzed', ['call_id' => $call->id]);
            return true;

        } catch (\Exception $e) {
            Log::error('Re-analysis failed', [
                'call_id' => $call->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
