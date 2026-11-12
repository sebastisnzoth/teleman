<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DocumentStatus;
use App\Enums\KycStatus;
use App\Http\Controllers\Controller;
use App\Jobs\SendKycApprovalEmail;
use App\Jobs\SendKycRejectionEmail;
use App\Models\User;
use App\Models\UserKycVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class KycReviewController extends Controller
{
    /**
     * Display pending KYC submissions
     * If user_id is provided, create/find KYC record and redirect to show page
     */
    public function index(Request $request): Response|RedirectResponse
    {
        // Check if user is admin
        abort_unless(auth()->user()->isAdmin(), 403, 'Only administrators can review KYC.');

        // Check if admin wants to manually approve a specific user's KYC
        if ($request->has('user_id')) {
            $user = User::findOrFail($request->user_id);

            // Find or create KYC verification record
            $kyc = UserKycVerification::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'status' => KycStatus::PENDING,
                    'submitted_at' => now(),
                ]
            );

            // Redirect to the show page for manual review/approval
            return redirect()->route('admin.kyc.show', $kyc->id);
        }

        $query = UserKycVerification::with('user')
            ->pendingReview();

        // Filters
        if ($request->has('tier')) {
            $query->where('kyc_tier', $request->tier);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $pending = $query->paginate(20);

        return Inertia::render('Admin/Kyc/Index', [
            'pending' => $pending,
            'filters' => [
                'tier' => $request->tier,
                'search' => $request->search,
            ],
            'stats' => [
                'total_pending' => UserKycVerification::pending()->count(),
                'basic_pending' => UserKycVerification::pending()->basicTier()->count(),
                'business_pending' => UserKycVerification::pending()->businessTier()->count(),
            ],
        ]);
    }

    /**
     * Display detailed KYC review page
     */
    public function show(UserKycVerification $kyc): Response
    {
        // Check if user is admin
        abort_unless(auth()->user()->isAdmin(), 403, 'Only administrators can review KYC.');

        $kyc->load('user', 'reviewer');

        return Inertia::render('Admin/Kyc/Show', [
            'kyc' => [
                'id' => $kyc->id,
                'kyc_tier' => $kyc->kyc_tier,
                'status' => $kyc->status,
                // User info
                'user' => [
                    'id' => $kyc->user->id,
                    'name' => $kyc->user->name,
                    'email' => $kyc->user->email,
                    'created_at' => $kyc->user->created_at->toISOString(),
                ],
                // Phone verification (Tier 1)
                'phone_number' => $kyc->phone_number,
                'phone_verified_at' => $kyc->phone_verified_at?->toISOString(),
                // Business information (Tier 2)
                'business_name' => $kyc->business_name,
                'business_registration_number' => $kyc->business_registration_number,
                'business_type' => $kyc->business_type,
                'business_address' => $kyc->getFullBusinessAddress(),
                // Documents
                'id_document_type' => $kyc->id_document_type,
                'id_document_url' => $kyc->id_document_path
                    ? route('admin.kyc.document', ['kyc' => $kyc->id, 'type' => 'id_document'])
                    : null,
                'id_document_status' => $kyc->id_document_status,
                'business_document_url' => $kyc->business_document_path
                    ? route('admin.kyc.document', ['kyc' => $kyc->id, 'type' => 'business_document'])
                    : null,
                'business_document_status' => $kyc->business_document_status,
                'selfie_with_id_url' => $kyc->selfie_with_id_path
                    ? route('admin.kyc.document', ['kyc' => $kyc->id, 'type' => 'selfie_with_id'])
                    : null,
                'selfie_with_id_status' => $kyc->selfie_status,
                // Review info
                'submitted_at' => $kyc->submitted_at?->toISOString(),
                'reviewed_by' => $kyc->reviewer ? [
                    'name' => $kyc->reviewer->name,
                    'email' => $kyc->reviewer->email,
                ] : null,
                'reviewed_at' => $kyc->reviewed_at?->toISOString(),
                'rejection_reason' => $kyc->rejection_reason,
                'admin_notes' => $kyc->admin_notes,
            ],
        ]);
    }

    /**
     * Approve KYC verification
     */
    public function approve(Request $request, UserKycVerification $kyc): RedirectResponse
    {
        // Check if user is admin
        abort_unless(auth()->user()->isAdmin(), 403, 'Only administrators can approve KYC.');

        if (! $kyc->isPending()) {
            return back()->with('error', 'This KYC verification is not pending review.');
        }

        DB::beginTransaction();
        try {
            // Update KYC status to approved. The reviewer and timestamps were
            // never recorded, so the Show page's "reviewed by" was always blank
            // and an approved verification had no expiry.
            $kyc->update([
                'status' => KycStatus::APPROVED->value,
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'approved_at' => now(),
                'expires_at' => now()->addYears(2),
            ]);

            // Send approval email
            SendKycApprovalEmail::dispatch($kyc);

            DB::commit();

            return redirect()->route('admin.kyc.index')
                ->with('success', "KYC verification approved for {$kyc->user->name}.");

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to approve KYC verification. Please try again.');
        }
    }

    /**
     * Approve user's KYC directly (create if not exists)
     */
    public function approveUser(Request $request): RedirectResponse
    {
        // Check if user is admin
        abort_unless(auth()->user()->isAdmin(), 403, 'Only administrators can approve KYC.');

        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $user = User::findOrFail($request->user_id);

        DB::beginTransaction();
        try {
            // Find or create KYC verification record
            $kyc = UserKycVerification::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'status' => KycStatus::PENDING,
                    'submitted_at' => now(),
                ]
            );

            // Approve the KYC - just update status to approved
            $kyc->update([
                'status' => KycStatus::APPROVED->value,
            ]);

            // Send approval email
            SendKycApprovalEmail::dispatch($kyc);

            DB::commit();

            return back()->with('success', "KYC verification approved for {$user->name}.");

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to approve KYC verification. Please try again.');
        }
    }

    /**
     * Reject KYC verification
     */
    public function reject(Request $request, UserKycVerification $kyc): RedirectResponse
    {
        // Check if user is admin
        abort_unless(auth()->user()->isAdmin(), 403, 'Only administrators can reject KYC.');

        if (! $kyc->isPending()) {
            return back()->with('error', 'This KYC verification is not pending review.');
        }

        $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
            'admin_notes' => ['nullable', 'string', 'max:1000'],
            'rejected_documents' => ['nullable', 'array'],
            'rejected_documents.*' => ['string', 'in:id_document,business_document,selfie_with_id'],
        ]);

        DB::beginTransaction();
        try {
            // Mark specific documents as rejected if provided
            if ($request->has('rejected_documents')) {
                foreach ($request->rejected_documents as $docType) {
                    $statusField = $docType.'_status';
                    $kyc->update([
                        $statusField => DocumentStatus::REJECTED->value,
                    ]);
                }
            }

            // Update KYC status to rejected
            $kyc->update([
                'status' => KycStatus::REJECTED->value,
                'rejection_reason' => $request->rejection_reason,
            ]);

            // Send rejection email
            SendKycRejectionEmail::dispatch($kyc);

            DB::commit();

            return redirect()->route('admin.kyc.index')
                ->with('success', "KYC verification rejected for {$kyc->user->name}.");

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to reject KYC verification. Please try again.');
        }
    }

    /**
     * Download/view document
     */
    public function downloadDocument(UserKycVerification $kyc, string $type): mixed
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only administrators can review KYC documents.');

        $path = match ($type) {
            'id_document' => $kyc->id_document_path,
            'business_document' => $kyc->business_document_path,
            'selfie_with_id' => $kyc->selfie_with_id_path,
            default => null,
        };

        if (! $path || ! Storage::disk('private')->exists($path)) {
            abort(404);
        }

        return Storage::disk('private')->download($path);
    }

    /**
     * Update document status individually
     */
    public function updateDocumentStatus(Request $request, UserKycVerification $kyc, string $type): RedirectResponse
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only administrators can review KYC documents.');

        $request->validate([
            'status' => ['required', 'string', 'in:approved,rejected,pending'],
        ]);

        $statusField = match ($type) {
            'id_document' => 'id_document_status',
            'business_document' => 'business_document_status',
            'selfie_with_id' => 'selfie_status',
            default => null,
        };

        if (! $statusField) {
            return back()->with('error', 'Invalid document type.');
        }

        $kyc->update([
            $statusField => $request->status,
        ]);

        return back()->with('success', 'Document status updated.');
    }
}
