<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Translation;
use App\Services\TranslationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LanguageController extends Controller
{
    public function __construct(
        protected TranslationService $translationService
    ) {}

    public function index(): Response
    {
        $languages = Language::withCount('translations')
            ->ordered()
            ->get();

        $defaultLanguage = Language::getDefault();
        $totalKeys = $defaultLanguage
            ? Translation::where('language_id', $defaultLanguage->id)->count()
            : 0;

        return Inertia::render('Admin/Languages/Index', [
            'languages' => $languages,
            'totalKeys' => $totalKeys,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Languages/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:languages,code',
            'name' => 'required|string|max:255',
            'native_name' => 'nullable|string|max:255',
            'flag' => 'nullable|string|max:10',
            'is_rtl' => 'boolean',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        $language = Language::create($validated);

        if ($validated['is_default'] ?? false) {
            Language::setDefault($language->id);
        }

        // Copy all keys from default language with empty values
        $this->translationService->copyKeysFromDefault($language);
        $this->translationService->clearCache();

        return redirect()->route('admin.languages.index')
            ->with('success', 'Language created successfully.');
    }

    public function edit(Language $language): Response
    {
        return Inertia::render('Admin/Languages/Edit', [
            'language' => $language,
        ]);
    }

    public function update(Request $request, Language $language): RedirectResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:languages,code,' . $language->id,
            'name' => 'required|string|max:255',
            'native_name' => 'nullable|string|max:255',
            'flag' => 'nullable|string|max:10',
            'is_rtl' => 'boolean',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        $language->update($validated);

        if ($validated['is_default'] ?? false) {
            Language::setDefault($language->id);
        }

        $this->translationService->clearCache();

        return redirect()->route('admin.languages.index')
            ->with('success', 'Language updated successfully.');
    }

    public function destroy(Language $language): RedirectResponse
    {
        if ($language->is_default) {
            return back()->with('error', 'Cannot delete the default language.');
        }

        $language->delete();
        $this->translationService->clearCache();

        return redirect()->route('admin.languages.index')
            ->with('success', 'Language deleted successfully.');
    }

    public function toggleActive(Language $language): RedirectResponse
    {
        if ($language->is_default && $language->is_active) {
            return back()->with('error', 'Cannot deactivate the default language.');
        }

        $language->update(['is_active' => !$language->is_active]);
        $this->translationService->clearCache();

        return back()->with('success', $language->is_active ? 'Language activated.' : 'Language deactivated.');
    }
}
