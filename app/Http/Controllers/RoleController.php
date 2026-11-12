<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    /**
     * Display a listing of roles.
     */
    public function index(): Response
    {
        $roles = Role::with('permissions')->get();

        return Inertia::render('roles/index', [
            'roles' => $roles,
        ]);
    }

    /**
     * Display a listing of permissions grouped by module.
     */
    public function permissions(): Response
    {
        $permissions = Permission::all()->groupBy('module');

        return Inertia::render('roles/Permissions', [
            'permissions' => $permissions,
        ]);
    }

    /**
     * Get role details with permissions.
     */
    public function show(Role $role): Response
    {
        $role->load('permissions');

        // Get users count for this role
        $usersCount = $role->users()->count();

        return Inertia::render('roles/Show', [
            'role' => $role,
            'usersCount' => $usersCount,
        ]);
    }

    /**
     * Update role permissions (Admin only).
     */
    public function updatePermissions(Request $request, Role $role)
    {
        // Only admins can modify role permissions
        abort_unless($request->user()->isAdmin(), 403);

        // System roles can't be modified
        if ($role->is_system) {
            return back()->with('error', 'System roles cannot be modified.');
        }

        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['exists:permissions,id'],
        ]);

        $permissions = Permission::whereIn('id', $validated['permissions'])->get();
        $role->syncPermissions($permissions->pluck('slug')->toArray());

        return back()->with('success', 'Role permissions updated successfully.');
    }

    /**
     * Get permissions available for a specific role level.
     */
    public function availablePermissions(Role $role)
    {
        // Get permissions appropriate for this role level
        $allPermissions = Permission::all()->groupBy('module');

        return response()->json([
            'permissions' => $allPermissions,
            'currentPermissions' => $role->permissions->pluck('id'),
        ]);
    }
}
