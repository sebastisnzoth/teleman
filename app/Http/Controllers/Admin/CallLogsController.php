<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Call;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CallLogsController extends Controller
{
    public function index(Request $request)
    {
        $query = Call::select('id', 'user_id', 'campaign_id', 'call_type', 'direction', 'to_number', 'from_number', 'status', 'duration_seconds', 'price', 'price_unit', 'recording_url', 'recording_duration', 'started_at', 'ended_at', 'created_at', 'sentiment', 'sentiment_confidence', 'lead_score', 'lead_quality', 'ai_summary', 'key_intents', 'sentiment_analyzed_at')
            ->with(['user:id,name,email', 'campaign:id,name'])
            ->latest();

        // Filter by customer/user
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

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
        
        // Get all users who have made calls for the filter dropdown
        $users = User::whereHas('calls')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return Inertia::render('Admin/CallLogs', [
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
            'users' => $users,
            'filters' => $request->only(['status', 'type', 'search', 'user_id', 'sentiment', 'lead_quality']),
        ]);
    }
}
