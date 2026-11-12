<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\AudioFiles\DeleteAudioFileAction;
use App\Actions\AudioFiles\UploadAudioFileAction;
use App\Models\AudioFile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AudioFileController extends Controller
{
    /**
     * Display a listing of audio files
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $ownerId = $user->isAgent() ? $user->parent_user_id : $user->id;
        $audioFiles = AudioFile::query()
            ->where('user_id', $ownerId)
            ->latest()
            ->paginate(15);

        return Inertia::render('AudioFiles/Index', [
            'audioFiles' => $audioFiles,
        ]);
    }

    /**
     * Store a newly uploaded audio file
     */
    public function store(
        Request $request,
        UploadAudioFileAction $action
    ): RedirectResponse {
        $request->validate([
            'file' => 'required|file|mimes:mp3,wav|max:10240', // 10MB
            'description' => 'nullable|string|max:500',
        ]);

        try {
            $action->execute(
                $request->user(),
                $request->file('file'),
                $request->input('description')
            );

            return back()->with('success', 'Audio file uploaded successfully');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Delete an audio file
     */
    public function destroy(
        Request $request,
        AudioFile $audioFile,
        DeleteAudioFileAction $action
    ): RedirectResponse {
        // Ensure user owns the audio file (agents can access parent's files)
        $user = $request->user();
        $ownerId = $user->isAgent() ? $user->parent_user_id : $user->id;
        if ($audioFile->user_id !== $ownerId) {
            abort(403);
        }

        // Check if audio file is in use by any campaigns
        if ($audioFile->campaigns()->exists()) {
            return back()->withErrors([
                'error' => 'Cannot delete audio file that is being used by campaigns.',
            ]);
        }

        try {
            $action->execute($audioFile);

            return back()->with('success', 'Audio file deleted successfully');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Stream audio file for playback
     */
    public function stream(Request $request, AudioFile $audioFile): BinaryFileResponse
    {
        // Ensure user owns the audio file (agents can access parent's files)
        $user = $request->user();
        $ownerId = $user->isAgent() ? $user->parent_user_id : $user->id;
        if ($audioFile->user_id !== $ownerId) {
            abort(403);
        }

        // Check if file exists
        if (!Storage::disk('private')->exists($audioFile->file_path)) {
            abort(404);
        }

        $path = Storage::disk('private')->path($audioFile->file_path);

        return response()->file($path, [
            'Content-Type' => $audioFile->mime_type,
            'Accept-Ranges' => 'bytes',
        ]);
    }
}
