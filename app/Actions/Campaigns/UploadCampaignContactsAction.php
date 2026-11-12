<?php

declare(strict_types=1);

namespace App\Actions\Campaigns;

use App\Models\Campaign;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UploadCampaignContactsAction
{
    /**
     * Upload and import contacts from CSV file
     *
     * @param Campaign $campaign
     * @param UploadedFile $file
     * @return array{imported: int, skipped: int, errors: array}
     * @throws ValidationException
     */
    public function execute(Campaign $campaign, UploadedFile $file): array
    {
        // Validate file
        if ($file->getClientOriginalExtension() !== 'csv') {
            throw ValidationException::withMessages([
                'file' => 'The file must be a CSV file.',
            ]);
        }

        if ($file->getSize() > 5 * 1024 * 1024) { // 5MB limit
            throw ValidationException::withMessages([
                'file' => 'The file size must not exceed 5MB.',
            ]);
        }

        // Parse CSV
        $contacts = $this->parseCSV($file);

        if (empty($contacts)) {
            throw ValidationException::withMessages([
                'file' => 'The CSV file is empty or invalid.',
            ]);
        }

        // Validate and import contacts
        return $this->importContacts($campaign, $contacts);
    }

    /**
     * Parse CSV file into array of contacts
     *
     * @param UploadedFile $file
     * @return array<int, array<string, string>>
     */
    private function parseCSV(UploadedFile $file): array
    {
        $contacts = [];
        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            return [];
        }

        // Read header row
        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return [];
        }

        // Normalize headers (trim and lowercase)
        $header = array_map(fn($h) => strtolower(trim($h)), $header);

        // Validate required columns
        $requiredColumns = ['phone_number'];
        $missingColumns = array_diff($requiredColumns, $header);
        
        if (!empty($missingColumns)) {
            fclose($handle);
            throw ValidationException::withMessages([
                'file' => 'CSV must contain the following columns: ' . implode(', ', $requiredColumns),
            ]);
        }

        // Read data rows
        $rowNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (count($row) !== count($header)) {
                continue; // Skip malformed rows
            }

            $contact = array_combine($header, $row);
            
            if ($contact === false) {
                continue;
            }

            // Add row number for error reporting
            $contact['_row'] = $rowNumber;
            $contacts[] = $contact;

            // Limit to 10,000 contacts per upload
            if (count($contacts) >= 10000) {
                break;
            }
        }

        fclose($handle);
        return $contacts;
    }

    /**
     * Import contacts into database
     *
     * @param Campaign $campaign
     * @param array<int, array<string, string>> $contacts
     * @return array{imported: int, skipped: int, errors: array}
     */
    private function importContacts(Campaign $campaign, array $contacts): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($contacts as $contact) {
                $rowNumber = $contact['_row'] ?? 0;
                unset($contact['_row']);

                // Validate contact
                $validator = Validator::make($contact, [
                    'phone_number' => 'required|string|regex:/^\+?[1-9]\d{1,14}$/',
                    'first_name' => 'nullable|string|max:255',
                    'last_name' => 'nullable|string|max:255',
                    'email' => 'nullable|email|max:255',
                    'company' => 'nullable|string|max:255',
                    'website' => 'nullable|string|max:255',
                ]);

                if ($validator->fails()) {
                    $skipped++;
                    $errors[] = [
                        'row' => $rowNumber,
                        'phone_number' => $contact['phone_number'] ?? 'N/A',
                        'error' => $validator->errors()->first(),
                    ];
                    continue;
                }

                // Format phone number to E.164
                $phoneNumber = $this->formatPhoneNumber($contact['phone_number']);

                // Check for duplicates in this campaign
                $exists = $campaign->campaignContacts()
                    ->where('phone_number', $phoneNumber)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    $errors[] = [
                        'row' => $rowNumber,
                        'phone_number' => $contact['phone_number'],
                        'error' => 'Duplicate phone number in campaign',
                    ];
                    continue;
                }

                // Create contact
                $campaign->campaignContacts()->create([
                    'phone_number' => $phoneNumber,
                    'first_name' => $contact['first_name'] ?? null,
                    'last_name' => $contact['last_name'] ?? null,
                    'email' => $contact['email'] ?? null,
                    'company' => $contact['company'] ?? null,
                    'variables' => array_diff_key($contact, array_flip([
                        'phone_number',
                        'first_name',
                        'last_name',
                        'email',
                        'company',
                        'website',
                    ])),
                    'status' => 'pending',
                ]);

                $imported++;
            }

            // Update campaign total_contacts
            $campaign->update([
                'total_contacts' => $campaign->campaignContacts()->count(),
            ]);

            DB::commit();

            return [
                'imported' => $imported,
                'skipped' => $skipped,
                'errors' => $errors,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Format phone number to E.164 format
     *
     * @param string $phoneNumber
     * @return string
     */
    private function formatPhoneNumber(string $phoneNumber): string
    {
        // Remove all non-numeric characters except +
        $cleaned = preg_replace('/[^0-9+]/', '', $phoneNumber);

        if ($cleaned === null) {
            return $phoneNumber;
        }

        // Add + if not present
        if (!str_starts_with($cleaned, '+')) {
            // Assume US/Canada if no country code
            if (strlen($cleaned) === 10) {
                $cleaned = '+1' . $cleaned;
            } else {
                $cleaned = '+' . $cleaned;
            }
        }

        return $cleaned;
    }
}
