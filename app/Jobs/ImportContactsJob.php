<?php

namespace App\Jobs;

use App\Models\Contact;
use App\Models\ContactImport;
use App\Notifications\ImportCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImportContactsJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 3600; // 1 hour timeout for large imports
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ContactImport $contactImport
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Starting import job for ContactImport ID: {$this->contactImport->id}");
            
            $this->contactImport->markAsProcessing();

            $filePath = Storage::path($this->contactImport->filename);
            
            if (!file_exists($filePath)) {
                throw new \Exception("CSV file not found: {$filePath}");
            }

            $columnMapping = $this->contactImport->column_mapping;
            $options = $this->contactImport->options ?? [];
            
            $this->processCSV($filePath, $columnMapping, $options);

            $this->contactImport->markAsCompleted();
            
            // Send notification to user
            $this->contactImport->user->notify(new ImportCompleted($this->contactImport));

            Log::info("Import completed successfully for ContactImport ID: {$this->contactImport->id}");

        } catch (\Exception $e) {
            Log::error("Import failed for ContactImport ID: {$this->contactImport->id}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->contactImport->markAsFailed($e->getMessage());
            
            // Still notify user about the failure
            $this->contactImport->user->notify(new ImportCompleted($this->contactImport));
            
            throw $e; // Re-throw to mark job as failed
        }
    }

    private function processCSV(string $filePath, array $columnMapping, array $options): void
    {
        $file = fopen($filePath, 'r');
        $headers = fgetcsv($file);
        
        $batch = [];
        $batchSize = 500;
        $rowNumber = 0;

        while (($row = fgetcsv($file)) !== false) {
            $rowNumber++;
            
            // Map CSV row to contact data
            $contactData = $this->mapRowToContact($row, $headers, $columnMapping);
            
            if ($contactData) {
                $batch[] = $contactData;
            }

            // Process batch when it reaches the batch size
            if (count($batch) >= $batchSize) {
                $this->processBatch($batch, $options);
                $batch = [];
            }
        }

        // Process remaining contacts
        if (!empty($batch)) {
            $this->processBatch($batch, $options);
        }

        fclose($file);
    }

    private function mapRowToContact(array $row, array $headers, array $columnMapping): ?array
    {
        $contactData = [];

        foreach ($columnMapping as $csvColumn => $dbColumn) {
            $columnIndex = array_search($csvColumn, $headers);
            
            if ($columnIndex !== false && isset($row[$columnIndex])) {
                $value = trim($row[$columnIndex]);
                
                if (!empty($value)) {
                    $contactData[$dbColumn] = $value;
                }
            }
        }

        // Validate required fields
        if (empty($contactData['phone_number'])) {
            Log::warning("Skipping row - missing phone number", ['data' => $contactData]);
            return null;
        }

        // Clean phone number
        $contactData['phone_number'] = $this->cleanPhoneNumber($contactData['phone_number']);

        return $contactData;
    }

    private function processBatch(array $batch, array $options): void
    {
        $successful = 0;
        $failed = 0;

        foreach ($batch as $contactData) {
            try {
                DB::beginTransaction();

                // Check for existing contact by phone number
                $existingContact = Contact::where('phone_number', $contactData['phone_number'])->first();

                if ($existingContact) {
                    Log::info("Duplicate phone number skipped", ['phone' => $contactData['phone_number']]);
                    $failed++;
                    DB::rollBack();
                    continue;
                }

                // Create contact
                $contact = Contact::create([
                    'user_id' => $this->contactImport->user_id,
                    'first_name' => $contactData['first_name'] ?? null,
                    'last_name' => $contactData['last_name'] ?? null,
                    'phone_number' => $contactData['phone_number'],
                    'email' => $contactData['email'] ?? null,
                    'company' => $contactData['company'] ?? null,
                    'website' => $contactData['website'] ?? null,
                    'notes' => $contactData['notes'] ?? null,
                ]);

                // Attach tags if provided
                if (!empty($options['tags'])) {
                    $contact->tags()->sync($options['tags']);
                }

                // Attach to lists if provided
                if (!empty($options['lists'])) {
                    $contact->lists()->sync($options['lists']);
                }

                $successful++;
                DB::commit();

            } catch (\Exception $e) {
                DB::rollBack();
                $failed++;
                Log::error("Failed to import contact", [
                    'data' => $contactData,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Update progress
        $this->contactImport->incrementProcessed($successful, $failed);
    }

    private function cleanPhoneNumber(string $phone): string
    {
        // Remove all non-numeric characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Add + prefix if not present
        if (!str_starts_with($phone, '+')) {
            if (strlen($phone) === 10) {
                // Bare 10-digit number — assume US/Canada
                $phone = '+1' . $phone;
            } else {
                $phone = '+' . $phone;
            }
        }

        return $phone;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Import job failed permanently for ContactImport ID: {$this->contactImport->id}", [
            'error' => $exception->getMessage()
        ]);

        $this->contactImport->markAsFailed($exception->getMessage());
        $this->contactImport->user->notify(new ImportCompleted($this->contactImport));
    }
}
