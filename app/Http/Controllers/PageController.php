<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\ThemeSetting;
use Inertia\Inertia;

class PageController extends Controller
{
    public function show(string $slug)
    {
        $page = Page::getBySlug($slug);

        if (!$page || !$page->is_active) {
            abort(404);
        }

        $settings = ThemeSetting::getSettings();

        return Inertia::render('Pages/Show', [
            'page' => $page,
            'settings' => $settings,
        ]);
    }
}
