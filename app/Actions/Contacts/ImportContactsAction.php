<?php

declare(strict_types=1);

namespace App\Actions\Contacts;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ImportContactsAction
{
    /**
     * Import contacts from CSV file with custom column mapping and chunk processing
     *
     * @param User $user
     * @param UploadedFile $file
     * @param array $options ['column_mapping' => [], 'tag_ids' => [], 'list_ids' => [], 'skip_duplicates' => true]
     * @return array{imported: int, updated: int, skipped: int, failed: int, errors: array}
     * @throws ValidationException
     */
    public function execute(User $user, UploadedFile $file, array $options = []): array
    {
        // Validate file
        $this->validateFile($file);

        // Parse CSV with column mapping
        $contacts = $this->parseCSV($file, $options['column_mapping'] ?? []);

        if (empty($contacts)) {
            throw ValidationException::withMessages([
                'file' => 'The CSV file is empty or invalid.',
            ]);
        }

        // Import contacts in chunks for better performance
        return $this->importContacts($user, $contacts, $options);
    }

    /**
     * Validate uploaded file
     */
    private function validateFile(UploadedFile $file): void
    {
        if (!in_array($file->getClientOriginalExtension(), ['csv', 'txt'])) {
            throw ValidationException::withMessages([
                'file' => 'The file must be a CSV file.',
            ]);
        }

        if ($file->getSize() > 50 * 1024 * 1024) { // 50MB limit
            throw ValidationException::withMessages([
                'file' => 'The file size must not exceed 50MB.',
            ]);
        }
    }

    /**
     * Parse CSV file into array of contacts using column mapping
     *
     * @param UploadedFile $file
     * @param array $columnMapping Column index to field name mapping
     * @return array<int, array<string, string>>
     */
    private function parseCSV(UploadedFile $file, array $columnMapping): array
    {
        $contacts = [];
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return [];
        }

        // Read and skip header row
        fgetcsv($handle);

        // Validate that phone_number is mapped
        $hasPhoneMapping = in_array('phone_number', $columnMapping);
        if (!$hasPhoneMapping) {
            fclose($handle);
            throw ValidationException::withMessages([
                'file' => 'You must map at least one column to Phone Number.',
            ]);
        }

        // Read data rows
        $rowNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            $contact = [];
            foreach ($columnMapping as $columnIndex => $fieldName) {
                if (empty($fieldName) || $fieldName === 'skip') continue; // Skip unmapped columns
                
                $value = $row[$columnIndex] ?? '';
                if ($value !== '') {
                    $contact[$fieldName] = trim($value);
                }
            }

            // Skip rows without phone number
            if (empty($contact['phone_number'])) {
                continue;
            }

            // Add row number for error reporting
            $contact['_row'] = $rowNumber;
            $contacts[] = $contact;

            // Limit to 100,000 contacts per upload
            if (count($contacts) >= 100000) {
                break;
            }
        }

        fclose($handle);
        return $contacts;
    }

    /**
     * Import contacts into database with chunk processing
     *
     * @param User $user
     * @param array<int, array<string, string>> $contacts
     * @param array $options
     * @return array{imported: int, updated: int, skipped: int, failed: int, errors: array}
     */
    private function importContacts(User $user, array $contacts, array $options): array
    {
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        $skipDuplicates = $options['skip_duplicates'] ?? true;
        $tagIds = $options['tag_ids'] ?? [];
        $listIds = $options['list_ids'] ?? [];

        // Process in chunks of 500 for better performance
        $chunks = array_chunk($contacts, 500);

        foreach ($chunks as $chunk) {
            DB::beginTransaction();

            try {
                foreach ($chunk as $contactData) {
                    $rowNumber = $contactData['_row'] ?? 0;
                    unset($contactData['_row']);

                    // Validate contact
                    $validator = Validator::make($contactData, [
                        'phone_number' => 'required|string',
                        'first_name' => 'nullable|string|max:255',
                        'last_name' => 'nullable|string|max:255',
                        'email' => 'nullable|email|max:255',
                        'company' => 'nullable|string|max:255',
                        'notes' => 'nullable|string',
                    ]);

                    if ($validator->fails()) {
                        $failed++;
                        $errors[] = [
                            'row' => $rowNumber,
                            'phone_number' => $contactData['phone_number'] ?? 'N/A',
                            'error' => $validator->errors()->first(),
                        ];
                        continue;
                    }

                    // Clean phone number
                    $phoneNumber = $this->cleanPhoneNumber($contactData['phone_number']);
                    if (!$phoneNumber) {
                        $failed++;
                        $errors[] = [
                            'row' => $rowNumber,
                            'phone_number' => $contactData['phone_number'],
                            'error' => 'Invalid phone number format',
                        ];
                        continue;
                    }

                    // Check for duplicate (global check)
                    $existingContact = Contact::where('phone_number', $phoneNumber)->first();

                    if ($existingContact) {
                        if ($skipDuplicates) {
                            $skipped++;
                            continue;
                        } else {
                            // Only update if it belongs to the same user
                            if ($existingContact->user_id === $user->id) {
                                $existingContact->update($this->prepareContactData($contactData, $phoneNumber));
                                $this->addTagsAndLists($existingContact, $tagIds, $listIds);
                                $updated++;
                            } else {
                                $skipped++;
                            }
                            continue;
                        }
                    }

                    // Create new contact
                    try {
                        $contact = Contact::create([
                            'user_id' => $user->id,
                            'phone_number' => $phoneNumber,
                            'source' => 'csv_import',
                            'status' => 'active',
                            ...$this->prepareContactData($contactData, $phoneNumber),
                        ]);

                        // Add tags and lists
                        $this->addTagsAndLists($contact, $tagIds, $listIds);

                        $imported++;
                    } catch (\Exception $e) {
                        $failed++;
                        $errors[] = [
                            'row' => $rowNumber,
                            'phone_number' => $phoneNumber,
                            'error' => $e->getMessage(),
                        ];
                    }
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'errors' => array_slice($errors, 0, 100), // Return first 100 errors only
        ];
    }

    /**
     * Clean and normalize phone number
     */
    private function cleanPhoneNumber(string $phone): ?string
    {
        // Remove all non-numeric characters except +
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);
        
        if (empty($cleaned)) {
            return null;
        }

        // Ensure it starts with +
        if (!str_starts_with($cleaned, '+')) {
            if (strlen($cleaned) === 10) {
                // Bare 10-digit number — assume US/Canada
                $cleaned = '+1' . $cleaned;
            } elseif (str_starts_with($cleaned, '0')) {
                // Leading 0 — strip it and prepend +1 (US/Canada local format)
                $cleaned = '+1' . ltrim($cleaned, '0');
            } else {
                $cleaned = '+' . $cleaned;
            }
        }

        // Basic validation
        if (strlen($cleaned) < 8 || strlen($cleaned) > 16) {
            return null;
        }

        return $cleaned;
    }

    /**
     * Prepare contact data for database
     */
    private function prepareContactData(array $data, string $phoneNumber): array
    {
        $prepared = [];

        // Don't include phone_number here since we pass it separately
        unset($data['phone_number']);

        $standardFields = [
            'first_name',
            'last_name',
            'email',
            'company',
            'website',
            'notes',
        ];

        foreach ($standardFields as $field) {
            if (isset($data[$field]) && $data[$field] !== '') {
                $prepared[$field] = $data[$field];
            }
        }

        return $prepared;
    }

    /**
     * Add tags and lists to contact
     */
    private function addTagsAndLists(Contact $contact, array $tagIds, array $listIds): void
    {
        if (!empty($tagIds)) {
            $contact->tags()->syncWithoutDetaching($tagIds);
        }

        if (!empty($listIds)) {
            $contact->lists()->syncWithoutDetaching($listIds);
            
            // Update list counts
            foreach ($listIds as $listId) {
                $list = \App\Models\ContactList::find($listId);
                $list?->updateContactCount();
            }
        }
    }
}
