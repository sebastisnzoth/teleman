<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\CampaignTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CampaignTemplateController extends Controller
{
    /**
     * Get all templates (system + user's custom templates)
     */
    public function index(Request $request): InertiaResponse
    {
        $userId = $request->user()->id ?? 1;
        $category = $request->query('category');

        $query = CampaignTemplate::query()
            ->where(function ($q) use ($userId) {
                $q->where('is_system_template', true)
                  ->orWhere('user_id', $userId);
            });

        if ($category) {
            $query->where('category', $category);
        }

        $templates = $query->orderByDesc('is_system_template')
            ->orderByDesc('usage_count')
            ->paginate(15);

        return Inertia::render('CampaignTemplates/Index', [
            'templates' => $templates,
        ]);
    }

    /**
     * Get a single template
     */
    public function show(CampaignTemplate $template): InertiaResponse
    {
        return Inertia::render('CampaignTemplates/Show', [
            'template' => $template,
        ]);
    }

    /**
     * Show the form for creating a new template
     */
    public function create(Request $request): InertiaResponse
    {
        return Inertia::render('CampaignTemplates/Create', [
            'categories' => [
                'promotional' => 'Promotional',
                'lead_qualification' => 'Lead Qualification',
                'survey' => 'Survey',
                'notification' => 'Notification',
                'personalized' => 'Personalized',
            ],
        ]);
    }

    /**
     * Create a custom template
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|in:promotional,lead_qualification,survey,notification,personalized',
            'template_data' => 'required|array',
            'template_data.message' => 'nullable|string|max:250',
            'template_data.type' => 'nullable|in:text_to_speech,voice_to_voice',
            'template_data.expected_variables' => 'nullable|array',
            'template_data.campaign_variables' => 'nullable|array',
            'template_data.enable_recording' => 'nullable|boolean',
            'template_data.enable_dtmf' => 'nullable|boolean',
            'template_data.dtmf_actions' => 'nullable|array',
        ]);

        $template = CampaignTemplate::create([
            ...$validated,
            'user_id' => $request->user()->id ?? 1,
            'is_system_template' => false,
        ]);

        return redirect()->route('campaign-templates.show', $template->id)
            ->with('success', 'Template created successfully');
    }

    /**
     * Update a custom template
     */
    public function update(Request $request, CampaignTemplate $template): JsonResponse
    {
        // Only allow updating user's own templates
        if ($template->is_system_template || $template->user_id !== ($request->user()->id ?? 1)) {
            return response()->json(['message' => 'Cannot modify this template'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category' => 'sometimes|in:promotional,lead_qualification,survey,notification,personalized',
            'template_data' => 'sometimes|array',
            'template_data.message' => 'nullable|string|max:250',
            'template_data.type' => 'nullable|in:text_to_speech,voice_to_voice',
            'template_data.expected_variables' => 'nullable|array',
            'template_data.campaign_variables' => 'nullable|array',
            'template_data.enable_recording' => 'nullable|boolean',
            'template_data.enable_dtmf' => 'nullable|boolean',
            'template_data.dtmf_actions' => 'nullable|array',
        ]);

        $template->update($validated);

        return response()->json($template);
    }

    /**
     * Delete a custom template
     */
    public function destroy(Request $request, CampaignTemplate $template): JsonResponse
    {
        // Only allow deleting user's own templates
        if ($template->is_system_template || $template->user_id !== ($request->user()->id ?? 1)) {
            return response()->json(['message' => 'Cannot delete this template'], 403);
        }

        $template->delete();

        return response()->json(['message' => 'Template deleted successfully']);
    }

    /**
     * Use a template (increment usage count and return data)
     */
    public function use(CampaignTemplate $template): RedirectResponse
    {
        $template->incrementUsage();

        return redirect()->route('campaigns.create', [
            'template' => $template->id,
        ]);
    }

    /**
     * Create a template from an existing campaign
     */
    public function createFromCampaign(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'campaign_id' => ['required', 'exists:campaigns,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'category' => ['required', 'in:promotional,notification,survey,lead_qualification,personalized,general'],
        ]);

        // Get the campaign
        $campaign = \App\Models\Campaign::findOrFail($validated['campaign_id']);

        // Ensure user owns the campaign
        if ($campaign->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Create template data from campaign
        $templateData = [
            'type' => $campaign->type,
            'message' => $campaign->message,
            'language' => $campaign->language,
            'voice' => $campaign->voice,
            'enable_recording' => $campaign->enable_recording,
            'enable_dtmf' => $campaign->enable_dtmf,
            'max_concurrent_calls' => $campaign->max_concurrent_calls,
            'retry_attempts' => $campaign->retry_attempts,
            'retry_delay_minutes' => $campaign->retry_delay_minutes,
            'expected_variables' => $campaign->expected_variables ?? [],
            'campaign_variables' => $campaign->campaign_variables ?? [],
        ];

        // Create the template
        $template = CampaignTemplate::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? "Template created from campaign: {$campaign->name}",
            'category' => $validated['category'],
            'template_data' => $templateData,
            'is_system_template' => false,
            'usage_count' => 0,
        ]);

        return response()->json([
            'message' => 'Template created successfully',
            'template' => $template,
        ], 201);
    }
}
