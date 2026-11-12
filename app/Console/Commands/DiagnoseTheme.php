<?php

namespace App\Console\Commands;

use App\Models\ThemeFooter;
use App\Models\ThemeHero;
use App\Services\ThemeService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DiagnoseTheme extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'theme:diagnose {--clear-cache : Clear theme cache after diagnosis}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Diagnose theme customization issues and check cache status';

    /**
     * Execute the console command.
     */
    public function handle(ThemeService $themeService)
    {
        $this->info('🔍 Diagnosing Theme Configuration...');
        $this->newLine();

        // Check Database Content
        $this->info('📊 Database Status:');
        $this->checkDatabase();
        $this->newLine();

        // Check Cache Status
        $this->info('💾 Cache Status:');
        $this->checkCache();
        $this->newLine();

        // Check Theme Service
        $this->info('⚙️ Theme Service Status:');
        $this->checkThemeService($themeService);
        $this->newLine();

        // Clear cache if requested
        if ($this->option('clear-cache')) {
            $this->info('🧹 Clearing theme cache...');
            $themeService->clearCache();
            Cache::flush();
            $this->info('✅ Cache cleared successfully!');
            $this->newLine();
        }

        // Recommendations
        $this->info('💡 Recommendations:');
        $this->showRecommendations();

        return 0;
    }

    private function checkDatabase()
    {
        // Hero Section
        $hero = ThemeHero::first();
        if ($hero) {
            $this->line("  ✅ Hero section exists");
            $this->line("     - Badge: {$hero->badge_text}");
            $this->line("     - Typewriter: " . \Str::limit($hero->typewriter_text, 50));
            $this->line("     - Active: " . ($hero->is_active ? 'Yes' : 'No'));
            $this->line("     - Updated: {$hero->updated_at}");
        } else {
            $this->error("  ❌ No hero section found in database");
        }

        // Footer Section
        $footer = ThemeFooter::first();
        if ($footer) {
            $this->line("  ✅ Footer section exists");
            $this->line("     - CTA Badge: {$footer->cta_badge_text}");
            $this->line("     - Headline: " . \Str::limit($footer->cta_headline, 50));
            $this->line("     - Active: " . ($footer->is_active ? 'Yes' : 'No'));
            $this->line("     - Updated: {$footer->updated_at}");
        } else {
            $this->error("  ❌ No footer section found in database");
        }

        // Stats count
        $this->line("  ℹ️ Stats: " . DB::table('theme_stats')->count());
        $this->line("  ℹ️ Features: " . DB::table('theme_features')->count());
        $this->line("  ℹ️ Benefits: " . DB::table('theme_benefits')->count());
    }

    private function checkCache()
    {
        $cacheKeys = ['all', 'settings', 'hero', 'stats', 'benefits', 'features', 'use_cases', 'pricing', 'faqs', 'footer'];
        
        foreach ($cacheKeys as $key) {
            $fullKey = 'theme:' . $key;
            $cached = Cache::has($fullKey);
            
            if ($cached) {
                $data = Cache::get($fullKey);
                $this->warn("  ⚠️ {$key}: CACHED");
                
                if ($key === 'hero' && $data) {
                    $this->line("     - Cached badge: " . ($data['badge_text'] ?? 'N/A'));
                } elseif ($key === 'footer' && $data) {
                    $this->line("     - Cached CTA: " . ($data['cta_badge_text'] ?? 'N/A'));
                }
            } else {
                $this->line("  ✅ {$key}: Not cached (will fetch from DB)");
            }
        }
    }

    private function checkThemeService(ThemeService $themeService)
    {
        try {
            $hero = $themeService->getHero();
            if ($hero) {
                $this->line("  ✅ Theme service can fetch hero");
                $this->line("     - Badge: " . ($hero['badge_text'] ?? 'N/A'));
            } else {
                $this->error("  ❌ Theme service returned null for hero");
            }

            $footer = $themeService->getFooter();
            if ($footer) {
                $this->line("  ✅ Theme service can fetch footer");
                $this->line("     - CTA: " . ($footer['cta_badge_text'] ?? 'N/A'));
            } else {
                $this->error("  ❌ Theme service returned null for footer");
            }
        } catch (\Exception $e) {
            $this->error("  ❌ Error fetching from theme service: " . $e->getMessage());
        }
    }

    private function showRecommendations()
    {
        $hero = ThemeHero::first();
        $heroCache = Cache::get('theme:hero');

        if ($hero && $heroCache) {
            $dbBadge = $hero->badge_text;
            $cacheBadge = $heroCache['badge_text'] ?? null;

            if ($dbBadge !== $cacheBadge) {
                $this->warn("  ⚠️ Cache mismatch detected!");
                $this->line("     DB: {$dbBadge}");
                $this->line("     Cache: {$cacheBadge}");
                $this->info("     → Run: php artisan theme:diagnose --clear-cache");
                return;
            }
        }

        if (Cache::has('theme:all')) {
            $this->line("  ℹ️ Theme is cached (good for performance)");
            $this->line("     → If customizations not showing, run: php artisan cache:clear");
        } else {
            $this->line("  ✅ No cache issues detected");
            $this->line("     → Changes should be visible immediately");
        }

        $this->newLine();
        $this->line("  📝 After making theme changes:");
        $this->line("     1. Check database: php artisan theme:diagnose");
        $this->line("     2. Clear cache: php artisan cache:clear");
        $this->line("     3. Clear browser cache: Ctrl+Shift+R");
        $this->line("     4. Rebuild frontend: npm run build (if using production)");
    }
}
