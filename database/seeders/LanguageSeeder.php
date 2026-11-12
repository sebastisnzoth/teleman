<?php

namespace Database\Seeders;

use App\Models\Language;
use App\Services\TranslationService;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            [
                'code'        => 'en',
                'name'        => 'English',
                'native_name' => 'English',
                'flag'        => '🇺🇸',
                'is_rtl'      => false,
                'is_active'   => true,
                'is_default'  => true,
                'sort_order'  => 1,
            ],
            [
                'code'        => 'es',
                'name'        => 'Spanish',
                'native_name' => 'Español',
                'flag'        => '🇪🇸',
                'is_rtl'      => false,
                'is_active'   => false,
                'is_default'  => false,
                'sort_order'  => 2,
            ],
            [
                'code'        => 'fr',
                'name'        => 'French',
                'native_name' => 'Français',
                'flag'        => '🇫🇷',
                'is_rtl'      => false,
                'is_active'   => false,
                'is_default'  => false,
                'sort_order'  => 3,
            ],
            [
                'code'        => 'ar',
                'name'        => 'Arabic',
                'native_name' => 'العربية',
                'flag'        => '🇸🇦',
                'is_rtl'      => true,
                'is_active'   => false,
                'is_default'  => false,
                'sort_order'  => 4,
            ],
            [
                'code'        => 'zh',
                'name'        => 'Chinese (Simplified)',
                'native_name' => '中文（简体）',
                'flag'        => '🇨🇳',
                'is_rtl'      => false,
                'is_active'   => false,
                'is_default'  => false,
                'sort_order'  => 5,
            ],
            [
                'code'        => 'hi',
                'name'        => 'Hindi',
                'native_name' => 'हिन्दी',
                'flag'        => '🇮🇳',
                'is_rtl'      => false,
                'is_active'   => false,
                'is_default'  => false,
                'sort_order'  => 6,
            ],
            [
                'code'        => 'pt',
                'name'        => 'Portuguese',
                'native_name' => 'Português',
                'flag'        => '🇧🇷',
                'is_rtl'      => false,
                'is_active'   => false,
                'is_default'  => false,
                'sort_order'  => 7,
            ],
            [
                'code'        => 'ru',
                'name'        => 'Russian',
                'native_name' => 'Русский',
                'flag'        => '🇷🇺',
                'is_rtl'      => false,
                'is_active'   => false,
                'is_default'  => false,
                'sort_order'  => 8,
            ],
            [
                'code'        => 'ja',
                'name'        => 'Japanese',
                'native_name' => '日本語',
                'flag'        => '🇯🇵',
                'is_rtl'      => false,
                'is_active'   => false,
                'is_default'  => false,
                'sort_order'  => 9,
            ],
            [
                'code'        => 'de',
                'name'        => 'German',
                'native_name' => 'Deutsch',
                'flag'        => '🇩🇪',
                'is_rtl'      => false,
                'is_active'   => false,
                'is_default'  => false,
                'sort_order'  => 10,
            ],
        ];

        // ── Step 1: Create / update language records ────────────────────────
        foreach ($languages as $lang) {
            Language::firstOrCreate(
                ['code' => $lang['code']],
                $lang
            );
        }

        $this->command->info('✓ Language records seeded (' . count($languages) . ' languages)');

        // ── Step 2: Import translations from lang/{code}.json into DB ───────
        $service = app(TranslationService::class);
        $totalKeys = 0;
        $missing = [];

        foreach (Language::all() as $language) {
            $jsonPath = base_path("lang/{$language->code}.json");

            if (!file_exists($jsonPath)) {
                $missing[] = $language->code;
                $this->command->warn("  ⚠ Skipped {$language->code}: lang/{$language->code}.json not found");
                continue;
            }

            $json = json_decode(file_get_contents($jsonPath), true);

            if (!is_array($json)) {
                $this->command->error("  ✗ Invalid JSON in lang/{$language->code}.json — skipping");
                continue;
            }

            $count = $service->importFromArray($language->id, $json, 'app');
            $totalKeys += $count;

            $this->command->info("  ✓ {$language->code}: {$count} keys imported");
        }

        // ── Step 3: Clear translation cache so fresh data loads immediately ─
        $service->clearCache();

        $this->command->info("✓ Translation cache cleared");
        $this->command->info("✓ Done — {$totalKeys} total translation records loaded");

        if (!empty($missing)) {
            $this->command->warn('Missing JSON files for: ' . implode(', ', $missing));
            $this->command->warn('These languages will fall back to English until their JSON files are added.');
        }
    }
}
