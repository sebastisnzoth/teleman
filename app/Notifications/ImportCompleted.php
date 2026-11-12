<?php

namespace App\Notifications;

use App\Models\ContactImport;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ImportCompleted extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public ContactImport $contactImport
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $data = [
            'id' => $this->contactImport->id,
            'status' => $this->contactImport->status,
            'filename' => $this->contactImport->original_filename,
            'total_rows' => $this->contactImport->total_rows,
            'successful_imports' => $this->contactImport->successful_imports,
            'failed_imports' => $this->contactImport->failed_imports,
        ];

        if ($this->contactImport->isCompleted()) {
            $data['title'] = 'Import Completed Successfully';
            $data['message'] = "Successfully imported {$this->contactImport->successful_imports} of {$this->contactImport->total_rows} contacts.";
            $data['type'] = 'success';
        } else {
            $data['title'] = 'Import Failed';
            $data['message'] = $this->contactImport->error_log ?? 'An error occurred during import.';
            $data['type'] = 'error';
        }

        return $data;
    }
}
