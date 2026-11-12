<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Contacts\ImportContactsAction;
use App\Jobs\ProcessContactImport;
use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\ContactList;
use App\Models\ContactTag;
use App\Services\PhoneValidationService;
use App\Services\OpenRouterService;
use App\Events\CRM\ContactAddedEvent;
use App\Events\CRM\ContactUpdatedEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ContactController extends Controller
{
    /**
     * Display a listing of contacts
     */
    public function index(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', Contact::class);

        $user = Auth::user();

        // Build query based on role
        $query = Contact::query()->with(['tags', 'lists']);

        if ($user->isAdmin()) {
            // Admin can see all contacts
        } elseif ($user->isCustomer()) {
            // Customer can see only their own contacts
            $query->where('user_id', $user->id);
        } elseif ($user->isAgent()) {
            // Agent can see their parent's contacts
            $query->where('user_id', $user->parent_user_id);
        }

        // Search
        if ($search = $request->input('search')) {
            $query->search($search);
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Filter by opted out
        if ($request->has('opted_out')) {
            $query->optedOut();
        }

        // Filter by tag
        if ($tagId = $request->input('tag_id')) {
            $query->whereHas('tags', fn($q) => $q->where('contact_tag_id', $tagId));
        }

        // Filter by list
        if ($listId = $request->input('list_id')) {
            if ($listId === 'unassigned') {
                $query->doesntHave('lists');
            } else {
                $query->whereHas('lists', fn($q) => $q->where('contact_list_id', $listId));
            }
        }
        if ($minScore = $request->input('min_score')) {
            $query->where('engagement_score', '>=', $minScore);
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        // Paginate
        $perPage = $request->input('per_page', 25);
        $contacts = $query->paginate($perPage);

        // Determine the data owner (agent sees parent's data)
        $ownerId = $user->isAgent() ? $user->parent_user_id : $user->id;

        // Get available tags and lists for filters
        $tags = ContactTag::where('user_id', $ownerId)->get();
        $lists = ContactList::where('user_id', $ownerId)->get();

        // Get statistics
        $stats = [
            'total' => Contact::where('user_id', $ownerId)->count(),
            'active' => Contact::where('user_id', $ownerId)->active()->count(),
            'opted_out' => Contact::where('user_id', $ownerId)->optedOut()->count(),
            'average_engagement' => (float) (Contact::where('user_id', $ownerId)->avg('engagement_score') ?? 0),
        ];

        return Inertia::render('contacts/index', [
            'contacts' => $contacts,
            'tags' => $tags,
            'lists' => $lists,
            'filters' => $request->only(['search', 'status', 'opted_out', 'tag_id', 'list_id', 'min_score']),
            'stats' => $stats,
        ]);
    }

    /**
     * Show the form for creating a new contact
     */
    public function create(): InertiaResponse
    {
        $this->authorize('create', Contact::class);

        $user = Auth::user();
        $ownerId = $user->isAgent() ? $user->parent_user_id : $user->id;

        $tags = ContactTag::where('user_id', $ownerId)->get();
        $lists = ContactList::where('user_id', $ownerId)->get();

        return Inertia::render('contacts/create', [
            'tags' => $tags,
            'lists' => $lists,
        ]);
    }

    /**
     * Store a newly created contact
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Contact::class);

        $validated = $request->validate([
            'phone_number' => 'required|string|regex:/^\+?[1-9]\d{1,14}$/|unique:contacts,phone_number',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'company' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:500',
            'facebook_url' => 'nullable|url|max:500',
            'instagram_url' => 'nullable|url|max:500',
            'linkedin_url' => 'nullable|url|max:500',
            'twitter_url' => 'nullable|url|max:500',
            'job_title' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'timezone' => 'nullable|string|max:255',
            'language' => 'nullable|string|max:255',
            'custom_fields' => 'nullable|array',
            'status' => 'nullable|in:active,inactive,blocked',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:contact_tags,id',
            'list_ids' => 'nullable|array',
            'list_ids.*' => 'exists:contact_lists,id',
        ]);

        try {
            $user = Auth::user();
            $ownerId = $user->isAgent() ? $user->parent_user_id : $user->id;
            $contact = Contact::create([
                'user_id' => $ownerId,
                'source' => 'manual',
                ...$validated,
            ]);

            // Attach tags
            if (!empty($validated['tag_ids'])) {
                $contact->tags()->attach($validated['tag_ids']);
            }

            // Attach lists
            if (!empty($validated['list_ids'])) {
                $contact->lists()->attach($validated['list_ids']);
                
                // Update list counts
                foreach ($validated['list_ids'] as $listId) {
                    $list = ContactList::find($listId);
                    $list?->updateContactCount();
                }
            }

            // Dispatch CRM event
            event(new ContactAddedEvent($contact, 'manual'));

            return redirect()->route('contacts.show', $contact)
                ->with('success', 'Contact created successfully.');
                
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle duplicate phone number error gracefully
            if ($e->getCode() === '23000') {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['phone_number' => 'This phone number is already registered in the system.']);
            }
            throw $e;
        }
    }

    /**
     * Return contact as JSON (used by softphone quick-edit modal)
     */
    public function showJson(Contact $contact): \Illuminate\Http\JsonResponse
    {
        $this->authorize('view', $contact);

        return response()->json([
            'contact' => [
                'id' => $contact->id,
                'first_name' => $contact->first_name,
                'last_name' => $contact->last_name,
                'phone_number' => $contact->phone_number,
                'email' => $contact->email,
                'notes' => $contact->notes,
            ],
        ]);
    }

    /**
     * Display the specified contact
     */
    public function show(Contact $contact): InertiaResponse
    {
        $this->authorize('view', $contact);

        $contact->load([
            'tags',
            'lists',
        ]);

        // Get campaigns (campaign_contacts for this contact)
        $campaigns = $contact->campaigns()
            ->with('campaign:id,name,status')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function ($campaignContact) {
                return [
                    'id' => $campaignContact->id,
                    'name' => $campaignContact->campaign->name ?? 'Unknown',
                    'status' => $campaignContact->campaign->status ?? 'unknown',
                    'contacted_at' => $campaignContact->created_at->toISOString(),
                    'call_status' => $campaignContact->status,
                    'call_duration' => null, // Can be added later if tracking call duration
                ];
            });

        // Get calls linked to this contact
        $recentCalls = \App\Models\Call::where('contact_id', $contact->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get(['id', 'direction', 'status', 'disposition', 'call_notes', 'duration_seconds', 'created_at']);

        // Get activity log
        $activities = collect([]);
        
        // Add campaign activities
        foreach ($campaigns as $campaign) {
            $activities->push([
                'id' => 'campaign_' . $campaign['id'],
                'type' => 'campaign',
                'description' => "Added to campaign: {$campaign['name']}",
                'timestamp' => $campaign['contacted_at'],
            ]);
        }

        // Sort activities by timestamp
        $activities = $activities->sortByDesc('timestamp')->values()->all();

        return Inertia::render('contacts/show', [
            'contact' => $contact,
            'campaigns' => $campaigns->toArray(),
            'recentCalls' => $recentCalls,
            'activities' => $activities,
        ]);
    }

    /**
     * Show the form for editing the specified contact
     */
    public function edit(Contact $contact): InertiaResponse
    {
        // Authorize
        $this->authorize('update', $contact);

        $user = Auth::user();
        $ownerId = $user->isAgent() ? $user->parent_user_id : $user->id;
        $tags = ContactTag::where('user_id', $ownerId)->get();
        $lists = ContactList::where('user_id', $ownerId)->get();

        $contact->load(['tags', 'lists']);

        return Inertia::render('contacts/edit', [
            'contact' => $contact,
            'tags' => $tags,
            'lists' => $lists,
        ]);
    }

    /**
     * Update the specified contact
     */
    public function update(Request $request, Contact $contact): RedirectResponse
    {
        // Authorize
        $this->authorize('update', $contact);

        $validated = $request->validate([
            'phone_number' => 'required|string|regex:/^\+?[1-9]\d{1,14}$/|unique:contacts,phone_number,' . $contact->id,
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'company' => 'nullable|string|max:255',
            'website' => 'nullable|url|max:500',
            'facebook_url' => 'nullable|url|max:500',
            'instagram_url' => 'nullable|url|max:500',
            'linkedin_url' => 'nullable|url|max:500',
            'twitter_url' => 'nullable|url|max:500',
            'job_title' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'timezone' => 'nullable|string|max:255',
            'language' => 'nullable|string|max:255',
            'custom_fields' => 'nullable|array',
            'status' => 'nullable|in:active,inactive,blocked',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:contact_tags,id',
            'list_ids' => 'nullable|array',
            'list_ids.*' => 'exists:contact_lists,id',
        ]);

        try {
            // Track changes before update
            $changes = array_diff_assoc($validated, $contact->only(array_keys($validated)));
            
            $contact->update($validated);

            // Sync tags
            if (isset($validated['tag_ids'])) {
                $contact->tags()->sync($validated['tag_ids']);
            }

            // Sync lists and update counts
            if (isset($validated['list_ids'])) {
                $oldListIds = $contact->lists()->pluck('contact_list_id')->toArray();
                $contact->lists()->sync($validated['list_ids']);
                
                // Update counts for affected lists
                $affectedListIds = array_unique(array_merge($oldListIds, $validated['list_ids']));
                foreach ($affectedListIds as $listId) {
                    $list = ContactList::find($listId);
                    $list?->updateContactCount();
                }
            }

            // Dispatch CRM event if there were changes
            if (!empty($changes)) {
                event(new ContactUpdatedEvent($contact, $changes));
            }

            return redirect()->route('contacts.show', $contact)
                ->with('success', 'Contact updated successfully.');
                
        } catch (\Illuminate\Database\QueryException $e) {
            // Handle duplicate phone number error gracefully
            if ($e->getCode() === '23000') {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['phone_number' => 'This phone number is already registered in the system.']);
            }
            throw $e;
        }
    }

    /**
     * Remove the specified contact
     */
    public function destroy(Contact $contact): RedirectResponse
    {
        // Authorize
        $this->authorize('delete', $contact);

        $contact->delete();

        return redirect()->route('contacts.index')
            ->with('success', 'Contact deleted successfully.');
    }

    /**
     * Show import page
     */
    /**
     * Display the import form
     */
    public function import(): InertiaResponse
    {
        $this->authorize('create', Contact::class);

        $user = Auth::user();
        $ownerId = $user->isAgent() ? $user->parent_user_id : $user->id;

        $tags = ContactTag::where('user_id', $ownerId)->get();
        $lists = ContactList::where('user_id', $ownerId)->get();

        return Inertia::render('contacts/import', [
            'tags' => $tags,
            'lists' => $lists,
        ]);
    }

    /**
     * Preview CSV file before import
     */
    public function importPreview(Request $request)
    {
        try {
            Log::info('Import preview started');
            
            $this->authorize('create', Contact::class);

            $request->validate([
                'file' => 'required|file|mimes:csv,txt|max:51200', // 50MB
            ]);

            Log::info('Validation passed');

            $file = $request->file('file');
            $handle = fopen($file->getRealPath(), 'r');

            if ($handle === false) {
                Log::error('Failed to open file');
                return response()->json(['error' => 'Failed to read CSV file'], 400);
            }

            // Read header row
            $headers = fgetcsv($handle);
            if ($headers === false || empty($headers)) {
                fclose($handle);
                Log::error('No headers found');
                return response()->json(['error' => 'CSV file is empty or invalid'], 400);
            }

            Log::info('Headers found: ' . count($headers));

            // Clean headers
            $headers = array_map(fn($h) => trim($h), $headers);

            // Read up to 5 sample rows
            $rows = [];
            $totalRows = 0;
            while (($row = fgetcsv($handle)) !== false && count($rows) < 5) {
                $rows[] = $row;
                $totalRows++;
            }

            // Count remaining rows
            while (fgetcsv($handle) !== false) {
                $totalRows++;
            }

            fclose($handle);

            Log::info('Total rows: ' . $totalRows);

            if ($totalRows === 0) {
                return response()->json(['error' => 'CSV file contains no data rows'], 400);
            }

            if ($totalRows > 100000) {
                return response()->json(['error' => 'CSV file contains too many rows. Maximum is 100,000'], 400);
            }

            return response()->json([
                'headers' => $headers,
                'rows' => $rows,
                'total_rows' => $totalRows,
            ]);
        } catch (\Exception $e) {
            Log::error('Import preview error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['error' => 'Failed to preview CSV file: ' . $e->getMessage()], 400);
        }
    }

    /**
     * Process contact import with column mapping (for queue-based import)
     */
    public function importProcess(Request $request)
    {
        Log::info('Import process hit', [
            'has_file' => $request->hasFile('file'),
            'has_mapping' => $request->has('column_mapping'),
        ]);

        $this->authorize('create', Contact::class);

        try {
            $validated = $request->validate([
                'file' => 'required|file|mimes:csv,txt|max:51200', // 50MB
                'column_mapping' => 'required|json',
                'tag_ids' => 'nullable|array',
                'tag_ids.*' => 'exists:contact_tags,id',
                'list_ids' => 'nullable|array',
                'list_ids.*' => 'exists:contact_lists,id',
            ]);

            Log::info('Validation passed');

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', ['errors' => $e->errors()]);
            return response()->json(['error' => $e->errors()], 422);
        }

        try {
            $file = $request->file('file');
            $columnMapping = json_decode($request->input('column_mapping'), true);
            
            Log::info('Starting contact import', [
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'column_mapping' => $columnMapping,
            ]);
            
            // Store CSV file
            $filename = 'imports/' . uniqid() . '_' . $file->getClientOriginalName();
            $storedPath = $file->storeAs('', $filename);

            Log::info('File stored', ['path' => $storedPath]);

            // Count total rows
            $handle = fopen(Storage::path($storedPath), 'r');
            fgetcsv($handle); // Skip header
            $totalRows = 0;
            while (fgetcsv($handle) !== false) {
                $totalRows++;
            }
            fclose($handle);

            Log::info('Rows counted', ['total' => $totalRows]);

            // Create import record
            $contactImport = ContactImport::create([
                'user_id' => Auth::id(),
                'filename' => $storedPath,
                'original_filename' => $file->getClientOriginalName(),
                'status' => ContactImport::STATUS_PENDING,
                'total_rows' => $totalRows,
                'column_mapping' => $columnMapping,
                'options' => [
                    'tags' => $request->input('tag_ids', []),
                    'lists' => $request->input('list_ids', []),
                ],
            ]);

            Log::info('Import record created', ['id' => $contactImport->id]);

            // Dispatch job to queue
            ProcessContactImport::dispatch($contactImport);

            Log::info('Import queued successfully', ['import_id' => $contactImport->id]);

            return response()->json([
                'success' => true,
                'message' => 'Import started successfully! We will notify you when it completes.',
                'import_id' => $contactImport->id,
            ]);

        } catch (\Exception $e) {
            Log::error('Import process error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['error' => 'Failed to start import: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Auto-detect column mapping from CSV headers
     * 
     * @param \Illuminate\Http\UploadedFile $file
     * @return array|null
     */
    private function autoDetectColumnMapping($file): ?array
    {
        $handle = fopen($file->getRealPath(), 'r');
        
        if ($handle === false) {
            return null;
        }

        // Read header row
        $headers = fgetcsv($handle);
        fclose($handle);

        if ($headers === false || empty($headers)) {
            return null;
        }

        // Clean headers
        $headers = array_map(fn($h) => strtolower(trim($h)), $headers);

        // Map of possible header variations to standard field names
        $fieldMappings = [
            'phone_number' => ['phone_number', 'phone', 'mobile', 'cell', 'telephone', 'phonenumber', 'phone number'],
            'first_name' => ['first_name', 'firstname', 'first name', 'fname', 'given_name'],
            'last_name' => ['last_name', 'lastname', 'last name', 'lname', 'surname', 'family_name'],
            'email' => ['email', 'e-mail', 'email address', 'mail'],
            'company' => ['company', 'organization', 'organisation', 'business', 'org'],
            'notes' => ['notes', 'note', 'comments', 'comment', 'description'],
        ];

        $columnMapping = [];
        $foundPhone = false;

        // Try to match each header to a field
        foreach ($headers as $index => $header) {
            foreach ($fieldMappings as $fieldName => $variations) {
                if (in_array($header, $variations)) {
                    $columnMapping[$index] = $fieldName;
                    if ($fieldName === 'phone_number') {
                        $foundPhone = true;
                    }
                    break;
                }
            }
        }

        // Must have at least phone_number mapped
        return $foundPhone ? $columnMapping : null;
    }

    /**
     * Get import status
     */
    public function importStatus($id): JsonResponse
    {
        $contactImport = ContactImport::where('user_id', Auth::id())
            ->findOrFail($id);

        return response()->json([
            'id' => $contactImport->id,
            'status' => $contactImport->status,
            'total_rows' => $contactImport->total_rows,
            'processed_rows' => $contactImport->processed_rows,
            'successful_rows' => $contactImport->successful_imports,
            'failed_rows' => $contactImport->failed_imports,
            'progress_percentage' => $contactImport->total_rows > 0 
                ? round(($contactImport->processed_rows / $contactImport->total_rows) * 100, 2)
                : 0,
            'error_details' => $contactImport->error_log ? ['message' => $contactImport->error_log] : null,
            'completed_at' => $contactImport->completed_at?->toIso8601String(),
        ]);
    }

    /**
     * Export contacts to CSV or download template
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', Contact::class);

        // Check if this is a template download request
        if ($request->input('template') == '1') {
            return $this->downloadTemplate();
        }

        $user = Auth::user();

        // Build query based on role
        $query = Contact::query();

        if ($user->isAdmin()) {
            // Admin can export all contacts
        } elseif ($user->isCustomer()) {
            // Customer can export only their own contacts
            $query->where('user_id', $user->id);
        } elseif ($user->isAgent()) {
            // Agent can export their parent's contacts
            $query->where('user_id', $user->parent_user_id);
        }

        // Apply same filters as index
        if ($search = $request->input('search')) {
            $query->search($search);
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($request->has('opted_out')) {
            $query->optedOut();
        }

        if ($tagId = $request->input('tag_id')) {
            $query->whereHas('tags', fn($q) => $q->where('contact_tag_id', $tagId));
        }

        if ($listId = $request->input('list_id')) {
            $query->whereHas('lists', fn($q) => $q->where('contact_list_id', $listId));
        }

        $contacts = $query->get();

        // Generate CSV
        $csv = $this->generateCSV($contacts);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="contacts-' . date('Y-m-d-His') . '.csv"');
    }

    /**
     * Bulk tag contacts
     */
    public function bulkTag(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'contact_ids' => 'required|array',
            'contact_ids.*' => 'exists:contacts,id',
            'tag_ids' => 'required|array',
            'tag_ids.*' => 'exists:contact_tags,id',
        ]);

        $user = Auth::user();

        // Build query based on role
        $query = Contact::whereIn('id', $validated['contact_ids']);

        if ($user->isAdmin()) {
            // Admin can tag any contacts
        } elseif ($user->isCustomer()) {
            // Customer can only tag their own contacts
            $query->where('user_id', $user->id);
        } elseif ($user->isAgent()) {
            // Agent can only tag their parent's contacts
            $query->where('user_id', $user->parent_user_id);
        }

        $contacts = $query->get();

        // Authorize each contact
        foreach ($contacts as $contact) {
            $this->authorize('update', $contact);
        }

        foreach ($contacts as $contact) {
            $contact->tags()->syncWithoutDetaching($validated['tag_ids']);
        }

        return redirect()->back()
            ->with('success', sprintf('Tagged %d contacts.', $contacts->count()));
    }

    /**
     * Bulk add contacts to list
     */
    public function bulkAddToList(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'contact_ids' => 'required|array',
            'contact_ids.*' => 'exists:contacts,id',
            'list_id' => 'required|exists:contact_lists,id',
        ]);

        $user = Auth::user();

        // Build query based on role
        $query = Contact::whereIn('id', $validated['contact_ids']);

        if ($user->isAdmin()) {
            // Admin can modify any contacts
        } elseif ($user->isCustomer()) {
            // Customer can only modify their own contacts
            $query->where('user_id', $user->id);
        } elseif ($user->isAgent()) {
            // Agent can only modify their parent's contacts
            $query->where('user_id', $user->parent_user_id);
        }

        $contacts = $query->get();

        // Authorize each contact
        foreach ($contacts as $contact) {
            $this->authorize('update', $contact);
        }

        $list = ContactList::findOrFail($validated['list_id']);

        foreach ($contacts as $contact) {
            $list->addContact($contact->id);
        }

        return redirect()->back()
            ->with('success', sprintf('Added %d contacts to list.', $contacts->count()));
    }

    /**
     * Bulk delete contacts
     */
    public function bulkDelete(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'contact_ids' => 'required|array',
            'contact_ids.*' => 'exists:contacts,id',
        ]);

        $user = Auth::user();

        // Build query based on role
        $query = Contact::whereIn('id', $validated['contact_ids']);

        if ($user->isAdmin()) {
            // Admin can delete any contacts
        } elseif ($user->isCustomer()) {
            // Customer can only delete their own contacts
            $query->where('user_id', $user->id);
        } elseif ($user->isAgent()) {
            // Agent can only delete their parent's contacts
            $query->where('user_id', $user->parent_user_id);
        }

        $contacts = $query->get();

        // Authorize each contact
        foreach ($contacts as $contact) {
            $this->authorize('delete', $contact);
        }

        $count = $contacts->count();
        
        // Delete all authorized contacts
        foreach ($contacts as $contact) {
            $contact->delete();
        }

        return redirect()->back()
            ->with('success', sprintf('Deleted %d contacts.', $count));
    }

    /**
     * Generate CSV from contacts
     */
    private function generateCSV($contacts): string
    {
        $output = fopen('php://temp', 'r+');

        // Header
        fputcsv($output, [
            'Phone Number',
            'First Name',
            'Last Name',
            'Email',
            'Company',
            'Job Title',
            'Status',
            'Opted Out',
            'Total Campaigns',
            'Total Calls',
            'Engagement Score',
            'Last Contacted',
            'Created At',
        ]);

        // Rows
        foreach ($contacts as $contact) {
            fputcsv($output, [
                $contact->phone_number,
                $contact->first_name,
                $contact->last_name,
                $contact->email,
                $contact->company,
                $contact->job_title,
                $contact->status,
                $contact->opted_out ? 'Yes' : 'No',
                $contact->total_campaigns,
                $contact->total_calls,
                $contact->engagement_score,
                $contact->last_contacted_at?->toDateTimeString(),
                $contact->created_at->toDateTimeString(),
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    /**
     * Download a sample CSV template for contact import
     */
    private function downloadTemplate()
    {
        $output = fopen('php://temp', 'r+');

        // Header with required and optional fields
        fputcsv($output, [
            'first_name',
            'last_name',
            'phone_number',
            'email',
            'company',
            'notes',
        ]);

        // Sample data rows
        fputcsv($output, [
            'John',
            'Doe',
            '+14155551234',
            'john.doe@example.com',
            'Example Corp',
            'Potential customer',
        ]);

        fputcsv($output, [
            'Jane',
            'Smith',
            '+14155555678',
            'jane.smith@example.com',
            'Tech Solutions',
            'Follow up next week',
        ]);

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="contacts-import-template.csv"');
    }

    /**
     * Validate all phone numbers for the current user using AI
     */
    public function validatePhoneNumbers(
        Request $request,
        PhoneValidationService $phoneValidator
    ): JsonResponse {
        $this->authorize('viewAny', Contact::class);

        $user = Auth::user();

        try {
            // Get all contacts for the user (agents see parent's contacts)
            $ownerId = $user->isAgent() ? $user->parent_user_id : $user->id;
            $contacts = Contact::where('user_id', $ownerId)->get();

            if ($contacts->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No contacts found',
                    'data' => [
                        'total' => 0,
                        'valid' => 0,
                        'invalid' => 0,
                        'invalid_contacts' => [],
                    ],
                ]);
            }

            $validationResults = [];
            $invalidContacts = [];

            // Validate each contact's phone number
            foreach ($contacts as $contact) {
                $result = $phoneValidator->sanitize($contact->phone_number);
                
                if (!$result['success']) {
                    $invalidContacts[] = [
                        'id' => $contact->id,
                        'name' => trim($contact->first_name . ' ' . $contact->last_name) ?: 'Unnamed Contact',
                        'phone_number' => $contact->phone_number,
                        'error' => $result['error'],
                        'company' => $contact->company,
                        'email' => $contact->email,
                    ];
                }

                $validationResults[] = [
                    'contact_id' => $contact->id,
                    'is_valid' => $result['success'],
                    'original' => $contact->phone_number,
                    'formatted' => $result['formatted'] ?? null,
                    'error' => $result['error'] ?? null,
                ];
            }

            $totalContacts = $contacts->count();
            $invalidCount = count($invalidContacts);
            $validCount = $totalContacts - $invalidCount;

            // Generate AI-powered suggestions for invalid numbers
            $aiSuggestions = $this->generatePhoneFixSuggestions($invalidContacts);

            return response()->json([
                'success' => true,
                'message' => "Validated {$totalContacts} contacts. Found {$invalidCount} invalid numbers.",
                'data' => [
                    'total' => $totalContacts,
                    'valid' => $validCount,
                    'invalid' => $invalidCount,
                    'invalid_contacts' => $invalidContacts,
                    'ai_suggestions' => $aiSuggestions,
                    'validation_results' => $validationResults,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error("Phone validation failed: {$e->getMessage()}");

            return response()->json([
                'success' => false,
                'message' => 'Failed to validate phone numbers: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Build AI prompt for phone number validation suggestions
     */
    private function buildAiPromptForPhoneValidation(array $invalidContacts): string
    {
        $contactList = '';
        foreach ($invalidContacts as $contact) {
            $contactList .= "- {$contact['name']}: {$contact['phone_number']} (Error: {$contact['error']})\n";
        }

        return <<<PROMPT
I have the following contacts with invalid phone numbers. Please analyze each number and provide suggestions on how to fix them.

Invalid Contacts:
{$contactList}

For each contact, provide:
1. Why the phone number is invalid
2. A suggested corrected format (if possible to determine)
3. Any additional notes or warnings

Phone numbers should be in E.164 format (e.g., +14155551234 for US numbers, +442071234567 for UK numbers).

Respond in JSON format:
{
  "suggestions": [
    {
      "contact_name": "Name",
      "original_number": "xxx",
      "issue": "description of the issue",
      "suggested_fix": "corrected number or 'Cannot auto-fix'",
      "confidence": "high|medium|low",
      "notes": "additional information"
    }
  ]
}
PROMPT;
    }

    /**
     * Generate AI-powered fix suggestions for invalid phone numbers
     */
    private function generatePhoneFixSuggestions(array $invalidContacts): array
    {
        if (empty($invalidContacts)) {
            return [];
        }

        $suggestions = [];

        foreach ($invalidContacts as $contact) {
            $suggestion = [
                'contact_id' => $contact['id'],
                'contact_name' => $contact['name'],
                'original_number' => $contact['phone_number'],
                'issue' => $contact['error'],
                'suggested_fix' => null,
                'confidence' => 'low',
                'notes' => '',
            ];

            // Analyze the phone number and provide intelligent suggestions
            $phoneNumber = $contact['phone_number'];
            
            // Remove all non-numeric characters for analysis
            $cleaned = preg_replace('/[^0-9]/', '', $phoneNumber);
            
            if (empty($cleaned)) {
                $suggestion['notes'] = 'Number contains no digits. Please enter a valid phone number.';
                $suggestion['suggested_fix'] = 'Manual entry required';
            } elseif (strlen($cleaned) < 7) {
                $suggestion['notes'] = 'Number is too short. Must be at least 7 digits.';
                $suggestion['suggested_fix'] = 'Manual entry required';
            } elseif (strlen($cleaned) > 15) {
                $suggestion['notes'] = 'Number is too long. Maximum 15 digits allowed.';
                $suggestion['suggested_fix'] = 'Manual entry required';
            } elseif (strlen($cleaned) === 10) {
                // Likely US/Canada number without country code
                $suggestion['suggested_fix'] = '+1' . $cleaned;
                $suggestion['confidence'] = 'high';
                $suggestion['notes'] = 'Appears to be a US/Canada number. Added +1 country code.';
            } elseif (strlen($cleaned) === 11 && $cleaned[0] === '1') {
                // US/Canada number with 1 prefix but no +
                $suggestion['suggested_fix'] = '+' . $cleaned;
                $suggestion['confidence'] = 'high';
                $suggestion['notes'] = 'US/Canada number missing + symbol.';
            } elseif (str_starts_with($phoneNumber, '00')) {
                // International format with 00 instead of +
                $suggestion['suggested_fix'] = '+' . substr($cleaned, 2);
                $suggestion['confidence'] = 'medium';
                $suggestion['notes'] = 'Replace 00 prefix with + for E.164 format.';
            } elseif (!str_starts_with($phoneNumber, '+') && strlen($cleaned) >= 7) {
                // Has digits but missing +
                $suggestion['suggested_fix'] = '+1' . $cleaned;
                $suggestion['confidence'] = 'medium';
                $suggestion['notes'] = 'Missing country code. Assumed US (+1). Please verify.';
            } else {
                $suggestion['notes'] = 'Unable to auto-fix. Please manually correct this number.';
                $suggestion['suggested_fix'] = 'Manual entry required';
            }

            $suggestions[] = $suggestion;
        }

        return $suggestions;
    }

    /**
     * Display the calling session page for working through a contact list
     */
    public function callingSession(Request $request): InertiaResponse
    {
        $this->authorize('viewAny', Contact::class);

        $user = Auth::user();
        $listId = $request->input('list_id');

        // Determine the owner user_id based on role
        $ownerId = $user->id;
        if ($user->isAgent()) {
            $ownerId = $user->parent_user_id;
        }

        // Build contacts query
        $query = Contact::where('user_id', $ownerId)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->where('opted_out', false)->orWhereNull('opted_out');
            });

        // Filter by list if provided
        if ($listId) {
            $query->whereHas('lists', fn($q) => $q->where('contact_list_id', $listId));
        }

        $contacts = $query->orderBy('last_contacted_at', 'asc')
            ->orderBy('created_at', 'asc')
            ->get(['id', 'first_name', 'last_name', 'phone_number', 'email', 'company', 'notes', 'last_contacted_at', 'total_calls']);

        // Get recent calls for these contacts to show disposition
        $contactIds = $contacts->pluck('id');
        $recentCalls = \App\Models\Call::whereIn('contact_id', $contactIds)
            ->whereNotNull('disposition')
            ->orderBy('created_at', 'desc')
            ->get(['id', 'contact_id', 'disposition', 'call_notes', 'callback_at', 'created_at'])
            ->groupBy('contact_id');

        // Get available lists for the dropdown
        $lists = ContactList::where('user_id', $ownerId)->get(['id', 'name']);

        // Get the selected list name
        $selectedList = $listId ? ContactList::find($listId) : null;

        return Inertia::render('contacts/calling-session', [
            'contacts' => $contacts,
            'recentCalls' => $recentCalls,
            'lists' => $lists,
            'selectedListId' => $listId ? (int) $listId : null,
            'selectedListName' => $selectedList?->name,
        ]);
    }
}
