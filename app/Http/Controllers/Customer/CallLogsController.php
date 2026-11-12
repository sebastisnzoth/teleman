<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Call;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CallLogsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        
        $query = Call::where('user_id', $user->id)
            ->select('id', 'call_type', 'direction', 'to_number', 'from_number', 'status', 'duration_seconds', 'price', 'price_unit', 'recording_url', 'recording_duration', 'started_at', 'ended_at', 'created_at', 'campaign_id', 'sentiment', 'sentiment_confidence', 'lead_score', 'lead_quality', 'ai_summary', 'key_intents', 'sentiment_analyzed_at')
            ->with(['campaign:id,name'])
            ->latest();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by call type
        if ($request->filled('type')) {
            $query->where('call_type', $request->type);
        }

        // Filter by sentiment
        if ($request->filled('sentiment')) {
            $query->where('sentiment', $request->sentiment);
        }

        // Filter by lead quality
        if ($request->filled('lead_quality')) {
            $query->where('lead_quality', $request->lead_quality);
        }

        // Search
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('to_number', 'like', "%{$request->search}%")
                    ->orWhere('from_number', 'like', "%{$request->search}%")
                    ->orWhere('twilio_call_sid', 'like', "%{$request->search}%");
            });
        }

        $calls = $query->paginate(50)->withQueryString();

        return Inertia::render('Customer/CallLogs', [
            'calls' => [
                'data' => $calls->items(),
                'links' => $calls->linkCollection()->toArray(),
                'meta' => [
                    'current_page' => $calls->currentPage(),
                    'from' => $calls->firstItem(),
                    'last_page' => $calls->lastPage(),
                    'path' => $calls->path(),
                    'per_page' => $calls->perPage(),
                    'to' => $calls->lastItem(),
                    'total' => $calls->total(),
                ],
            ],
            'filters' => $request->only(['status', 'type', 'search', 'sentiment', 'lead_quality']),
        ]);
    }
}
