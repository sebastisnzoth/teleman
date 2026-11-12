<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Contact;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ContactDataCleaningService
{
    public function __construct(
        private OpenRouterService $aiService
    ) {}

    /**
     * Clean and validate a single contact
     */
    public function cleanContact(Contact $contact, bool $applyChanges = false): array
    {
        Log::info('Cleaning contact data', [
            'contact_id' => $contact->id,
            'phone' => $contact->phone_number,
        ]);

        // Prepare contact data for AI analysis
        $contactData = [
            'phone_number' => $contact->phone_number,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'email' => $contact->email,
            'company' => $contact->company,
            'job_title' => $contact->job_title,
        ];

        // Get AI analysis
        $analysis = $this->aiService->cleanContactData($contactData);

        // Update contact with quality data
        $contact->data_quality_score = $analysis['quality_score'];
        $contact->quality_issues = $analysis['issues'];
        $contact->ai_suggestions = $analysis['suggestions'];
        $contact->quality_checked_at = now();

        // Apply AI suggestions if requested
        if ($applyChanges && !empty($analysis['suggestions'])) {
            foreach ($analysis['suggestions'] as $field => $value) {
                if ($contact->isFillable($field) && !empty($value)) {
                    $contact->$field = $value;
                }
            }
            $contact->manually_verified = false; // Mark as AI-cleaned
        }

        $contact->save();

        Log::info('Contact cleaning completed', [
            'contact_id' => $contact->id,
            'quality_score' => $analysis['quality_score'],
            'issues_count' => count($analysis['issues']),
            'changes_applied' => $applyChanges,
        ]);

        return $analysis;
    }

    /**
     * Clean contact data during import (before saving)
     */
    public function cleanImportData(array $contactData): array
    {
        // Get AI analysis
        $analysis = $this->aiService->cleanContactData($contactData);

        // Apply suggestions automatically for high-confidence changes
        if ($analysis['quality_score'] < 80 && !empty($analysis['suggestions'])) {
            foreach ($analysis['suggestions'] as $field => $value) {
                if (!empty($value) && $this->isHighConfidenceChange($field, $contactData[$field] ?? null, $value)) {
                    $contactData[$field] = $value;
                }
            }
        }

        // Add quality metadata
        $contactData['data_quality_score'] = $analysis['quality_score'];
        $contactData['quality_issues'] = $analysis['issues'];
        $contactData['ai_suggestions'] = $analysis['suggestions'];
        $contactData['quality_checked_at'] = now();

        return $contactData;
    }

    /**
     * Batch clean multiple contacts
     */
    public function cleanBatch(array $contactIds, bool $applyChanges = false, int $limit = 100): array
    {
        $contacts = Contact::whereIn('id', array_slice($contactIds, 0, $limit))->get();
        
        $results = [
            'processed' => 0,
            'improved' => 0,
            'issues_found' => 0,
            'average_score' => 0,
        ];

        $totalScore = 0;

        foreach ($contacts as $contact) {
            try {
                $analysis = $this->cleanContact($contact, $applyChanges);
                
                $results['processed']++;
                $totalScore += $analysis['quality_score'];
                
                if ($analysis['quality_score'] < 100) {
                    $results['issues_found']++;
                }
                
                if (!empty($analysis['suggestions']) && $applyChanges) {
                    $results['improved']++;
                }
                
            } catch (\Exception $e) {
                Log::error('Contact cleaning failed', [
                    'contact_id' => $contact->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $results['average_score'] = $results['processed'] > 0 
            ? round($totalScore / $results['processed'], 2) 
            : 0;

        return $results;
    }

    /**
     * Find potential duplicate contacts
     */
    public function findDuplicates(Contact $contact, int $limit = 10): array
    {
        $query = Contact::where('user_id', $contact->user_id)
            ->where('id', '!=', $contact->id);

        // Find by exact phone match
        $duplicates = [];
        
        if ($contact->phone_number) {
            $phoneMatches = $query->where('phone_number', $contact->phone_number)->limit($limit)->get();
            foreach ($phoneMatches as $match) {
                $duplicates[] = [
                    'contact' => $match,
                    'match_type' => 'phone_exact',
                    'confidence' => 100,
                ];
            }
        }

        // Find by similar name
        if ($contact->first_name && $contact->last_name) {
            $nameMatches = Contact::where('user_id', $contact->user_id)
                ->where('id', '!=', $contact->id)
                ->where('first_name', 'LIKE', $contact->first_name)
                ->where('last_name', 'LIKE', $contact->last_name)
                ->limit($limit)
                ->get();
                
            foreach ($nameMatches as $match) {
                if (!collect($duplicates)->pluck('contact.id')->contains($match->id)) {
                    $duplicates[] = [
                        'contact' => $match,
                        'match_type' => 'name_similar',
                        'confidence' => 80,
                    ];
                }
            }
        }

        // Find by email
        if ($contact->email) {
            $emailMatches = Contact::where('user_id', $contact->user_id)
                ->where('id', '!=', $contact->id)
                ->where('email', $contact->email)
                ->limit($limit)
                ->get();
                
            foreach ($emailMatches as $match) {
                if (!collect($duplicates)->pluck('contact.id')->contains($match->id)) {
                    $duplicates[] = [
                        'contact' => $match,
                        'match_type' => 'email_exact',
                        'confidence' => 95,
                    ];
                }
            }
        }

        return array_slice($duplicates, 0, $limit);
    }

    /**
     * Get quality statistics for user's contacts
     */
    public function getQualityStatistics(int $userId): array
    {
        $contacts = Contact::where('user_id', $userId)
            ->whereNotNull('data_quality_score')
            ->get();

        if ($contacts->isEmpty()) {
            return [
                'total_contacts' => 0,
                'checked_contacts' => 0,
                'average_score' => 0,
                'high_quality' => 0,
                'medium_quality' => 0,
                'low_quality' => 0,
                'with_issues' => 0,
            ];
        }

        return [
            'total_contacts' => Contact::where('user_id', $userId)->count(),
            'checked_contacts' => $contacts->count(),
            'average_score' => round($contacts->avg('data_quality_score'), 2),
            'high_quality' => $contacts->where('data_quality_score', '>=', 80)->count(),
            'medium_quality' => $contacts->where('data_quality_score', '>=', 60)->where('data_quality_score', '<', 80)->count(),
            'low_quality' => $contacts->where('data_quality_score', '<', 60)->count(),
            'with_issues' => $contacts->filter(fn($c) => !empty($c->quality_issues))->count(),
        ];
    }

    /**
     * Determine if a change is high confidence (auto-apply safe)
     */
    private function isHighConfidenceChange(?string $original, string $suggested): bool
    {
        // Phone number formatting (add country code)
        if (Str::startsWith($suggested, '+') && !Str::startsWith($original ?? '', '+')) {
            return true;
        }

        // Name capitalization only
        if (strtolower($original ?? '') === strtolower($suggested)) {
            return true;
        }

        // Company name formatting (capitalization changes)
        if (strlen($suggested) > 3 && strtolower($original ?? '') === strtolower($suggested)) {
            return true;
        }

        return false;
    }

    /**
     * Apply AI suggestions to a contact
     */
    public function applySuggestions(Contact $contact): bool
    {
        if (empty($contact->ai_suggestions)) {
            return false;
        }

        $applied = false;
        foreach ($contact->ai_suggestions as $field => $value) {
            if ($contact->isFillable($field) && !empty($value)) {
                $contact->$field = $value;
                $applied = true;
            }
        }

        if ($applied) {
            $contact->ai_suggestions = null; // Clear after applying
            $contact->manually_verified = true;
            $contact->save();
        }

        return $applied;
    }
}
