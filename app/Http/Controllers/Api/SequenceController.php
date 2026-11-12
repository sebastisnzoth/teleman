<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Sequence;
use App\Models\SequenceEnrollment;
use App\Services\SequenceEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SequenceController extends Controller
{
    public function __construct(protected SequenceEngine $sequenceEngine)
    {
    }

    /**
     * List all sequences for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $sequences = Sequence::forUser($user->id)
            ->withCount(['steps', 'enrollments'])
            ->with('steps')
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json($sequences);
    }

    /**
     * Show a specific sequence
     */
    public function show(Request $request, Sequence $sequence): JsonResponse
    {
        $this->authorize('view', $sequence);

        $sequence->load(['steps', 'enrollments' => function ($query) {
            $query->latest()->limit(10);
        }]);

        return response()->json($sequence);
    }

    /**
     * Create a new sequence
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'trigger_type' => 'required|in:no_answer,interested,callback_requested,not_interested,manual,completed',
            'trigger_conditions' => 'nullable|array',
            'stop_conditions' => 'nullable|array',
            'is_active' => 'boolean',
            'use_smart_timing' => 'boolean',
            'max_enrollments' => 'nullable|integer|min:1',
            'priority' => 'integer|min:1|max:10',
            'steps' => 'required|array|min:1',
            'steps.*.step_name' => 'required|string',
            'steps.*.delay_amount' => 'required|integer|min:0',
            'steps.*.delay_unit' => 'required|in:minutes,hours,days',
            'steps.*.action_type' => 'required|in:call,sms,email,wait,webhook',
            'steps.*.action_config' => 'nullable|array',
            'steps.*.conditions' => 'nullable|array',
            'steps.*.stop_if' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = $request->user();
        $data = $validator->validated();

        // Create sequence
        $sequence = Sequence::create([
            'user_id' => $user->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'trigger_type' => $data['trigger_type'],
            'trigger_conditions' => $data['trigger_conditions'] ?? [],
            'stop_conditions' => $data['stop_conditions'] ?? [],
            'is_active' => $data['is_active'] ?? false,
            'use_smart_timing' => $data['use_smart_timing'] ?? false,
            'max_enrollments' => $data['max_enrollments'] ?? null,
            'priority' => $data['priority'] ?? 5,
        ]);

        // Create steps
        foreach ($data['steps'] as $index => $stepData) {
            $sequence->steps()->create([
                'step_number' => $index + 1,
                'step_name' => $stepData['step_name'],
                'delay_amount' => $stepData['delay_amount'],
                'delay_unit' => $stepData['delay_unit'],
                'action_type' => $stepData['action_type'],
                'action_config' => $stepData['action_config'] ?? [],
                'conditions' => $stepData['conditions'] ?? [],
                'stop_if' => $stepData['stop_if'] ?? [],
            ]);
        }

        $sequence->load('steps');

        return response()->json([
            'success' => true,
            'data' => $sequence,
        ], 201);
    }

    /**
     * Update a sequence
     */
    public function update(Request $request, Sequence $sequence): JsonResponse
    {
        $this->authorize('update', $sequence);

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'trigger_type' => 'in:no_answer,interested,callback_requested,not_interested,manual,completed',
            'trigger_conditions' => 'nullable|array',
            'stop_conditions' => 'nullable|array',
            'is_active' => 'boolean',
            'use_smart_timing' => 'boolean',
            'max_enrollments' => 'nullable|integer|min:1',
            'priority' => 'integer|min:1|max:10',
            'steps' => 'array|min:1',
            'steps.*.step_name' => 'required|string',
            'steps.*.delay_amount' => 'required|integer|min:0',
            'steps.*.delay_unit' => 'required|in:minutes,hours,days',
            'steps.*.action_type' => 'required|in:call,sms,email,wait,webhook',
            'steps.*.action_config' => 'nullable|array',
            'steps.*.conditions' => 'nullable|array',
            'steps.*.stop_if' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // Update sequence
        $sequence->update(array_filter([
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'trigger_type' => $data['trigger_type'] ?? null,
            'trigger_conditions' => $data['trigger_conditions'] ?? null,
            'stop_conditions' => $data['stop_conditions'] ?? null,
            'is_active' => $data['is_active'] ?? null,
            'use_smart_timing' => $data['use_smart_timing'] ?? null,
            'max_enrollments' => $data['max_enrollments'] ?? null,
            'priority' => $data['priority'] ?? null,
        ], fn($value) => $value !== null));

        // Update steps if provided
        if (isset($data['steps'])) {
            // Delete existing steps
            $sequence->steps()->delete();

            // Create new steps
            foreach ($data['steps'] as $index => $stepData) {
                $sequence->steps()->create([
                    'step_number' => $index + 1,
                    'step_name' => $stepData['step_name'],
                    'delay_amount' => $stepData['delay_amount'],
                    'delay_unit' => $stepData['delay_unit'],
                    'action_type' => $stepData['action_type'],
                    'action_config' => $stepData['action_config'] ?? [],
                    'conditions' => $stepData['conditions'] ?? [],
                    'stop_if' => $stepData['stop_if'] ?? [],
                ]);
            }
        }

        $sequence->load('steps');

        return response()->json([
            'success' => true,
            'data' => $sequence,
        ]);
    }

    /**
     * Delete a sequence
     */
    public function destroy(Request $request, Sequence $sequence): JsonResponse
    {
        $this->authorize('delete', $sequence);

        // Check if sequence has active enrollments
        $activeEnrollments = $sequence->enrollments()->where('status', 'active')->count();

        if ($activeEnrollments > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete sequence with {$activeEnrollments} active enrollments. Please deactivate the sequence first.",
            ], 400);
        }

        $sequence->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sequence deleted successfully',
        ]);
    }

    /**
     * Activate a sequence
     */
    public function activate(Request $request, Sequence $sequence): JsonResponse
    {
        $this->authorize('update', $sequence);

        $sequence->update(['is_active' => true]);

        return response()->json([
            'success' => true,
            'data' => $sequence,
        ]);
    }

    /**
     * Deactivate a sequence
     */
    public function deactivate(Request $request, Sequence $sequence): JsonResponse
    {
        $this->authorize('update', $sequence);

        $sequence->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'data' => $sequence,
        ]);
    }

    /**
     * Get enrollments for a sequence
     */
    public function enrollments(Request $request, Sequence $sequence): JsonResponse
    {
        $this->authorize('view', $sequence);

        $enrollments = $sequence->enrollments()
            ->with(['contact', 'campaignContact', 'campaign'])
            ->when($request->input('status'), function ($query, $status) {
                return $query->where('status', $status);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return response()->json($enrollments);
    }

    /**
     * Get analytics for a sequence
     */
    public function analytics(Request $request, Sequence $sequence): JsonResponse
    {
        $this->authorize('view', $sequence);

        $analytics = $this->sequenceEngine->getSequenceAnalytics($sequence);

        return response()->json([
            'success' => true,
            'data' => $analytics,
        ]);
    }

    /**
     * Manually enroll a contact in a sequence
     */
    public function enrollContact(Request $request, Contact $contact): JsonResponse
    {
        $this->authorize('update', $contact);

        $validator = Validator::make($request->all(), [
            'sequence_id' => 'required|exists:follow_up_sequences,id',
            'campaign_contact_id' => 'required|exists:campaign_contacts,id',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $sequence = Sequence::findOrFail($data['sequence_id']);

        $this->authorize('view', $sequence);

        $campaignContact = \App\Models\CampaignContact::findOrFail($data['campaign_contact_id']);

        $enrollment = $this->sequenceEngine->enrollContactInSequence(
            $contact,
            $campaignContact,
            $sequence,
            array_merge($data['metadata'] ?? [], ['enrolled_by' => 'manual'])
        );

        if (!$enrollment) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to enroll contact. Contact may already be enrolled or sequence may be at capacity.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $enrollment,
        ]);
    }

    /**
     * Pause an enrollment
     */
    public function pauseEnrollment(Request $request, SequenceEnrollment $enrollment): JsonResponse
    {
        $this->authorize('update', $enrollment->sequence);

        $enrollment->pause();

        return response()->json([
            'success' => true,
            'data' => $enrollment,
        ]);
    }

    /**
     * Resume an enrollment
     */
    public function resumeEnrollment(Request $request, SequenceEnrollment $enrollment): JsonResponse
    {
        $this->authorize('update', $enrollment->sequence);

        $enrollment->resume();

        return response()->json([
            'success' => true,
            'data' => $enrollment,
        ]);
    }

    /**
     * Stop an enrollment
     */
    public function stopEnrollment(Request $request, SequenceEnrollment $enrollment): JsonResponse
    {
        $this->authorize('update', $enrollment->sequence);

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $enrollment->stop($request->input('reason'));

        return response()->json([
            'success' => true,
            'data' => $enrollment,
        ]);
    }
}

