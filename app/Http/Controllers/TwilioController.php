<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Twilio\DeleteTwilioCredentialsAction;
use App\Actions\Twilio\GenerateTwilioTokenAction;
use App\Actions\Twilio\StoreTwilioCredentialsAction;
use App\Actions\Twilio\VerifyTwilioCredentialsAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwilioController extends Controller
{
    /**
     * Show Twilio configuration page
     */
    public function index(Request $request): Response
    {
        $credential = $request->user()->twilioCredential;

        return Inertia::render('twilio/setup', [
            'credential' => $credential ? [
                'account_sid' => $credential->account_sid,
                'phone_number' => $credential->phone_number,
                'is_active' => $credential->is_active,
                'verified_at' => $credential->verified_at?->toDateTimeString(),
            ] : null,
            'has_credentials' => $request->user()->hasTwilioConfigured(),
        ]);
    }

    /**
     * Verify Twilio credentials
     */
    public function verify(
        Request $request,
        VerifyTwilioCredentialsAction $action
    ): JsonResponse {
        $request->validate([
            'account_sid' => ['required', 'string', 'starts_with:AC'],
            'auth_token' => ['required', 'string', 'min:32'],
        ]);

        $result = $action->execute(
            $request->input('account_sid'),
            $request->input('auth_token')
        );

        if (!$result['valid']) {
            throw ValidationException::withMessages([
                'account_sid' => 'Invalid credentials: ' . ($result['error'] ?? 'Unknown error'),
            ]);
        }

        return response()->json([
            'valid' => true,
            'account_name' => $result['account_name'],
            'phone_number' => $result['phone_number'],
        ]);
    }

    /**
     * Store Twilio credentials
     */
    public function store(
        Request $request,
        StoreTwilioCredentialsAction $action
    ): RedirectResponse {
        $validated = $request->validate([
            'account_sid' => ['required', 'string', 'starts_with:AC'],
            'auth_token' => ['required', 'string', 'min:32'],
            'phone_number' => ['nullable', 'string'],
        ]);

        try {
            $action->execute($request->user(), $validated);

            return redirect()->route('twilio.index')
                ->with('success', 'Twilio credentials saved successfully!');
        } catch (\Exception $e) {
            return back()->withErrors([
                'account_sid' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Delete Twilio credentials
     */
    public function destroy(
        Request $request,
        DeleteTwilioCredentialsAction $action
    ): RedirectResponse {
        $action->execute($request->user());

        return redirect()->route('twilio.index')
            ->with('success', 'Twilio credentials removed successfully!');
    }

    /**
     * Generate access token for WebRTC
     */
    public function token(
        Request $request,
        GenerateTwilioTokenAction $action
    ): JsonResponse {
        try {
            $token = $action->execute($request->user());

            return response()->json([
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
