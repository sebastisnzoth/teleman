<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignContact;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CampaignContactController extends Controller
{
    /**
     * Store a new contact in the campaign
     */
    public function store(Request $request, Campaign $campaign): RedirectResponse
    {
        $this->authorize('update', $campaign);

        $validated = $request->validate([
            'phone_number' => 'required|string|regex:/^\+?[1-9]\d{1,14}$/',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'company' => 'nullable|string|max:255',
            'variables' => 'nullable|array',
            'variables.*' => 'nullable|string|max:1000',
        ]);

        // Format phone number to E.164
        $phoneNumber = $this->formatPhoneNumber($validated['phone_number']);

        // Check for duplicates
        $exists = $campaign->campaignContacts()
            ->where('phone_number', $phoneNumber)
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'phone_number' => 'This phone number already exists in the campaign.'
            ])->withInput();
        }

        // Create contact
        $campaign->campaignContacts()->create([
            'phone_number' => $phoneNumber,
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'email' => $validated['email'] ?? null,
            'company' => $validated['company'] ?? null,
            'variables' => $validated['variables'] ?? [],
            'status' => 'pending',
        ]);

        // Update campaign total_contacts
        $campaign->update([
            'total_contacts' => $campaign->campaignContacts()->count(),
        ]);

        return back()->with('success', 'Contact added successfully!');
    }

    /**
     * Update a campaign contact
     */
    public function update(Request $request, Campaign $campaign, CampaignContact $contact): RedirectResponse
    {
        $this->authorize('update', $campaign);

        // Verify contact belongs to campaign
        if ($contact->campaign_id !== $campaign->id) {
            abort(404);
        }

        $validated = $request->validate([
            'phone_number' => 'required|string|regex:/^\+?[1-9]\d{1,14}$/',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'company' => 'nullable|string|max:255',
            'variables' => 'nullable|array',
            'variables.*' => 'nullable|string|max:1000',
        ]);

        // Format phone number
        $phoneNumber = $this->formatPhoneNumber($validated['phone_number']);

        // Check for duplicates (exclude current contact)
        $exists = $campaign->campaignContacts()
            ->where('phone_number', $phoneNumber)
            ->where('id', '!=', $contact->id)
            ->exists();

        if ($exists) {
            return back()->withErrors([
                'phone_number' => 'This phone number already exists in the campaign.'
            ])->withInput();
        }

        // Update contact
        $contact->update([
            'phone_number' => $phoneNumber,
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'email' => $validated['email'] ?? null,
            'company' => $validated['company'] ?? null,
            'variables' => $validated['variables'] ?? [],
        ]);

        return back()->with('success', 'Contact updated successfully!');
    }

    /**
     * Delete a campaign contact
     */
    public function destroy(Campaign $campaign, CampaignContact $contact): RedirectResponse
    {
        $this->authorize('update', $campaign);

        // Verify contact belongs to campaign
        if ($contact->campaign_id !== $campaign->id) {
            abort(404);
        }

        $contact->delete();

        // Update campaign total_contacts
        $campaign->update([
            'total_contacts' => $campaign->campaignContacts()->count(),
        ]);

        return back()->with('success', 'Contact removed from campaign.');
    }

    /**
     * Get all unique variable keys from campaign contacts
     */
    public function getVariableKeys(Campaign $campaign): JsonResponse
    {
        $this->authorize('view', $campaign);

        $contacts = $campaign->campaignContacts()
            ->select('variables')
            ->whereNotNull('variables')
            ->get();

        $totalContacts = $contacts->count();
        $variableKeys = [];
        $variableExamples = [];

        foreach ($contacts as $contact) {
            $vars = $contact->variables ?? [];
            foreach ($vars as $key => $value) {
                if (!in_array($key, $variableKeys)) {
                    $variableKeys[] = $key;
                    $variableExamples[$key] = [
                        'example' => $value,
                        'count' => 1,
                    ];
                } else {
                    $variableExamples[$key]['count']++;
                }
            }
        }

        return response()->json([
            'variables' => array_map(function ($key) use ($variableExamples, $totalContacts) {
                return [
                    'key' => $key,
                    'example' => $variableExamples[$key]['example'],
                    'count' => $variableExamples[$key]['count'],
                    'coverage' => round(($variableExamples[$key]['count'] / max(1, $totalContacts)) * 100),
                ];
            }, $variableKeys)
        ]);
    }

    /**
     * Format phone number to E.164 format
     */
    private function formatPhoneNumber(string $phoneNumber): string
    {
        // Remove all non-numeric characters except +
        $cleaned = preg_replace('/[^0-9+]/', '', $phoneNumber);

        // If doesn't start with +, add +1 (assuming US)
        if (!str_starts_with($cleaned, '+')) {
            $cleaned = '+1' . $cleaned;
        }

        return $cleaned;
    }
}
