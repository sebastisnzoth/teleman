<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define all permissions grouped by module
        $permissions = [
            // Campaign Permissions
            'campaigns' => [
                ['name' => 'View Campaigns', 'slug' => 'campaigns.view', 'description' => 'View campaign list and details'],
                ['name' => 'Create Campaign', 'slug' => 'campaigns.create', 'description' => 'Create new campaigns'],
                ['name' => 'Edit Campaign', 'slug' => 'campaigns.edit', 'description' => 'Edit existing campaigns'],
                ['name' => 'Delete Campaign', 'slug' => 'campaigns.delete', 'description' => 'Delete campaigns'],
                ['name' => 'Execute Campaign', 'slug' => 'campaigns.execute', 'description' => 'Start/stop campaign execution'],
                ['name' => 'View Campaign Analytics', 'slug' => 'campaigns.analytics', 'description' => 'View campaign statistics and reports'],
            ],

            // Contact Permissions
            'contacts' => [
                ['name' => 'View Contacts', 'slug' => 'contacts.view', 'description' => 'View contact list and details'],
                ['name' => 'Create Contact', 'slug' => 'contacts.create', 'description' => 'Add new contacts'],
                ['name' => 'Edit Contact', 'slug' => 'contacts.edit', 'description' => 'Edit existing contacts'],
                ['name' => 'Delete Contact', 'slug' => 'contacts.delete', 'description' => 'Delete contacts'],
                ['name' => 'Import Contacts', 'slug' => 'contacts.import', 'description' => 'Import contacts from CSV'],
                ['name' => 'Export Contacts', 'slug' => 'contacts.export', 'description' => 'Export contacts to CSV'],
                ['name' => 'Manage Contact Lists', 'slug' => 'contacts.lists', 'description' => 'Create and manage contact lists'],
                ['name' => 'Manage Contact Tags', 'slug' => 'contacts.tags', 'description' => 'Create and manage contact tags'],
            ],

            // Call Permissions
            'calls' => [
                ['name' => 'View Calls', 'slug' => 'calls.view', 'description' => 'View call history and details'],
                ['name' => 'Make Calls', 'slug' => 'calls.make', 'description' => 'Make outbound calls via softphone'],
                ['name' => 'View All Calls', 'slug' => 'calls.view_all', 'description' => 'View all users\' call history'],
                ['name' => 'View Own Calls Only', 'slug' => 'calls.view_own', 'description' => 'View only own call history'],
                ['name' => 'Listen to Recordings', 'slug' => 'calls.recordings', 'description' => 'Listen to call recordings'],
                ['name' => 'Download Recordings', 'slug' => 'calls.download', 'description' => 'Download call recordings'],
            ],

            // Analytics Permissions
            'analytics' => [
                ['name' => 'View Analytics Dashboard', 'slug' => 'analytics.view', 'description' => 'View analytics dashboard'],
                ['name' => 'View All Analytics', 'slug' => 'analytics.view_all', 'description' => 'View system-wide analytics'],
                ['name' => 'View Own Analytics', 'slug' => 'analytics.view_own', 'description' => 'View only own analytics'],
                ['name' => 'Export Analytics', 'slug' => 'analytics.export', 'description' => 'Export analytics reports'],
            ],

            // Agent Management Permissions
            'agents' => [
                ['name' => 'View Agents', 'slug' => 'agents.view', 'description' => 'View agent list'],
                ['name' => 'Create Agent', 'slug' => 'agents.create', 'description' => 'Create new agents'],
                ['name' => 'Edit Agent', 'slug' => 'agents.edit', 'description' => 'Edit agent details'],
                ['name' => 'Delete Agent', 'slug' => 'agents.delete', 'description' => 'Delete agents'],
                ['name' => 'Assign Permissions', 'slug' => 'agents.permissions', 'description' => 'Manage agent permissions'],
            ],

            // User Management Permissions (Admin only)
            'users' => [
                ['name' => 'View Users', 'slug' => 'users.view', 'description' => 'View all system users'],
                ['name' => 'Create User', 'slug' => 'users.create', 'description' => 'Create new users'],
                ['name' => 'Edit User', 'slug' => 'users.edit', 'description' => 'Edit user details'],
                ['name' => 'Delete User', 'slug' => 'users.delete', 'description' => 'Delete users'],
                ['name' => 'Manage Roles', 'slug' => 'users.roles', 'description' => 'Assign/remove user roles'],
            ],

            // Settings Permissions
            'settings' => [
                ['name' => 'View Settings', 'slug' => 'settings.view', 'description' => 'View own settings'],
                ['name' => 'Edit Settings', 'slug' => 'settings.edit', 'description' => 'Edit own settings'],
                ['name' => 'Manage Twilio', 'slug' => 'settings.twilio', 'description' => 'Manage Twilio configuration'],
                ['name' => 'View System Settings', 'slug' => 'settings.system', 'description' => 'View system-wide settings (admin only)'],
            ],

            // Billing Permissions (Future use)
            'billing' => [
                ['name' => 'View Billing', 'slug' => 'billing.view', 'description' => 'View billing information'],
                ['name' => 'Manage Billing', 'slug' => 'billing.manage', 'description' => 'Manage billing and subscriptions'],
            ],

            // Phone Number Management Permissions
            'numbers' => [
                ['name' => 'View Phone Numbers', 'slug' => 'numbers.view', 'description' => 'View phone number inventory'],
                ['name' => 'Request Phone Number', 'slug' => 'numbers.request', 'description' => 'Request a phone number'],
                ['name' => 'Approve Number Requests', 'slug' => 'numbers.approve', 'description' => 'Approve/reject number requests'],
                ['name' => 'Release Phone Numbers', 'slug' => 'numbers.release', 'description' => 'Release assigned numbers'],
                ['name' => 'Sync Twilio Numbers', 'slug' => 'numbers.sync', 'description' => 'Sync available numbers from Twilio'],
                ['name' => 'View Number Costs', 'slug' => 'numbers.costs', 'description' => 'View Twilio number costs'],
            ],

            // KYC Permissions
            'kyc' => [
                ['name' => 'View KYC Status', 'slug' => 'kyc.view', 'description' => 'View own KYC verification status'],
                ['name' => 'Submit Basic KYC', 'slug' => 'kyc.submit_basic', 'description' => 'Submit basic KYC (phone verification)'],
                ['name' => 'Submit Business KYC', 'slug' => 'kyc.submit_business', 'description' => 'Submit business KYC documents'],
                ['name' => 'View All KYC Submissions', 'slug' => 'kyc.view_all', 'description' => 'View all KYC submissions (admin)'],
                ['name' => 'Review KYC', 'slug' => 'kyc.review', 'description' => 'Review and approve/reject KYC submissions'],
                ['name' => 'Download KYC Documents', 'slug' => 'kyc.documents', 'description' => 'Download KYC verification documents'],
            ],
        ];

        // Create all permissions
        foreach ($permissions as $module => $modulePermissions) {
            foreach ($modulePermissions as $permissionData) {
                Permission::updateOrCreate(
                    ['slug' => $permissionData['slug']],
                    array_merge($permissionData, ['module' => $module])
                );
            }
        }

        $this->command->info('Permissions seeded successfully!');

        // Assign permissions to roles
        $this->assignPermissionsToRoles();
    }

    /**
     * Assign permissions to roles based on business logic
     */
    private function assignPermissionsToRoles(): void
    {
        $admin = Role::where('slug', Role::ADMIN)->first();
        $customer = Role::where('slug', Role::CUSTOMER)->first();
        $agent = Role::where('slug', Role::AGENT)->first();

        // Admin gets ALL permissions
        $allPermissions = Permission::all()->pluck('id')->toArray();
        $admin->permissions()->sync($allPermissions);

        // Customer permissions
        $customerPermissions = Permission::whereIn('slug', [
            // Campaigns - Full access
            'campaigns.view', 'campaigns.create', 'campaigns.edit', 'campaigns.delete', 'campaigns.execute', 'campaigns.analytics',
            
            // Contacts - Full access
            'contacts.view', 'contacts.create', 'contacts.edit', 'contacts.delete', 'contacts.import', 'contacts.export', 'contacts.lists', 'contacts.tags',
            
            // Calls - Full access to own
            'calls.view', 'calls.make', 'calls.view_all', 'calls.recordings', 'calls.download',
            
            // Analytics - All own data
            'analytics.view', 'analytics.view_all', 'analytics.export',
            
            // Agents - Full management of own agents
            'agents.view', 'agents.create', 'agents.edit', 'agents.delete', 'agents.permissions',
            
            // Settings - Own settings
            'settings.view', 'settings.edit', 'settings.twilio',
            
            // Billing - View and manage own billing
            'billing.view', 'billing.manage',
            
            // Phone Numbers - View and request only
            'numbers.view', 'numbers.request',
            
            // KYC - View and submit own KYC
            'kyc.view', 'kyc.submit_basic', 'kyc.submit_business',
        ])->pluck('id')->toArray();
        
        $customer->permissions()->sync($customerPermissions);

        // Agent permissions (limited) - Updated to allow phone number access
        $agentPermissions = Permission::whereIn('slug', [
            // Campaigns - View and execute only
            'campaigns.view', 'campaigns.execute', 'campaigns.analytics',

            // Contacts - View only
            'contacts.view',

            // Calls - Make and view own only
            'calls.view', 'calls.make', 'calls.view_own', 'calls.recordings',

            // Analytics - View own only
            'analytics.view', 'analytics.view_own',

            // Settings - View own profile only (no edit — agents cannot change Twilio/API settings)
            'settings.view',

            // KYC - View own status only
            'kyc.view',

            // Phone Numbers - View assigned numbers only
            'numbers.view',
        ])->pluck('id')->toArray();
        
        $agent->permissions()->sync($agentPermissions);

        $this->command->info('Permissions assigned to roles successfully!');
    }
}

