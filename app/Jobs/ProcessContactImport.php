<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Contact;
use App\Models\ContactImport;
use App\Models\ContactList;
use App\Models\ContactTag;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessContactImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hour timeout
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ContactImport $contactImport
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Starting import job for ContactImport ID: {$this->contactImport->id}");

            // Update status to processing
            $this->contactImport->update(['status' => ContactImport::STATUS_PROCESSING]);

            // Get file path
            $filePath = Storage::path($this->contactImport->filename);
            
            if (!file_exists($filePath)) {
                throw new \Exception("Import file not found: {$filePath}");
            }

            // Process CSV
            $this->processCSV($filePath);

            // Mark as completed
            $this->contactImport->update([
                'status' => ContactImport::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            Log::info("Completed import job for ContactImport ID: {$this->contactImport->id}");

        } catch (\Exception $e) {
            Log::error("Import job failed for ContactImport ID: {$this->contactImport->id}");
            Log::error("Error: " . $e->getMessage());
            Log::error($e->getTraceAsString());

            $this->contactImport->update([
                'status' => ContactImport::STATUS_FAILED,
                'error_details' => [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ],
            ]);

            throw $e;
        }
    }

    /**
     * Process the CSV file
     */
    private function processCSV(string $filePath): void
    {
        $handle = fopen($filePath, 'r');
        
        if ($handle === false) {
            throw new \Exception('Failed to open CSV file');
        }

        // Skip header row
        fgetcsv($handle);

        $columnMapping = $this->contactImport->column_mapping;
        $options = $this->contactImport->options ?? [];
        $tagIds = $options['tags'] ?? [];
        $listIds = $options['lists'] ?? [];

        $processedRows = 0;
        $successfulRows = 0;
        $failedRows = 0;
        $errors = [];
        $batch = [];
        $batchSize = 500;

        while (($row = fgetcsv($handle)) !== false) {
            try {
                // Map row data to contact fields
                $contactData = $this->mapRowToContact($row, $columnMapping);
                
                if ($contactData) {
                    $batch[] = $contactData;
                }

                // Process batch when it reaches batch size
                if (count($batch) >= $batchSize) {
                    $result = $this->processBatch($batch, $tagIds, $listIds);
                    $successfulRows += $result['success'];
                    $failedRows += $result['failed'];
                    if (!empty($result['errors'])) {
                        $errors = array_merge($errors, $result['errors']);
                    }
                    $batch = [];
                }

                $processedRows++;

                // Update progress every 100 rows
                if ($processedRows % 100 === 0) {
                    $this->contactImport->update([
                        'processed_rows' => $processedRows,
                        'successful_imports' => $successfulRows,
                        'failed_imports' => $failedRows,
                    ]);
                }

            } catch (\Exception $e) {
                $failedRows++;
                $errors[] = [
                    'row' => $processedRows + 1,
                    'error' => $e->getMessage(),
                ];
                Log::warning("Failed to process row " . ($processedRows + 1) . ": " . $e->getMessage());
            }
        }

        // Process remaining batch
        if (!empty($batch)) {
            $result = $this->processBatch($batch, $tagIds, $listIds);
            $successfulRows += $result['success'];
            $failedRows += $result['failed'];
            if (!empty($result['errors'])) {
                $errors = array_merge($errors, $result['errors']);
            }
        }

        fclose($handle);

        // Final update
        $this->contactImport->update([
            'processed_rows' => $processedRows,
            'successful_imports' => $successfulRows,
            'failed_imports' => $failedRows,
            'error_log' => count($errors) > 0 ? json_encode(array_slice($errors, 0, 100)) : null,
        ]);
    }

    /**
     * Map CSV row to contact data
     */
    private function mapRowToContact(array $row, array $columnMapping): ?array
    {
        $data = [];

        foreach ($columnMapping as $csvColumn => $dbField) {
            if ($dbField && $dbField !== 'skip' && isset($row[$csvColumn])) {
                $value = trim($row[$csvColumn]);
                
                if ($value !== '') {
                    $data[$dbField] = $value;
                }
            }
        }

        // Phone number is required
        if (empty($data['phone_number'])) {
            return null;
        }

        // Clean and normalize phone number
        $data['phone_number'] = $this->cleanPhoneNumber($data['phone_number']);

        // Add user_id
        $data['user_id'] = $this->contactImport->user_id;

        return $data;
    }

    /**
     * Process a batch of contacts
     */
    private function processBatch(array $batch, array $tagIds, array $listIds): array
    {
        $successCount = 0;
        $failedCount = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($batch as $contactData) {
                try {
                    // Check for duplicates by phone number
                    $existingContact = Contact::where('user_id', $contactData['user_id'])
                        ->where('phone_number', $contactData['phone_number'])
                        ->first();

                    if ($existingContact) {
                        // Update existing contact
                        $existingContact->update($contactData);
                        $contact = $existingContact;
                    } else {
                        // Create new contact
                        $contact = Contact::create($contactData);
                    }

                    // Attach tags
                    if (!empty($tagIds)) {
                        $contact->tags()->syncWithoutDetaching($tagIds);
                    }

                    // Attach lists
                    if (!empty($listIds)) {
                        $contact->lists()->syncWithoutDetaching($listIds);
                    }

                    $successCount++;

                } catch (\Exception $e) {
                    $failedCount++;
                    $errors[] = [
                        'phone' => $contactData['phone_number'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ];
                }
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'success' => $successCount,
            'failed' => $failedCount,
            'errors' => $errors,
        ];
    }

    /**
     * Clean and normalize phone number
     */
    private function cleanPhoneNumber(string $phone): string
    {
        // Remove all non-digit characters except +
        $cleaned = preg_replace('/[^0-9+]/', '', $phone);

        // Add + prefix if not present
        if (!str_starts_with($cleaned, '+')) {
            if (strlen($cleaned) === 10) {
                // Bare 10-digit number — assume US/Canada
                $cleaned = '+1' . $cleaned;
            } else {
                $cleaned = '+' . $cleaned;
            }
        }

        return $cleaned;
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Import job permanently failed for ContactImport ID: {$this->contactImport->id}");
        Log::error("Error: " . $exception->getMessage());

        $this->contactImport->update([
            'status' => ContactImport::STATUS_FAILED,
            'error_log' => $exception->getMessage(),
        ]);
    }
}
