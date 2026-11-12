<?php

namespace App\Http\Controllers;

use App\Models\Sequence;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class SequenceController extends Controller
{
    /**
     * Display a listing of sequences.
     */
    public function index(Request $request)
    {
        $user = auth()->user();
        $ownerId = $user->isAgent() ? $user->parent_user_id : $user->id;

        $query = Sequence::where('user_id', $ownerId)
            ->with(['steps'])
            ->withCount([
                'steps',
                'enrollments',
                'enrollments as active_enrollments_count' => function ($query) {
                    $query->where('status', 'active');
                },
                'enrollments as completed_enrollments_count' => function ($query) {
                    $query->where('status', 'completed');
                },
                'enrollments as converted_enrollments_count' => function ($query) {
                    $query->where('status', 'converted');
                }
            ]);

        // Add search filter
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        // Add status filter
        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        // Add trigger type filter
        if ($request->filled('trigger_type')) {
            $query->where('trigger_type', $request->trigger_type);
        }

        $sequences = $query->orderBy('created_at', 'desc')->paginate(12);

        // Calculate overall stats
        $stats = [
            'total' => Sequence::where('user_id', $ownerId)->count(),
            'enrolled' => DB::table('sequence_enrollments')
                ->join('follow_up_sequences', 'sequence_enrollments.sequence_id', '=', 'follow_up_sequences.id')
                ->where('follow_up_sequences.user_id', $ownerId)
                ->count(),
            'converted' => DB::table('sequence_enrollments')
                ->join('follow_up_sequences', 'sequence_enrollments.sequence_id', '=', 'follow_up_sequences.id')
                ->where('follow_up_sequences.user_id', $ownerId)
                ->where('sequence_enrollments.status', 'converted')
                ->count(),
        ];

        // Calculate average conversion rate
        $totalCompleted = DB::table('sequence_enrollments')
            ->join('follow_up_sequences', 'sequence_enrollments.sequence_id', '=', 'follow_up_sequences.id')
            ->where('follow_up_sequences.user_id', $ownerId)
            ->where('sequence_enrollments.status', 'completed')
            ->count();

        $stats['avg_conversion_rate'] = $totalCompleted > 0 
            ? round(($stats['converted'] / $totalCompleted) * 100, 1) 
            : 0;

        return Inertia::render('sequences/index', [
            'sequences' => $sequences,
            'stats' => $stats,
            'filters' => [
                'search' => $request->search,
                'status' => $request->status,
                'trigger_type' => $request->trigger_type,
            ]
        ]);
    }

    /**
     * Show the form for creating a new sequence.
     */
    public function create()
    {
        return Inertia::render('sequences/create');
    }

    /**
     * Display the specified sequence.
     */
    public function show(Sequence $sequence)
    {
        // Authorize user can view this sequence
        $user = auth()->user();
        $ownerId = $user->isAgent() ? $user->parent_user_id : $user->id;
        if ($sequence->user_id !== $ownerId) {
            abort(403);
        }

        // Load sequence with relationships
        $sequence->load([
            'steps' => function ($query) {
                $query->orderBy('step_number');
            },
            'enrollments.contact',
        ]);

        // Calculate enrollment stats
        $enrollmentStats = [
            'total' => $sequence->enrollments()->count(),
            'active' => $sequence->enrollments()->where('status', 'active')->count(),
            'completed' => $sequence->enrollments()->where('status', 'completed')->count(),
            'paused' => $sequence->enrollments()->where('status', 'paused')->count(),
            'converted' => $sequence->enrollments()->where('status', 'converted')->count(),
        ];

        // Calculate conversion rate
        $completed = $enrollmentStats['completed'];
        $enrollmentStats['conversion_rate'] = $completed > 0 
            ? round(($enrollmentStats['converted'] / $completed) * 100, 1) 
            : 0;

        // Get analytics data for each step
        $stepAnalytics = [];
        foreach ($sequence->steps as $step) {
            $stepAnalytics[$step->id] = [
                'executed' => DB::table('sequence_step_executions')
                    ->where('step_id', $step->id)
                    ->count(),
                'successful' => DB::table('sequence_step_executions')
                    ->where('step_id', $step->id)
                    ->where('status', 'completed')
                    ->count(),
            ];
        }

        // Get recent enrollments for the enrollments tab
        $recentEnrollments = $sequence->enrollments()
            ->with(['contact'])
            ->latest()
            ->take(50)
            ->get();

        // Per-step performance, counted from the executions rather than from
        // the cached counters on the step, which only the engine maintains.
        $stepsPerformance = $sequence->steps->map(function ($step) use ($stepAnalytics) {
            $executed = $stepAnalytics[$step->id]['executed'] ?? 0;
            $successful = $stepAnalytics[$step->id]['successful'] ?? 0;

            return [
                'step_number' => $step->step_number,
                'step_name' => $step->step_name,
                'action_type' => $step->action_type,
                'total_executed' => $executed,
                'total_successful' => $successful,
                'success_rate' => $executed > 0 ? round(($successful / $executed) * 100, 1) : 0.0,
            ];
        })->values()->all();

        // The page formats this as a number of seconds; the model answers in
        // hours. Null until something has actually completed, which the page
        // renders as N/A.
        $averageCompletionHours = $sequence->getAverageCompletionTime();

        // Exactly what the page reads. Every rate is a number because the page
        // calls toFixed() on it, and a decimal cast would hand it a string.
        $analytics = [
            'total_enrolled' => $enrollmentStats['total'],
            'active' => $enrollmentStats['active'],
            'completed' => $enrollmentStats['completed'],
            'converted' => $enrollmentStats['converted'],
            'stopped' => $sequence->enrollments()->where('status', 'stopped')->count(),
            // Converted out of everyone enrolled — the rate the sequence caches
            // for itself, recounted live.
            'conversion_rate' => $enrollmentStats['total'] > 0
                ? round(($enrollmentStats['converted'] / $enrollmentStats['total']) * 100, 1)
                : 0.0,
            'success_rate' => $sequence->getSuccessRate(),
            'avg_completion_time' => $averageCompletionHours === null
                ? null
                : (int) round($averageCompletionHours * 3600),
            'steps_performance' => $stepsPerformance,
        ];

        return Inertia::render('sequences/show', [
            'sequence' => $sequence,
            'enrollments' => $recentEnrollments,
            'analytics' => $analytics,
            'stats' => $enrollmentStats,
        ]);
    }

    /**
     * Show the form for editing the specified sequence.
     */
    public function edit(Sequence $sequence)
    {
        // Authorize user can edit this sequence
        $user = auth()->user();
        $ownerId = $user->isAgent() ? $user->parent_user_id : $user->id;
        if ($sequence->user_id !== $ownerId) {
            abort(403);
        }

        // Load steps for editing
        $sequence->load(['steps' => function ($query) {
            $query->orderBy('step_number');
        }]);

        // Reuse the create page with sequence data
        return Inertia::render('sequences/create', [
            'sequence' => $sequence,
            'isEditing' => true,
        ]);
    }
}
