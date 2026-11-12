<?php

declare(strict_types=1);

namespace App\Actions\AudioFiles;

use App\Models\AudioFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class UploadAudioFileAction
{
    /**
     * Upload and store audio file
     *
     * @param User $user
     * @param UploadedFile $file
     * @param string|null $description
     * @return AudioFile
     * @throws ValidationException
     */
    public function execute(User $user, UploadedFile $file, ?string $description = null): AudioFile
    {
        // Validate file type
        $allowedMimeTypes = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/x-wav', 'audio/wave'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes, true)) {
            throw ValidationException::withMessages([
                'file' => 'The file must be an MP3 or WAV audio file.',
            ]);
        }

        // Validate file size (max 10MB)
        if ($file->getSize() > 10 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'file' => 'The audio file must not exceed 10MB.',
            ]);
        }

        DB::beginTransaction();

        try {
            // Generate unique filename
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = pathinfo($originalName, PATHINFO_FILENAME);
            $uniqueFilename = $filename . '_' . time() . '.' . $extension;

            // Store file in private storage
            $path = $file->storeAs('audio-files', $uniqueFilename, 'private');

            if ($path === false) {
                throw new \RuntimeException('Failed to store audio file.');
            }

            // Get file duration if possible (using getID3 would be better, but using file size as fallback)
            $duration = $this->estimateDuration($file);

            // Create database record
            $audioFile = AudioFile::create([
                'user_id' => $user->id,
                'filename' => $originalName,
                'file_path' => $path,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType() ?? 'audio/mpeg',
                'duration' => $duration,
                'description' => $description,
            ]);

            DB::commit();

            return $audioFile;
        } catch (\Exception $e) {
            DB::rollBack();

            // Delete uploaded file if database insert fails
            if (isset($path)) {
                Storage::disk('private')->delete($path);
            }

            throw $e;
        }
    }

    /**
     * Estimate audio duration based on file size and bitrate
     * This is a rough estimation. For production, use getID3 library.
     *
     * @param UploadedFile $file
     * @return int Duration in seconds
     */
    private function estimateDuration(UploadedFile $file): int
    {
        $fileSize = $file->getSize();
        $mimeType = $file->getMimeType();

        // Rough estimation based on average bitrates
        // MP3: ~128 kbps average, WAV: ~1411 kbps (16-bit stereo 44.1kHz)
        $bitrate = match ($mimeType) {
            'audio/wav', 'audio/x-wav', 'audio/wave' => 1411 * 1000 / 8, // bytes per second
            default => 128 * 1000 / 8, // MP3 default
        };

        $duration = (int) ($fileSize / $bitrate);

        return max(1, $duration); // Minimum 1 second
    }
}
