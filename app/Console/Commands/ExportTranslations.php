<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TranslationService;
use Illuminate\Console\Command;

class ExportTranslations extends Command
{
    protected $signature = 'translations:export {locale? : Specific locale to export}';

    protected $description = 'Export translations from database to JSON files for Laravel\'s __() function';

    public function handle(TranslationService $service): int
    {
        if ($locale = $this->argument('locale')) {
            $service->exportToJson($locale);
            $this->info("Exported translations for {$locale}.");
        } else {
            $service->exportAll();
            $this->info('Exported all translations.');
        }

        return self::SUCCESS;
    }
}
