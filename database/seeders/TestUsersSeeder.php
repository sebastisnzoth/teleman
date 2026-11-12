<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get roles
        $adminRole = Role::where('slug', Role::ADMIN)->first();
        $customerRole = Role::where('slug', Role::CUSTOMER)->first();
        $agentRole = Role::where('slug', Role::AGENT)->first();

        if (!$adminRole || !$customerRole || !$agentRole) {
            $this->command->error('❌ Roles not found! Please run RoleSeeder first.');
            $this->command->info('Run: php artisan db:seed --class=RoleSeeder');
            return;
        }

        // Create Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@mail.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
                'credit_balance' => 1000.00,
                'preferred_currency' => 'USD',
            ]
        );

        // Assign admin role
        if (!$admin->hasRole(Role::ADMIN)) {
            $admin->roles()->attach($adminRole->id);
        }

        $this->command->info('✅ Admin user created: admin@mail.com / password');

        // Create Customer User
        $customer = User::firstOrCreate(
            ['email' => 'customer@mail.com'],
            [
                'name' => 'Customer User',
                'password' => Hash::make('password'),
                'status' => 'active',
                'email_verified_at' => now(),
                'credit_balance' => 500.00,
                'preferred_currency' => 'USD',
            ]
        );

        // Assign customer role
        if (!$customer->hasRole(Role::CUSTOMER)) {
            $customer->roles()->attach($customerRole->id);
        }

        $this->command->info('✅ Customer user created: customer@mail.com / password');

        // Create Agent User (child of customer)
        $agent = User::firstOrCreate(
            ['email' => 'agent@mail.com'],
            [
                'name' => 'Agent User',
                'password' => Hash::make('password'),
                'parent_user_id' => $customer->id,
                'status' => 'active',
                'email_verified_at' => now(),
                'credit_balance' => 0.00, // Agents typically don't have their own balance
                'preferred_currency' => 'USD',
            ]
        );

        // Assign agent role
        if (!$agent->hasRole(Role::AGENT)) {
            $agent->roles()->attach($agentRole->id);
        }

        $this->command->info('✅ Agent user created: agent@mail.com / password (parent: customer@mail.com)');

        $this->command->info('');
        $this->command->info('🎉 Test users created successfully!');
        $this->command->info('');
        $this->command->info('Login credentials:');
        $this->command->info('Admin:    admin@mail.com    / password');
        $this->command->info('Customer: customer@mail.com / password');
        $this->command->info('Agent:    agent@mail.com    / password');
    }
}
