<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Everything a fresh install needs, in dependency order. The web
     * installer runs these one at a time (each in its own request budget),
     * so the list lives here where both entry points share it.
     *
     * @var list<class-string<Seeder>>
     */
    public const SEEDERS = [
        RoleSeeder::class,
        PermissionSeeder::class,
        ThemeSeeder::class,
        CampaignTemplateSeeder::class,
        PricingRuleSeeder::class,
        LanguageSeeder::class,
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed roles and permissions first
        $this->call(self::SEEDERS);

        // Default test users are NOT created during installation.
        // The installer will create the admin user during setup.
        // To seed test users for local development, run: php artisan db:seed --class=TestUserSeeder
    }
}
