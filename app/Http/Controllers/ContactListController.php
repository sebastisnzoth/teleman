<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ContactListController extends Controller
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
     * Authorize that the contact list belongs to the current user or their parent.
     */
    private function authorizeList(ContactList $contactList): void
    {
        $ownerId = $this->ownerId();
        abort_if((int) $contactList->user_id !== $ownerId, 403, 'This contact list does not belong to you.');
    }

    /**
     * Display a listing of contact lists
     */
    public function index(): InertiaResponse
    {
        $lists = ContactList::where('user_id', $this->ownerId())
            ->withCount('contacts')
            ->latest()
            ->get();

        return Inertia::render('ContactLists/Index', [
            'lists' => $lists,
        ]);
    }

    /**
     * Show the form for creating a new contact list
     */
    public function create(): InertiaResponse
    {
        return Inertia::render('ContactLists/Create');
    }

    /**
     * Store a newly created contact list
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        try {
            ContactList::create([
                'user_id' => $this->ownerId(),
                ...$validated,
            ]);

            return to_route('contact-lists.index')
                ->with('success', 'Contact list created successfully.');

        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create contact list. Please try again.']);
        }
    }

    /**
     * Display the specified contact list
     */
    public function show(ContactList $contactList): InertiaResponse
    {
        $this->authorizeList($contactList);

        // Load contacts with their full data
        $contactList->load(['contacts' => function ($query) {
            $query->select('contacts.id', 'contacts.first_name', 'contacts.last_name', 'contacts.email', 'contacts.phone_number', 'contacts.status')
                  ->withPivot('created_at');
        }]);

        // Transform contacts to include full_name
        $contactListData = $contactList->toArray();
        $contactListData['contacts'] = $contactList->contacts->map(function ($contact) {
            return [
                'id' => $contact->id,
                'name' => $contact->full_name,
                'email' => $contact->email,
                'phone' => $contact->phone_number,
                'status' => $contact->status,
            ];
        })->toArray();

        // Add contacts_count (the actual count from the relationship)
        $contactListData['contacts_count'] = $contactList->contacts->count();

        // Get all owner's contacts that are NOT in this list
        $availableContacts = Contact::where('user_id', $this->ownerId())
            ->whereNotIn('id', $contactList->contacts->pluck('id'))
            ->select('id', 'first_name', 'last_name', 'email', 'phone_number')
            ->get()
            ->map(function ($contact) {
                return [
                    'id' => $contact->id,
                    'name' => $contact->full_name,
                    'email' => $contact->email,
                    'phone' => $contact->phone_number,
                ];
            });

        return Inertia::render('ContactLists/Show', [
            'list' => $contactListData,
            'availableContacts' => $availableContacts,
        ]);
    }

    /**
     * Show the form for editing the specified contact list
     */
    public function edit(ContactList $contactList): InertiaResponse
    {
        $this->authorizeList($contactList);

        return Inertia::render('ContactLists/Edit', [
            'list' => $contactList,
        ]);
    }

    /**
     * Update the specified contact list
     */
    public function update(Request $request, ContactList $contactList): RedirectResponse
    {
        $this->authorizeList($contactList);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        try {
            $contactList->update($validated);

            return back()
                ->with('success', 'Contact list updated successfully.');

        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to update contact list. Please try again.']);
        }
    }

    /**
     * Remove the specified contact list
     */
    public function destroy(ContactList $contactList): RedirectResponse
    {
        $this->authorizeList($contactList);

        $contactList->delete();

        return to_route('contact-lists.index')
            ->with('success', 'Contact list deleted successfully.');
    }

    /**
     * Add contacts to list
     */
    public function addContacts(Request $request, ContactList $contactList): RedirectResponse
    {
        $this->authorizeList($contactList);

        $validated = $request->validate([
            'contact_ids' => 'required|array',
            'contact_ids.*' => 'exists:contacts,id',
        ]);

        $contactList->addContacts($validated['contact_ids']);

        return back()
            ->with('success', sprintf('Added %d contacts to list.', count($validated['contact_ids'])));
    }

    /**
     * Remove contacts from list
     */
    public function removeContacts(Request $request, ContactList $contactList): RedirectResponse
    {
        $this->authorizeList($contactList);

        $validated = $request->validate([
            'contact_ids' => 'required|array',
            'contact_ids.*' => 'exists:contacts,id',
        ]);

        $contactList->removeContacts($validated['contact_ids']);

        return back()
            ->with('success', sprintf('Removed %d contacts from list.', count($validated['contact_ids'])));
    }
}
