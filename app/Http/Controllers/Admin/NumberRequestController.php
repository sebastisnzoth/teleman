<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NumberRequest;
use App\Services\NumberRequestService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NumberRequestController extends Controller
{
    protected $numberRequestService;

    public function __construct(NumberRequestService $numberRequestService)
    {
        $this->numberRequestService = $numberRequestService;
    }

    /**
     * Display pending number requests
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', NumberRequest::class);

        $query = NumberRequest::with(['phoneNumber', 'customer', 'admin']);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            // Default to pending requests
            $query->pending();
        }

        // Search by customer name
        if ($request->filled('search')) {
            $query->whereHas('customer', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%');
            });
        }

        $requests = $query->latest('requested_at')
            ->paginate($request->per_page ?? 15)
            ->withQueryString();

        $statistics = $this->numberRequestService->getStatistics();

        return Inertia::render('Admin/NumberRequests/Index', [
            'requests' => $requests,
            'stats' => $statistics,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    /**
     * Show a single request
     */
    public function show(NumberRequest $numberRequest)
    {
        $this->authorize('view', $numberRequest);

        $numberRequest->load(['phoneNumber', 'customer', 'admin']);

        return Inertia::render('Admin/NumberRequests/Show', [
            'request' => $numberRequest,
        ]);
    }

    /**
     * Approve a number request
     */
    public function approve(NumberRequest $numberRequest, Request $request)
    {
        $this->authorize('process', $numberRequest);

        $request->validate([
            'admin_notes' => 'nullable|string|max:500',
        ]);

        try {
            $this->numberRequestService->approveRequest(
                $numberRequest,
                auth()->user(),
                $request->admin_notes
            );

            return redirect()->back()->with('success', 'Number request approved and assigned to customer.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to approve request: ' . $e->getMessage());
        }
    }

    /**
     * Reject a number request
     */
    public function reject(NumberRequest $numberRequest, Request $request)
    {
        $this->authorize('process', $numberRequest);

        $request->validate([
            'admin_notes' => 'required|string|max:500',
        ]);

        try {
            $this->numberRequestService->rejectRequest(
                $numberRequest,
                auth()->user(),
                $request->admin_notes
            );

            return redirect()->back()->with('success', 'Number request rejected.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to reject request: ' . $e->getMessage());
        }
    }

    /**
     * Get pending count (API endpoint)
     */
    public function pendingCount()
    {
        $this->authorize('viewAny', NumberRequest::class);

        return response()->json([
            'count' => $this->numberRequestService->getPendingCount(),
        ]);
    }
}
