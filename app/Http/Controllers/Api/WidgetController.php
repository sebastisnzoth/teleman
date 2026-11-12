<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WidgetController extends Controller
{
    /**
     * Validate API key and domain
     */
    public function validate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'api_key' => 'required|string',
            'domain' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid request parameters',
            ], 400);
        }

        $apiKey = $request->input('api_key');
        $domain = $request->input('domain');

        // Validate API key format (pk_live_ or pk_test_)
        if (!preg_match('/^pk_(live|test)_[a-zA-Z0-9]{32}$/', $apiKey)) {
            return response()->json([
                'valid' => false,
                'message' => 'Invalid API key format',
            ], 401);
        }

        // TODO: Check database for API key
        // For now, we'll implement basic validation
        // In production, you'd check against ApiKey model
        
        // Check if API key exists in database
        $keyRecord = ApiKey::where('key', $apiKey)
            ->where('is_active', true)
            ->first();

        if (!$keyRecord) {
            Log::warning('Invalid API key attempted', [
                'api_key' => substr($apiKey, 0, 10) . '...',
                'domain' => $domain,
            ]);

            return response()->json([
                'valid' => false,
                'message' => 'Invalid API key',
            ], 401);
        }

        // Check domain whitelist
        $allowedDomains = $keyRecord->allowed_domains ?? [];
        
        if (!empty($allowedDomains) && !in_array($domain, $allowedDomains)) {
            Log::warning('Domain not whitelisted for API key', [
                'api_key' => substr($apiKey, 0, 10) . '...',
                'domain' => $domain,
                'allowed_domains' => $allowedDomains,
            ]);

            return response()->json([
                'valid' => false,
                'message' => 'Domain not authorized',
            ], 403);
        }

        // Log successful validation
        Log::info('API key validated successfully', [
            'api_key' => substr($apiKey, 0, 10) . '...',
            'domain' => $domain,
        ]);

        return response()->json([
            'valid' => true,
            'user_id' => $keyRecord->user_id,
            'permissions' => $keyRecord->permissions ?? ['calls'],
        ]);
    }

    /**
     * Get Twilio token for widget
     */
    public function getToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'api_key' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Invalid request parameters',
            ], 400);
        }

        $apiKey = $request->input('api_key');

        // Validate API key
        $keyRecord = ApiKey::where('key', $apiKey)
            ->where('is_active', true)
            ->first();

        if (!$keyRecord) {
            return response()->json([
                'error' => 'Invalid API key',
            ], 401);
        }

        // Generate Twilio token for this user
        try {
            $token = app(\App\Services\TwilioService::class)->generateToken($keyRecord->user_id);

            return response()->json([
                'token' => $token,
                'expires_in' => 3600, // 1 hour
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate Twilio token for widget', [
                'error' => $e->getMessage(),
                'user_id' => $keyRecord->user_id,
            ]);

            return response()->json([
                'error' => 'Failed to generate token',
            ], 500);
        }
    }

    /**
     * Get user's phone numbers for widget
     */
    public function getPhoneNumbers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'api_key' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Invalid request parameters',
            ], 400);
        }

        $apiKey = $request->input('api_key');

        // Validate API key
        $keyRecord = ApiKey::where('key', $apiKey)
            ->where('is_active', true)
            ->first();

        if (!$keyRecord) {
            return response()->json([
                'error' => 'Invalid API key',
            ], 401);
        }

        // Get user's phone numbers
        try {
            $user = \App\Models\User::findOrFail($keyRecord->user_id);
            
            $phoneNumbers = $user->phoneNumbers()
                ->where('is_active', true)
                ->get()
                ->map(function ($phone) {
                    return [
                        'id' => $phone->id,
                        'number' => $phone->number,
                        'formatted_number' => $phone->formatted_number,
                        'friendly_name' => $phone->friendly_name,
                        'capabilities' => $phone->capabilities,
                    ];
                });

            return response()->json([
                'phone_numbers' => $phoneNumbers,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get phone numbers for widget', [
                'error' => $e->getMessage(),
                'user_id' => $keyRecord->user_id,
            ]);

            return response()->json([
                'error' => 'Failed to get phone numbers',
            ], 500);
        }
    }

    /**
     * Initiate call from widget
     */
    public function initiateCall(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'api_key' => 'required|string',
            'to_number' => 'required|string',
            'from_number' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Invalid request parameters',
            ], 400);
        }

        $apiKey = $request->input('api_key');

        // Validate API key
        $keyRecord = ApiKey::where('key', $apiKey)
            ->where('is_active', true)
            ->first();

        if (!$keyRecord) {
            return response()->json([
                'error' => 'Invalid API key',
            ], 401);
        }

        // Create call record
        try {
            $call = \App\Models\Call::create([
                'user_id' => $keyRecord->user_id,
                'to_number' => $request->input('to_number'),
                'from_number' => $request->input('from_number'),
                'direction' => 'outbound',
                'status' => 'initiated',
                'source' => 'widget',
            ]);

            return response()->json([
                'call' => $call,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create call record for widget', [
                'error' => $e->getMessage(),
                'user_id' => $keyRecord->user_id,
            ]);

            return response()->json([
                'error' => 'Failed to initiate call',
            ], 500);
        }
    }

    /**
     * End call from widget
     */
    public function endCall(Request $request, $callId)
    {
        $validator = Validator::make($request->all(), [
            'api_key' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Invalid request parameters',
            ], 400);
        }

        $apiKey = $request->input('api_key');

        // Validate API key
        $keyRecord = ApiKey::where('key', $apiKey)
            ->where('is_active', true)
            ->first();

        if (!$keyRecord) {
            return response()->json([
                'error' => 'Invalid API key',
            ], 401);
        }

        // Find and update call
        try {
            $call = \App\Models\Call::where('id', $callId)
                ->where('user_id', $keyRecord->user_id)
                ->firstOrFail();

            $call->update([
                'status' => 'completed',
                'ended_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'call' => $call,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to end call for widget', [
                'error' => $e->getMessage(),
                'call_id' => $callId,
            ]);

            return response()->json([
                'error' => 'Failed to end call',
            ], 500);
        }
    }
}
