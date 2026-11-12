<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Services\ContactDataCleaningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ContactQualityController extends Controller
{
    public function __construct(
        private ContactDataCleaningService $cleaningService
    ) {}

    /**
     * Preview quality of contact data before import
     */
    public function previewQuality(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contacts' => 'required|array|min:1|max:100',
            'contacts.*.phone_number' => 'required|string',
            'contacts.*.first_name' => 'nullable|string',
            'contacts.*.last_name' => 'nullable|string',
            'contacts.*.email' => 'nullable|email',
            'contacts.*.company' => 'nullable|string',
            'contacts.*.job_title' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $contacts = $request->input('contacts');
            $results = [];

            foreach ($contacts as $index => $contactData) {
                $analysis = $this->cleaningService->cleanImportData($contactData);
                $results[] = [
                    'index' => $index,
                    'original' => $contactData,
                    'cleaned' => $analysis['cleaned_data'],
                    'quality_score' => $analysis['quality_score'],
                    'issues' => $analysis['issues'],
                    'suggestions' => $analysis['suggestions'],
                    'is_valid' => $analysis['is_valid'],
                ];
            }

            // Calculate summary statistics
            $totalScore = array_sum(array_column($results, 'quality_score'));
            $avgScore = count($results) > 0 ? $totalScore / count($results) : 0;
            $highQuality = count(array_filter($results, fn($r) => $r['quality_score'] >= 80));
            $mediumQuality = count(array_filter($results, fn($r) => $r['quality_score'] >= 60 && $r['quality_score'] < 80));
            $lowQuality = count(array_filter($results, fn($r) => $r['quality_score'] < 60));

            return response()->json([
                'results' => $results,
                'summary' => [
                    'total' => count($results),
                    'average_score' => round($avgScore, 2),
                    'high_quality' => $highQuality,
                    'medium_quality' => $mediumQuality,
                    'low_quality' => $lowQuality,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to preview contact quality', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to analyze contacts',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check quality of a single contact
     */
    public function checkContact(Contact $contact, Request $request): JsonResponse
    {
        // Authorize
        if ($contact->user_id !== $request->user()->id && !$request->user()->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $analysis = $this->cleaningService->cleanContact($contact, false);
            $contact->refresh();

            return response()->json([
                'contact' => $contact,
                'analysis' => $analysis,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to check contact quality', [
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to analyze contact',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Apply AI suggestions to a contact
     */
    public function applySuggestions(Contact $contact, Request $request): JsonResponse
    {
        // Authorize
        if ($contact->user_id !== $request->user()->id && !$request->user()->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $this->cleaningService->applySuggestions($contact);
            $contact->refresh();

            return response()->json([
                'message' => 'Suggestions applied successfully',
                'contact' => $contact,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to apply suggestions', [
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to apply suggestions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clean a batch of contacts
     */
    public function cleanBatch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contact_ids' => 'required|array|min:1|max:100',
            'contact_ids.*' => 'required|integer|exists:contacts,id',
            'apply_changes' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $contactIds = $request->input('contact_ids');
        $applyChanges = $request->input('apply_changes', false);

        // Verify all contacts belong to user
        $userId = $request->user()->id;
        $validContacts = Contact::whereIn('id', $contactIds)
            ->where('user_id', $userId)
            ->pluck('id')
            ->toArray();

        if (count($validContacts) !== count($contactIds)) {
            return response()->json(['message' => 'Some contacts do not belong to you'], 403);
        }

        try {
            $stats = $this->cleaningService->cleanBatch($contactIds, $applyChanges);

            return response()->json([
                'message' => 'Batch cleaning completed',
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to clean batch', [
                'contact_ids' => $contactIds,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to clean contacts',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get quality statistics for user's contacts
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $stats = $this->cleaningService->getQualityStatistics($request->user()->id);

            return response()->json($stats);
        } catch (\Exception $e) {
            Log::error('Failed to get quality statistics', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to get statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Find duplicate contacts
     */
    public function findDuplicates(Contact $contact, Request $request): JsonResponse
    {
        // Authorize
        if ($contact->user_id !== $request->user()->id && !$request->user()->is_admin) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        try {
            $duplicates = $this->cleaningService->findDuplicates($contact);

            return response()->json([
                'contact' => $contact,
                'duplicates' => $duplicates,
                'count' => count($duplicates),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to find duplicates', [
                'contact_id' => $contact->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to find duplicates',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
