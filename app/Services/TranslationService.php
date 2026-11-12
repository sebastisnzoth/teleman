<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Language;
use App\Models\Translation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TranslationService
{
    /**
     * Get flat translations for a locale (key => value).
     * Falls back to the default language value for any missing/untranslated keys.
     */
    public function getFlatTranslationsForLocale(string $locale): array
    {
        return Cache::remember("translations_flat_{$locale}", 3600, function () use ($locale) {
            // Rebuilding this cache is the moment to notice that the version
            // now running ships wording this database has never seen, and to
            // take it. Upgrading used to depend on a migration, which is a
            // one-time event: wording added in any later version — including
            // wording added minutes later — had nothing left to carry it
            // across, and reached the screen as its raw key. Checking here
            // instead means the catalogue repairs itself, on every release,
            // without anyone having to remember a command.
            //
            // The cost is a handful of indexed lookups an hour: this runs
            // only when the cache is cold, and finds nothing to do unless a
            // release actually added wording.
            $this->syncShippedCatalogue();

            $language = Language::where('code', $locale)->active()->first();

            if (! $language) {
                return [];
            }

            // Get this locale's translated values
            $translations = Translation::where('language_id', $language->id)
                ->whereNotNull('value')
                ->where('value', '!=', '')
                ->pluck('value', 'key')
                ->toArray();

            // If not the default language, fill missing keys with default language values
            if (! $language->is_default) {
                $defaultLanguage = Language::getDefault();
                if ($defaultLanguage && $defaultLanguage->id !== $language->id) {
                    $defaults = Translation::where('language_id', $defaultLanguage->id)
                        ->whereNotNull('value')
                        ->where('value', '!=', '')
                        ->pluck('value', 'key')
                        ->toArray();

                    // Merge: locale-specific values override defaults
                    $translations = array_merge($defaults, $translations);
                }
            }

            return $translations;
        });
    }

    /**
     * Take any wording the running version ships that this database is
     * missing.
     *
     * Wrapped in rescue() because it runs inside a page render: during an
     * install the tables it reads do not exist yet, and a translation
     * catalogue that cannot be topped up is never a reason to fail a
     * request. The worst case is the raw keys that were showing anyway.
     */
    private function syncShippedCatalogue(): void
    {
        rescue(fn () => $this->importMissingFromFiles(), report: false);
    }

    /**
     * Export DB translations to JSON file for Laravel's __() function.
     */
    public function exportToJson(string $locale): void
    {
        $translations = $this->getFlatTranslationsForLocale($locale);
        $langDir = lang_path();

        if (! is_dir($langDir)) {
            mkdir($langDir, 0755, true);
        }

        file_put_contents(
            "{$langDir}/{$locale}.json",
            json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        Log::info("Translations exported for locale: {$locale}", [
            'count' => count($translations),
        ]);
    }

    /**
     * Export all active languages to JSON files.
     */
    public function exportAll(): void
    {
        $locales = Language::active()->pluck('code');

        foreach ($locales as $locale) {
            $this->exportToJson($locale);
        }
    }

    /**
     * Clear translation caches.
     */
    public function clearCache(?string $locale = null): void
    {
        if ($locale) {
            Cache::forget("translations_flat_{$locale}");
        } else {
            $locales = Language::pluck('code');
            foreach ($locales as $code) {
                Cache::forget("translations_flat_{$code}");
            }
        }

        Cache::forget('active_languages');
        Cache::forget('active_locale_codes');
        Cache::forget('default_language');
    }

    /**
     * Import translations from a JSON array into the database.
     */
    public function importFromArray(int $languageId, array $translations, string $group = 'app'): int
    {
        // One upsert per 500 rows instead of a select + insert per key.
        // The installer imports ~19,000 rows; row by row that was the
        // slowest thing it did, and on shared hosting it could not finish
        // inside a request. (language_id, group, key) is unique, so the
        // upsert updates existing values in place.
        $now = now();
        $rows = [];

        foreach ($translations as $key => $value) {
            $rows[] = [
                'language_id' => $languageId,
                'group' => $group,
                'key' => (string) $key,
                'value' => is_string($value) ? $value : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            Translation::upsert($chunk, ['language_id', 'group', 'key'], ['value', 'updated_at']);
        }

        return count($rows);
    }

    /**
     * Add the keys this language does not have yet, and touch nothing else.
     *
     * Seeders only run when a database is first created, so an installation
     * that upgrades gets no new interface wording: the catalogue it was
     * installed with is the catalogue it keeps, and every string added since
     * then renders to the customer as its raw key ("admin.telephony.locked_to").
     * This is what repairs that, so it has to be safe to run on a live
     * installation whose administrator may have rewritten any string in the
     * admin translation editor.
     *
     * So it is insert-only: importFromArray() upserts and would overwrite
     * their wording with the shipped English. A key already present is left
     * exactly as it is, whatever its value and whichever group it sits under
     * — the lookup is by key alone, because the same key can have been filed
     * under a different group by an older importer, and inserting it again
     * under this group would leave two rows fighting over one name.
     *
     * @param  array<string, mixed>  $translations
     * @return int how many keys were actually added
     */
    public function importMissingFromArray(int $languageId, array $translations, string $group = 'app'): int
    {
        if ($translations === []) {
            return 0;
        }

        $existing = Translation::where('language_id', $languageId)
            ->whereIn('key', array_map(strval(...), array_keys($translations)))
            ->pluck('key')
            ->flip();

        $now = now();
        $rows = [];

        foreach ($translations as $key => $value) {
            $key = (string) $key;

            if ($existing->has($key)) {
                continue;
            }

            $rows[] = [
                'language_id' => $languageId,
                'group' => $group,
                'key' => $key,
                'value' => is_string($value) ? $value : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // insertOrIgnore rather than insert: two requests racing through the
        // same upgrade must not turn a duplicate key into a failed migration.
        foreach (array_chunk($rows, 500) as $chunk) {
            Translation::insertOrIgnore($chunk);
        }

        return count($rows);
    }

    /**
     * Add every key the shipped language files carry that the database is
     * missing, for all languages that have a file, and drop the caches the
     * old catalogue is sitting in.
     *
     * @return array<string, int> locale => keys added
     */
    public function importMissingFromFiles(): array
    {
        $added = [];

        foreach (Language::all() as $language) {
            $path = base_path("lang/{$language->code}.json");

            if (! is_file($path)) {
                continue;
            }

            $decoded = json_decode((string) file_get_contents($path), true);

            if (! is_array($decoded)) {
                Log::warning("Translation file for {$language->code} is not valid JSON; skipped.");

                continue;
            }

            $count = $this->importMissingFromArray($language->id, $decoded);

            if ($count > 0) {
                $added[$language->code] = $count;
            }
        }

        if ($added !== []) {
            $this->clearCache();
        }

        return $added;
    }

    /**
     * Get translation completion stats for a language.
     */
    public function getCompletionStats(int $languageId): array
    {
        $total = Translation::where('language_id', $languageId)->count();
        $translated = Translation::where('language_id', $languageId)
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->count();

        return [
            'total' => $total,
            'translated' => $translated,
            'percentage' => $total > 0 ? round(($translated / $total) * 100, 1) : 0,
        ];
    }

    /**
     * Copy all translation keys from the default language to a new language (with empty values).
     */
    public function copyKeysFromDefault(Language $newLanguage): int
    {
        $defaultLanguage = Language::getDefault();

        if (! $defaultLanguage || $defaultLanguage->id === $newLanguage->id) {
            return 0;
        }

        $defaultTranslations = Translation::where('language_id', $defaultLanguage->id)->get();
        $count = 0;

        foreach ($defaultTranslations as $translation) {
            Translation::firstOrCreate(
                [
                    'language_id' => $newLanguage->id,
                    'group' => $translation->group,
                    'key' => $translation->key,
                ],
                ['value' => null]
            );
            $count++;
        }

        return $count;
    }
}
