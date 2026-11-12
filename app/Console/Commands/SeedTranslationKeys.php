<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Language;
use App\Services\TranslationService;
use Illuminate\Console\Command;

/**
 * Give this installation any interface wording it is missing.
 *
 * The upgrade migration does this by itself; this is the same repair by hand,
 * for an installation whose screens are showing raw keys ("nav.dashboard"
 * where "Dashboard" belongs) and whose administrator wants it fixed now.
 */
class SeedTranslationKeys extends Command
{
    protected $signature = 'translations:seed-keys {file? : A JSON file to take the keys from, for one language}';

    protected $description = 'Add interface wording this installation is missing, without touching wording it already has';

    public function handle(TranslationService $service): int
    {
        if (Language::count() === 0) {
            $this->error('No languages found. Run the language seeder first.');

            return self::FAILURE;
        }

        return $this->argument('file') === null
            ? $this->importShippedFiles($service)
            : $this->importOneFile($service, (string) $this->argument('file'));
    }

    /**
     * The normal path: every language that ships a file.
     */
    private function importShippedFiles(TranslationService $service): int
    {
        $added = $service->importMissingFromFiles();

        if ($added === []) {
            $this->info('Nothing to add — every language already has the wording this version ships.');

            return self::SUCCESS;
        }

        foreach ($added as $code => $count) {
            $this->line("  {$code}: {$count} added");
        }

        $this->info('Added '.array_sum($added).' missing entries across '.count($added).' language(s).');

        return self::SUCCESS;
    }

    /**
     * One file, applied to the default language — for a translator handing
     * over a file that is not one of the shipped ones.
     */
    private function importOneFile(TranslationService $service, string $file): int
    {
        if (! is_file($file)) {
            $this->error("File not found: {$file}");

            return self::FAILURE;
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        if (! is_array($decoded)) {
            $this->error('That file is not valid JSON.');

            return self::FAILURE;
        }

        $language = Language::getDefault() ?? Language::first();

        // Insert-only, like the shipped path: an administrator who rewrote a
        // string in the translation editor keeps their wording.
        $count = $service->importMissingFromArray($language->id, $decoded);
        $service->clearCache();

        $this->info("Added {$count} missing entries to {$language->code} from ".basename($file).'.');

        return self::SUCCESS;
    }
}
