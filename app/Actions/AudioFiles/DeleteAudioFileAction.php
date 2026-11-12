<?php

declare(strict_types=1);

namespace App\Actions\AudioFiles;

use App\Models\AudioFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteAudioFileAction
{
    /**
     * Delete audio file from storage and database
     *
     * @param AudioFile $audioFile
     * @return void
     * @throws \Exception
     */
    public function execute(AudioFile $audioFile): void
    {
        DB::beginTransaction();

        try {
            // Delete file from storage
            if (Storage::disk('private')->exists($audioFile->file_path)) {
                Storage::disk('private')->delete($audioFile->file_path);
            }

            // Delete database record
            $audioFile->delete();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
