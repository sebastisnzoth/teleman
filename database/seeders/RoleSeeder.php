<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Admin',
                'slug' => Role::ADMIN,
                'description' => 'Full system access. Can manage all users, roles, permissions, campaigns, and system settings.',
                'level' => Role::LEVEL_ADMIN,
                'is_system' => true,
            ],
            [
                'name' => 'Customer',
                'slug' => Role::CUSTOMER,
                'description' => 'Business user who can create campaigns, manage contacts, and create agents.',
                'level' => Role::LEVEL_CUSTOMER,
                'is_system' => true,
            ],
            [
                'name' => 'Agent',
                'slug' => Role::AGENT,
                'description' => 'Limited user created by Admin or Customer. Can execute campaigns and make calls based on assigned permissions.',
                'level' => Role::LEVEL_AGENT,
                'is_system' => true,
            ],
        ];

        foreach ($roles as $roleData) {
            Role::updateOrCreate(
                ['slug' => $roleData['slug']],
                $roleData
            );
        }

        $this->command->info('Roles seeded successfully!');
    }
}

