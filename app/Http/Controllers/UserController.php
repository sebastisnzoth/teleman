<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()->with(['roles', 'parent', 'kycVerification']);

        // Filter by role
        if ($request->filled('role')) {
            $query->whereHas('roles', fn($q) => $q->where('slug', $request->role));
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Admin sees all, Customers see only their agents
        if ($request->user()->isCustomer()) {
            $query->where(function($q) use ($request) {
                $q->where('id', $request->user()->id)
                  ->orWhere('parent_user_id', $request->user()->id);
            });
        }

        // Sort
        $sortField = $request->get('sort_field', 'created_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $users = $query->paginate($request->get('per_page', 15))
            ->withQueryString()
            ->through(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->status,
                    'created_at' => $user->created_at,
                    'roles' => $user->roles,
                    'parent' => $user->parent,
                    'kyc' => $user->kycVerification ? [
                        'id' => $user->kycVerification->id,
                        'status' => $user->kycVerification->status,
                        'kyc_tier' => $user->kycVerification->kyc_tier,
                    ] : null,
                ];
            });

        return Inertia::render('users/index', [
            'users' => $users,
            'filters' => $request->only(['search', 'role', 'status', 'sort_field', 'sort_direction']),
            'roles' => Role::all(['id', 'name', 'slug']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        $this->authorize('create', User::class);

        return Inertia::render('users/create', [
            'roles' => Role::with('permissions')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->authorize('create', User::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role_id' => ['required', 'exists:roles,id'],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
        ]);

        $role = Role::findOrFail($validated['role_id']);

        // Customers can only create agents
        if ($request->user()->isCustomer() && !$role->isAgent()) {
            abort(403, 'Customers can only create agents.');
        }

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'status' => $validated['status'],
            'parent_user_id' => $role->isAgent() ? $request->user()->id : null,
        ]);

        $user->assignRole($role->slug);

        return redirect()->route('users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(User $user): Response
    {
        $this->authorize('view', $user);

        $user->load(['roles.permissions', 'parent', 'agents']);

        // Get user's activity stats
        $stats = [
            'campaigns_count' => $user->campaigns()->count(),
            'contacts_count' => $user->contacts()->count(),
            'calls_count' => $user->calls()->count(),
            'agents_count' => $user->agents()->count(),
        ];

        return Inertia::render('users/show', [
            'user' => $user,
            'stats' => $stats,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(User $user): Response
    {
        $this->authorize('update', $user);

        $user->load('roles');

        return Inertia::render('users/edit', [
            'user' => $user,
            'roles' => Role::with('permissions')->get(),
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::defaults()],
            'role_id' => ['sometimes', 'exists:roles,id'],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
        ]);

        // Update basic info
        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'status' => $validated['status'],
        ]);

        // Update password if provided
        if (!empty($validated['password'])) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        // Update role if provided and authorized
        if (isset($validated['role_id']) && $request->user()->can('assignRole', $user)) {
            $role = Role::findOrFail($validated['role_id']);
            // Pass role ID instead of slug to prevent string insertion into INT column
            $user->syncRoles([$role->id]);
        }

        return redirect()->route('users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        // Cannot delete user with active agents
        if ($user->agents()->count() > 0) {
            return back()->with('error', 'Cannot delete user with active agents. Reassign or delete agents first.');
        }

        $user->delete();

        return redirect()->route('users.index')
            ->with('success', 'User deleted successfully.');
    }

    /**
     * Toggle user status.
     */
    public function toggleStatus(User $user)
    {
        $this->authorize('update', $user);

        $user->update([
            'status' => $user->status === 'active' ? 'inactive' : 'active',
        ]);

        return back()->with('success', 'User status updated successfully.');
    }

    /**
     * Impersonate a user.
     */
    public function impersonate(Request $request, User $user)
    {
        // Only admins can impersonate
        if (!$request->user()->isAdmin()) {
            abort(403, 'Only administrators can impersonate users.');
        }

        // Cannot impersonate yourself
        if ($request->user()->id === $user->id) {
            return back()->with('error', 'You cannot impersonate yourself.');
        }

        // Store the original user ID in session
        session(['impersonator' => $request->user()->id]);
        
        // Log in as the target user
        auth()->login($user);

        return redirect()->route('dashboard')
            ->with('success', "Now impersonating {$user->name}. You can leave impersonation from the user menu.");
    }

    /**
     * Leave impersonation and return to original user.
     */
    public function leaveImpersonate(Request $request)
    {
        $impersonatorId = session('impersonator');
        
        if (!$impersonatorId) {
            return back()->with('error', 'You are not currently impersonating anyone.');
        }

        $originalUser = User::findOrFail($impersonatorId);
        
        // Clear the impersonation session
        session()->forget('impersonator');
        
        // Log back in as the original user
        auth()->login($originalUser);

        return redirect()->route('users.index')
            ->with('success', 'Impersonation ended. Welcome back!');
    }
}
