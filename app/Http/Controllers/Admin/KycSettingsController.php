<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KycSetting;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KycSettingsController extends Controller
{
    /**
     * Display the KYC settings page
     */
    public function index(): Response
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only administrators can access KYC settings.');

        $settings = KycSetting::getAllGrouped();

        return Inertia::render('Admin/Kyc/Settings', [
            'settings' => $settings,
        ]);
    }

    /**
     * Update KYC settings
     */
    public function update(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403, 'Only administrators can update KYC settings.');

        $validated = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable',
        ]);

        foreach ($validated['settings'] as $setting) {
            KycSetting::set($setting['key'], $setting['value']);
        }

        KycSetting::clearCache();

        return back()->with('success', 'KYC settings updated successfully');
    }
}
