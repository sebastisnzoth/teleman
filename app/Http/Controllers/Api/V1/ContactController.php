<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use League\Csv\Reader;
use League\Csv\Writer;

/**
 * @OA\Tag(
 *     name="Contacts",
 *     description="Contact management endpoints"
 * )
 */
class ContactController extends BaseApiController
{
    /**
     * @OA\Get(
     *     path="/api/v1/contacts",
     *     summary="List all contacts",
     *     tags={"Contacts"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="filter[status]", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="filter[phone_number]", in="query", @OA\Schema(type="string")),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *     @OA\Response(response=200, description="Contacts list")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);

        $contacts = QueryBuilder::for(Contact::class)
            ->allowedFilters([
                'status',
                'phone_number',
                'email',
                'company',
                AllowedFilter::exact('opted_out'),
            ])
            ->allowedSorts(['first_name', 'last_name', 'created_at', 'last_contacted_at'])
            ->where('user_id', $request->user()->id)
            ->when($request->get('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('phone_number', 'like', "%{$search}%");
                });
            })
            ->with(['tags', 'contactLists'])
            ->paginate($perPage);

        return $this->paginatedResponse($contacts, ContactResource::class);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/contacts",
     *     summary="Create a new contact",
     *     tags={"Contacts"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone_number"},
     *             @OA\Property(property="phone_number", type="string", example="+1234567890"),
     *             @OA\Property(property="first_name", type="string", example="John"),
     *             @OA\Property(property="last_name", type="string", example="Doe"),
     *             @OA\Property(property="email", type="string", example="john@example.com"),
     *             @OA\Property(property="company", type="string", example="Acme Inc")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Contact created successfully")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'phone_number' => 'required|string|max:20',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'company' => 'nullable|string|max:255',
            'job_title' => 'nullable|string|max:255',
            'timezone' => 'nullable|string|max:50',
            'language' => 'nullable|string|max:10',
            'notes' => 'nullable|string',
            'custom_fields' => 'nullable|array',
            'status' => 'nullable|in:active,inactive,dnc',
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['status'] = $validated['status'] ?? 'active';

        // Check for duplicate
        $existing = Contact::where('user_id', $request->user()->id)
            ->where('phone_number', $validated['phone_number'])
            ->first();

        if ($existing) {
            return $this->errorResponse('Contact with this phone number already exists', 409);
        }

        $contact = Contact::create($validated);

        return $this->createdResponse(
            new ContactResource($contact),
            'Contact created successfully'
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v1/contacts/{id}",
     *     summary="Get contact details",
     *     tags={"Contacts"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Contact details")
     * )
     */
    public function show(Request $request, Contact $contact): JsonResponse
    {
        if ($contact->user_id !== $request->user()->id && !$request->user()->hasRole('admin')) {
            return $this->forbiddenResponse();
        }

        return $this->successResponse(
            new ContactResource($contact->load(['tags', 'contactLists']))
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v1/contacts/{id}",
     *     summary="Update contact",
     *     tags={"Contacts"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Contact updated")
     * )
     */
    public function update(Request $request, Contact $contact): JsonResponse
    {
        if ($contact->user_id !== $request->user()->id && !$request->user()->hasRole('admin')) {
            return $this->forbiddenResponse();
        }

        $validated = $request->validate([
            'phone_number' => 'sometimes|string|max:20',
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255',
            'company' => 'sometimes|string|max:255',
            'job_title' => 'sometimes|string|max:255',
            'notes' => 'sometimes|string',
            'custom_fields' => 'sometimes|array',
            'status' => 'sometimes|in:active,inactive,dnc',
            'opted_out' => 'sometimes|boolean',
        ]);

        if (isset($validated['opted_out']) && $validated['opted_out']) {
            $validated['opted_out_at'] = now();
        }

        $contact->update($validated);

        return $this->successResponse(
            new ContactResource($contact->fresh()),
            'Contact updated successfully'
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/contacts/{id}",
     *     summary="Delete contact",
     *     tags={"Contacts"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="Contact deleted")
     * )
     */
    public function destroy(Request $request, Contact $contact): JsonResponse
    {
        if ($contact->user_id !== $request->user()->id && !$request->user()->hasRole('admin')) {
            return $this->forbiddenResponse();
        }

        $contact->delete();

        return $this->successResponse(null, 'Contact deleted successfully');
    }

    /**
     * @OA\Post(
     *     path="/api/v1/contacts/bulk",
     *     summary="Bulk create contacts",
     *     tags={"Contacts"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="contacts", type="array", @OA\Items(
     *                 @OA\Property(property="phone_number", type="string"),
     *                 @OA\Property(property="first_name", type="string"),
     *                 @OA\Property(property="last_name", type="string")
     *             ))
     *         )
     *     ),
     *     @OA\Response(response=201, description="Contacts created")
     * )
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'contacts' => 'required|array|min:1|max:1000',
            'contacts.*.phone_number' => 'required|string|max:20',
            'contacts.*.first_name' => 'nullable|string|max:255',
            'contacts.*.last_name' => 'nullable|string|max:255',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.company' => 'nullable|string|max:255',
            'contacts.*.custom_fields' => 'nullable|array',
        ]);

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($validated['contacts'] as $index => $contactData) {
            $contactData['user_id'] = $request->user()->id;
            $contactData['status'] = 'active';

            // Check for duplicate
            $existing = Contact::where('user_id', $request->user()->id)
                ->where('phone_number', $contactData['phone_number'])
                ->exists();

            if ($existing) {
                $skipped++;
                $errors[] = "Row {$index}: Phone number {$contactData['phone_number']} already exists";
                continue;
            }

            try {
                Contact::create($contactData);
                $created++;
            } catch (\Exception $e) {
                $errors[] = "Row {$index}: " . $e->getMessage();
            }
        }

        return $this->successResponse([
            'created' => $created,
            'skipped' => $skipped,
            'total' => count($validated['contacts']),
            'errors' => $errors,
        ], "Bulk import completed: {$created} created, {$skipped} skipped");
    }

    /**
     * @OA\Get(
     *     path="/api/v1/contacts/export/csv",
     *     summary="Export contacts as CSV",
     *     tags={"Contacts"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(response=200, description="CSV file download")
     * )
     */
    public function exportCsv(Request $request)
    {
        $contacts = Contact::where('user_id', $request->user()->id)->get();

        $csv = Writer::createFromString('');
        $csv->insertOne(['Phone Number', 'First Name', 'Last Name', 'Email', 'Company', 'Status']);

        foreach ($contacts as $contact) {
            $csv->insertOne([
                $contact->phone_number,
                $contact->first_name,
                $contact->last_name,
                $contact->email,
                $contact->company,
                $contact->status,
            ]);
        }

        return response($csv->toString(), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="contacts-' . date('Y-m-d') . '.csv"',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/contacts/import/csv",
     *     summary="Import contacts from CSV",
     *     tags={"Contacts"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(property="file", type="string", format="binary")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=200, description="Import completed")
     * )
     */
    public function importCsv(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
        ]);

        $file = $request->file('file');
        $csv = Reader::createFromPath($file->getRealPath(), 'r');
        $csv->setHeaderOffset(0);

        $created = 0;
        $skipped = 0;
        $errors = [];

        foreach ($csv as $index => $record) {
            if (empty($record['phone_number'] ?? $record['Phone Number'] ?? null)) {
                $skipped++;
                continue;
            }

            $phoneNumber = $record['phone_number'] ?? $record['Phone Number'];

            // Check for duplicate
            $existing = Contact::where('user_id', $request->user()->id)
                ->where('phone_number', $phoneNumber)
                ->exists();

            if ($existing) {
                $skipped++;
                continue;
            }

            try {
                Contact::create([
                    'user_id' => $request->user()->id,
                    'phone_number' => $phoneNumber,
                    'first_name' => $record['first_name'] ?? $record['First Name'] ?? null,
                    'last_name' => $record['last_name'] ?? $record['Last Name'] ?? null,
                    'email' => $record['email'] ?? $record['Email'] ?? null,
                    'company' => $record['company'] ?? $record['Company'] ?? null,
                    'status' => 'active',
                ]);
                $created++;
            } catch (\Exception $e) {
                $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
            }
        }

        return $this->successResponse([
            'created' => $created,
            'skipped' => $skipped,
            'errors' => $errors,
        ], "Import completed: {$created} created, {$skipped} skipped");
    }
}
