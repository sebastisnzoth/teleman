<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ContactTag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ContactTagController extends Controller
{
    /**
     * Get the data owner ID (agent uses parent's ID).
     */
    private function ownerId(): int
    {
        $user = Auth::user();
        return $user->isAgent() ? $user->parent_user_id : $user->id;
    }

    /**
     * Display a listing of contact tags
     */
    public function index(): InertiaResponse
    {
        $tags = ContactTag::where('user_id', $this->ownerId())
            ->withCount('contacts')
            ->latest()
            ->get();

        return Inertia::render('ContactTags/Index', [
            'tags' => $tags,
        ]);
    }

    /**
     * Store a newly created contact tag
     */
    public function store(Request $request): RedirectResponse
    {
        $ownerId = $this->ownerId();

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:contact_tags,name,NULL,id,user_id,' . $ownerId,
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        try {
            ContactTag::create([
                'user_id' => $ownerId,
                'color' => $validated['color'] ?? '#3b82f6',
                ...$validated,
            ]);

            return redirect()->back()
                ->with('success', 'Tag created successfully.');

        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000') {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['name' => 'A tag with this name already exists.']);
            }
            throw $e;
        }
    }

    /**
     * Update the specified contact tag
     */
    public function update(Request $request, ContactTag $tag): RedirectResponse
    {
        // Authorize — check against owner ID
        abort_if($tag->user_id !== $this->ownerId(), 403);

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:contact_tags,name,' . $tag->id . ',id,user_id,' . $this->ownerId(),
            'color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        try {
            $tag->update($validated);

            return redirect()->back()
                ->with('success', 'Tag updated successfully.');

        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000') {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['name' => 'A tag with this name already exists.']);
            }
            throw $e;
        }
    }

    /**
     * Remove the specified contact tag
     */
    public function destroy(ContactTag $tag): RedirectResponse
    {
        // Authorize — check against owner ID
        abort_if($tag->user_id !== $this->ownerId(), 403);

        $tag->delete();

        return redirect()->back()
            ->with('success', 'Tag deleted successfully.');
    }
}
