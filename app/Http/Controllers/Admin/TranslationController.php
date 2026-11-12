<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Translation;
use App\Services\TranslationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class TranslationController extends Controller
{
    public function __construct(
        protected TranslationService $translationService
    ) {}

    public function index(Request $request, Language $language): Response
    {
        $query = Translation::where('language_id', $language->id);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('key', 'like', "%{$search}%")
                  ->orWhere('value', 'like', "%{$search}%");
            });
        }

        if ($request->filled('group') && $request->group !== 'all') {
            $query->where('group', $request->group);
        }

        if ($request->boolean('untranslated')) {
            $query->where(function ($q) {
                $q->whereNull('value')->orWhere('value', '');
            });
        }

        $translations = $query->orderBy('group')
            ->orderBy('key')
            ->paginate($request->integer('per_page', 50))
            ->withQueryString();

        $groups = Translation::where('language_id', $language->id)
            ->distinct()
            ->pluck('group');

        $stats = $this->translationService->getCompletionStats($language->id);

        return Inertia::render('Admin/Languages/Translations', [
            'language' => $language,
            'translations' => $translations,
            'groups' => $groups,
            'stats' => $stats,
            'filters' => $request->only(['search', 'group', 'untranslated', 'per_page']),
        ]);
    }

    public function update(Request $request, Language $language): RedirectResponse
    {
        $validated = $request->validate([
            'translations' => 'required|array',
            'translations.*.id' => 'required|integer|exists:translations,id',
            'translations.*.value' => 'nullable|string',
        ]);

        foreach ($validated['translations'] as $item) {
            Translation::where('id', $item['id'])
                ->where('language_id', $language->id)
                ->update(['value' => $item['value']]);
        }

        $this->translationService->clearCache($language->code);
        $this->translationService->exportToJson($language->code);

        return back()->with('success', count($validated['translations']) . ' translations updated.');
    }

    public function import(Request $request, Language $language): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:json,txt|max:5120',
            'group' => 'nullable|string|max:50',
        ]);

        $content = file_get_contents($request->file('file')->path());
        $json = json_decode($content, true);

        if (!is_array($json)) {
            return back()->with('error', 'Invalid JSON file.');
        }

        $group = $request->get('group', 'app');
        $count = $this->translationService->importFromArray($language->id, $json, $group);

        $this->translationService->clearCache($language->code);
        $this->translationService->exportToJson($language->code);

        return back()->with('success', "{$count} translations imported successfully.");
    }

    public function export(Language $language): JsonResponse
    {
        $translations = Translation::where('language_id', $language->id)
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->pluck('value', 'key')
            ->toArray();

        return response()->json($translations, 200, [
            'Content-Disposition' => "attachment; filename={$language->code}.json",
        ]);
    }
}
