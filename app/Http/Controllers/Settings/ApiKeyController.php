<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ApiKeyController extends Controller
{
    /**
     * Display API keys management page
     */
    public function index()
    {
        $apiKeys = Auth::user()->apiKeys()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($key) {
                return [
                    'id' => $key->id,
                    'name' => $key->name,
                    'key' => $key->key,
                    'masked_key' => substr($key->key, 0, 12) . '...' . substr($key->key, -6),
                    'type' => $key->type,
                    'is_active' => $key->is_active,
                    'allowed_domains' => $key->allowed_domains ?? [],
                    'permissions' => $key->permissions ?? [],
                    'last_used_at' => $key->last_used_at?->diffForHumans(),
                    'created_at' => $key->created_at->format('M d, Y'),
                    'expires_at' => $key->expires_at?->format('M d, Y'),
                ];
            });

        return Inertia::render('settings/api-keys/index', [
            'apiKeys' => $apiKeys,
        ]);
    }

    /**
     * Create a new API key
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:live,test',
            'allowed_domains' => 'nullable|array',
            'allowed_domains.*' => 'string',
            'permissions' => 'nullable|array',
        ]);

        $apiKey = ApiKey::generate(
            Auth::id(),
            $validated['name'],
            $validated['type']
        );

        if (!empty($validated['allowed_domains'])) {
            $apiKey->update([
                'allowed_domains' => $validated['allowed_domains'],
            ]);
        }

        if (!empty($validated['permissions'])) {
            $apiKey->update([
                'permissions' => $validated['permissions'],
            ]);
        }

        return redirect()->back()->with('success', 'API key created successfully!');
    }

    /**
     * Update API key
     */
    public function update(Request $request, ApiKey $apiKey)
    {
        // Check ownership
        if ($apiKey->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean',
            'allowed_domains' => 'sometimes|nullable|array',
            'allowed_domains.*' => 'string',
            'permissions' => 'sometimes|nullable|array',
        ]);

        $apiKey->update($validated);

        return redirect()->back()->with('success', 'API key updated successfully!');
    }

    /**
     * Delete API key
     */
    public function destroy(ApiKey $apiKey)
    {
        // Check ownership
        if ($apiKey->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $apiKey->delete();

        return redirect()->back()->with('success', 'API key deleted successfully!');
    }

    /**
     * Toggle API key active status
     */
    public function toggleStatus(ApiKey $apiKey)
    {
        // Check ownership
        if ($apiKey->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $apiKey->update([
            'is_active' => !$apiKey->is_active,
        ]);

        $status = $apiKey->is_active ? 'activated' : 'deactivated';
        return redirect()->back()->with('success', "API key {$status} successfully!");
    }
}
