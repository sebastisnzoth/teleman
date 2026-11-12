<?php

namespace App\Http\Controllers;

use App\Models\PhoneNumber;
use App\Models\TrunkPhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AgentNumberController extends Controller
{
    /**
     * Get agent's assigned phone numbers (web page)
     */
    public function myNumbers(Request $request)
    {
        $userId = Auth::id();

        // Get Twilio direct numbers assigned to agent
        $twilioNumbers = PhoneNumber::where('user_id', $userId)
            ->where('status', 'assigned')
            ->get()
            ->map(function ($number) {
                return [
                    'id' => $number->id,
                    'number' => $number->number,
                    'formatted_number' => $number->formatted_number,
                    'friendly_name' => $number->friendly_name,
                    'capabilities' => $number->capabilities,
                    'source' => 'twilio_direct',
                    'status' => $number->status,
                ];
            });

        // Get SIP trunk numbers assigned to agent
        $sipNumbers = TrunkPhoneNumber::where('user_id', $userId)
            ->where('status', 'active')
            ->where('assigned_to', 'softphone')
            ->with('trunk')
            ->get()
            ->map(function ($number) {
                return [
                    'id' => $number->id,
                    'number' => $number->phone_number,
                    'formatted_number' => $number->formatted,
                    'friendly_name' => $number->friendly_name,
                    'capabilities' => $number->capabilities,
                    'source' => 'sip_trunk',
                    'trunk_name' => $number->trunk->trunk_name ?? 'Unknown',
                    'status' => $number->status,
                ];
            });

        // Combine both
        $allNumbers = $twilioNumbers->merge($sipNumbers);

        return Inertia::render('Agent/MyNumbers', [
            'numbers' => $allNumbers,
            'totalNumbers' => $allNumbers->count(),
        ]);
    }

    /**
     * API endpoint for softphone (agent's assigned numbers)
     * This endpoint is used by the WebRTC softphone to get available numbers
     */
    public function myNumbersApi(Request $request)
    {
        $userId = Auth::id();

        // Get all phone numbers assigned to this agent from phone_numbers table
        // This includes both twilio_direct and sip_trunk source numbers
        $numbers = PhoneNumber::where('user_id', $userId)
            ->where('status', 'assigned')
            ->get()
            ->map(function ($number) {
                return [
                    'id' => $number->id,
                    'number' => $number->number,
                    'formatted_number' => $number->formatted_number,
                    'friendly_name' => $number->friendly_name,
                    'capabilities' => $number->capabilities,
                    'source' => $number->source ?? 'twilio_direct',
                ];
            });

        return response()->json([
            'numbers' => $numbers,
            'total' => $numbers->count(),
        ]);
    }
}
