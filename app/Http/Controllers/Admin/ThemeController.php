<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ThemeBenefit;
use App\Models\ThemeFaq;
use App\Models\ThemeFeature;
use App\Models\ThemeFooter;
use App\Models\ThemeHero;
use App\Models\ThemePricing;
use App\Models\ThemeSetting;
use App\Models\ThemeStat;
use App\Models\ThemeUseCase;
use App\Services\ThemeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ThemeController extends Controller
{
    public function __construct(
        private ThemeService $themeService
    ) {
    }

    /**
     * Display theme management overview
     */
    public function index()
    {
        $stats = [
            'settings' => ThemeSetting::count(),
            'hero' => ThemeHero::count(),
            'stats' => ThemeStat::count(),
            'features' => ThemeFeature::count(),
            'benefits' => ThemeBenefit::count(),
            'use_cases' => ThemeUseCase::count(),
            'pricing' => ThemePricing::count(),
            'faqs' => ThemeFaq::count(),
            'footer' => ThemeFooter::count(),
        ];

        return Inertia::render('Admin/theme/Index', [
            'stats' => $stats,
        ]);
    }

    // ==================== SETTINGS ====================
    
    /**
     * Show settings edit form
     */
    public function settingsEdit()
    {
        $settings = ThemeSetting::first() ?? new ThemeSetting();
        
        return Inertia::render('Admin/theme/Settings', [
            'settings' => $settings,
        ]);
    }

    /**
     * Update settings
     */
    public function settingsUpdate(Request $request)
    {
        $validated = $request->validate([
            'site_name' => 'required|string|max:255',
            'site_tagline' => 'nullable|string|max:255',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords' => 'nullable|string|max:500',
            'logo_path' => 'nullable|string|max:500',
            'favicon_path' => 'nullable|string|max:500',
            'og_image_path' => 'nullable|string|max:500',
            'twitter_card' => 'nullable|string|max:50',
            'twitter_site' => 'nullable|string|max:100',
            'google_analytics_id' => 'nullable|string|max:50',
            'google_tag_manager_id' => 'nullable|string|max:50',
            'facebook_pixel_id' => 'nullable|string|max:50',
            'custom_head_scripts' => 'nullable|string',
            'custom_body_scripts' => 'nullable|string',
            'primary_color' => 'nullable|string|max:50',
            'secondary_color' => 'nullable|string|max:50',
            'can_register' => 'required|boolean',
            'trust_badges' => 'nullable|array',
            'social_links' => 'nullable|array',
            'legal_links' => 'nullable|array',
            'copyright_text' => 'nullable|string|max:500',
        ]);

        $settings = ThemeSetting::first() ?? new ThemeSetting();
        $settings->fill($validated);
        $settings->save();

        $this->themeService->clearCacheFor('settings');

        return redirect()->back()->with('success', 'Settings updated successfully');
    }

    // ==================== HERO ====================
    
    /**
     * Show hero edit form
     */
    public function heroEdit()
    {
        $hero = ThemeHero::active()->first() ?? new ThemeHero();
        
        return Inertia::render('Admin/theme/Hero', [
            'hero' => $hero,
        ]);
    }

    /**
     * Update hero
     */
    public function heroUpdate(Request $request)
    {
        $validated = $request->validate([
            'badge_text' => 'required|string|max:255',
            'badge_icon_left' => 'nullable|string|max:100',
            'badge_icon_right' => 'nullable|string|max:100',
            'typewriter_text' => 'required|string|max:500',
            'subtitle' => 'required|string|max:1000',
            'subtitle_emoji' => 'nullable|string|max:50',
            'primary_cta_text' => 'required|string|max:255',
            'primary_cta_icon' => 'nullable|string|max:100',
            'secondary_cta_text' => 'required|string|max:255',
            'secondary_cta_icon' => 'nullable|string|max:100',
            'background_gradient' => 'nullable|string',
            'is_active' => 'required|boolean',
        ]);

        $hero = ThemeHero::first() ?? new ThemeHero();
        $hero->fill($validated);
        $hero->save();

        $this->themeService->clearCacheFor('hero');

        return redirect()->back()->with('success', 'Hero section updated successfully!');
    }

    // ==================== STATS ====================
    
    /**
     * List all stats
     */
    public function statsIndex()
    {
        $stats = ThemeStat::ordered()->get();
        
        return Inertia::render('Admin/theme/Stats', [
            'stats' => $stats,
        ]);
    }

    /**
     * Store new stat
     */
    public function statsStore(Request $request)
    {
        $validated = $request->validate([
            'number' => 'required|string|max:100',
            'label' => 'required|string|max:255',
            'icon' => 'required|string|max:100',
            'order' => 'required|integer',
            'is_active' => 'required|boolean',
        ]);

        ThemeStat::create($validated);
        
        $this->themeService->clearCacheFor('stats');

        return redirect()->back()->with('success', 'Stat created successfully!');
    }

    /**
     * Update stat
     */
    public function statsUpdate(Request $request, ThemeStat $stat)
    {
        $validated = $request->validate([
            'number' => 'required|string|max:100',
            'label' => 'required|string|max:255',
            'icon' => 'required|string|max:100',
            'order' => 'required|integer',
            'is_active' => 'required|boolean',
        ]);

        $stat->update($validated);
        
        $this->themeService->clearCacheFor('stats');

        return redirect()->back()->with('success', 'Stat updated successfully!');
    }

    /**
     * Delete stat
     */
    public function statsDestroy(ThemeStat $stat)
    {
        $stat->delete();
        
        $this->themeService->clearCacheFor('stats');

        return redirect()->back()->with('success', 'Stat deleted successfully!');
    }

    // ==================== FEATURES ====================
    
    /**
     * List all features
     */
    public function featuresIndex()
    {
        $features = ThemeFeature::ordered()->get();
        
        return Inertia::render('Admin/theme/Features', [
            'features' => $features,
        ]);
    }

    /**
     * Store new feature
     */
    public function featuresStore(Request $request)
    {
        $validated = $request->validate([
            'icon' => 'required|string|max:100',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'gradient' => 'required|string|max:500',
            'icon_bg' => 'required|string|max:500',
            'order' => 'required|integer',
            'is_active' => 'required|boolean',
        ]);

        ThemeFeature::create($validated);
        
        $this->themeService->clearCacheFor('features');

        return redirect()->back()->with('success', 'Feature created successfully!');
    }

    /**
     * Update feature
     */
    public function featuresUpdate(Request $request, ThemeFeature $feature)
    {
        $validated = $request->validate([
            'icon' => 'required|string|max:100',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'gradient' => 'required|string|max:500',
            'icon_bg' => 'required|string|max:500',
            'order' => 'required|integer',
            'is_active' => 'required|boolean',
        ]);

        $feature->update($validated);
        
        $this->themeService->clearCacheFor('features');

        return redirect()->back()->with('success', 'Feature updated successfully!');
    }

    /**
     * Delete feature
     */
    public function featuresDestroy(ThemeFeature $feature)
    {
        $feature->delete();
        
        $this->themeService->clearCacheFor('features');

        return redirect()->back()->with('success', 'Feature deleted successfully!');
    }

    // ==================== BENEFITS ====================
    
    /**
     * List all benefits
     */
    public function benefitsIndex()
    {
        $benefits = ThemeBenefit::ordered()->get();
        
        return Inertia::render('Admin/theme/Benefits', [
            'benefits' => $benefits,
        ]);
    }

    /**
     * Store new benefit
     */
    public function benefitsStore(Request $request)
    {
        $validated = $request->validate([
            'icon' => 'required|string|max:100',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'order' => 'required|integer',
            'is_active' => 'required|boolean',
        ]);

        ThemeBenefit::create($validated);
        
        $this->themeService->clearCacheFor('benefits');

        return redirect()->back()->with('success', 'Benefit created successfully!');
    }

    /**
     * Update benefit
     */
    public function benefitsUpdate(Request $request, ThemeBenefit $benefit)
    {
        $validated = $request->validate([
            'icon' => 'required|string|max:100',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'order' => 'required|integer',
            'is_active' => 'required|boolean',
        ]);

        $benefit->update($validated);
        
        $this->themeService->clearCacheFor('benefits');

        return redirect()->back()->with('success', 'Benefit updated successfully!');
    }

    /**
     * Delete benefit
     */
    public function benefitsDestroy(ThemeBenefit $benefit)
    {
        $benefit->delete();
        
        $this->themeService->clearCacheFor('benefits');

        return redirect()->back()->with('success', 'Benefit deleted successfully!');
    }

    // ==================== USE CASES ====================
    
    /**
     * List all use cases
     */
    public function useCasesIndex()
    {
        $useCases = ThemeUseCase::ordered()->get();
        
        return Inertia::render('Admin/theme/UseCases', [
            'useCases' => $useCases,
        ]);
    }

    /**
     * Store new use case
     */
    public function useCasesStore(Request $request)
    {
        $validated = $request->validate([
            'icon' => 'required|string|max:100',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'items' => 'required|array',
            'color' => 'required|string|max:500',
            'order' => 'required|integer',
            'is_active' => 'required|boolean',
        ]);

        ThemeUseCase::create($validated);
        
        $this->themeService->clearCacheFor('useCases');

        return redirect()->back()->with('success', 'Use case created successfully!');
    }

    /**
     * Update use case
     */
    public function useCasesUpdate(Request $request, ThemeUseCase $useCase)
    {
        $validated = $request->validate([
            'icon' => 'required|string|max:100',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'items' => 'required|array',
            'color' => 'required|string|max:500',
            'order' => 'required|integer',
            'is_active' => 'required|boolean',
        ]);

        $useCase->update($validated);
        
        $this->themeService->clearCacheFor('useCases');

        return redirect()->back()->with('success', 'Use case updated successfully!');
    }

    /**
     * Delete use case
     */
    public function useCasesDestroy(ThemeUseCase $useCase)
    {
        $useCase->delete();
        
        $this->themeService->clearCacheFor('useCases');

        return redirect()->back()->with('success', 'Use case deleted successfully!');
    }

    // ==================== PRICING ====================
    
    /**
     * List all pricing packages
     */
    public function pricingIndex()
    {
        $pricing = ThemePricing::ordered()->get();
        
        return Inertia::render('Admin/theme/Pricing', [
            'pricing' => $pricing,
        ]);
    }

    /**
     * Store new pricing package
     */
    public function pricingStore(Request $request)
    {
        $validated = $request->validate([
            'credits' => 'required|string|max:100',
            'price' => 'required|string|max:100',
            'per_credit' => 'required|string|max:100',
            'popular' => 'required|boolean',
            'icon' => 'required|string|max:100',
            'savings' => 'nullable|string|max:100',
            'order' => 'required|integer',
            'is_active' => 'required|boolean',
        ]);

        ThemePricing::create($validated);
        
        $this->themeService->clearCacheFor('pricing');

        return redirect()->back()->with('success', 'Pricing package created successfully!');
    }

    /**
     * Update pricing package
     */
    public function pricingUpdate(Request $request, ThemePricing $pricing)
    {
        $validated = $request->validate([
            'credits' => 'required|string|max:100',
            'price' => 'required|string|max:100',
            'per_credit' => 'required|string|max:100',
            'popular' => 'required|boolean',
            'icon' => 'required|string|max:100',
            'savings' => 'nullable|string|max:100',
            'order' => 'required|integer',
            'is_active' => 'required|boolean',
        ]);

        $pricing->update($validated);
        
        $this->themeService->clearCacheFor('pricing');

        return redirect()->back()->with('success', 'Pricing package updated successfully!');
    }

    /**
     * Delete pricing package
     */
    public function pricingDestroy(ThemePricing $pricing)
    {
        $pricing->delete();
        
        $this->themeService->clearCacheFor('pricing');

        return redirect()->back()->with('success', 'Pricing package deleted successfully!');
    }

    // ==================== FAQS ====================
    
    /**
     * List all FAQs
     */
    public function faqsIndex()
    {
        $faqs = ThemeFaq::ordered()->get();
        
        return Inertia::render('Admin/theme/Faqs', [
            'faqs' => $faqs,
        ]);
    }

    /**
     * Store new FAQ
     */
    public function faqsStore(Request $request)
    {
        $validated = $request->validate([
            'question' => 'required|string|max:500',
            'answer' => 'required|string',
            'icon' => 'required|string|max:100',
            'order' => 'required|integer',
            'is_active' => 'required|boolean',
        ]);

        ThemeFaq::create($validated);
        
        $this->themeService->clearCacheFor('faqs');

        return redirect()->back()->with('success', 'FAQ created successfully!');
    }

    /**
     * Update FAQ
     */
    public function faqsUpdate(Request $request, ThemeFaq $faq)
    {
        $validated = $request->validate([
            'question' => 'required|string|max:500',
            'answer' => 'required|string',
            'icon' => 'required|string|max:100',
            'order' => 'required|integer',
            'is_active' => 'required|boolean',
        ]);

        $faq->update($validated);
        
        $this->themeService->clearCacheFor('faqs');

        return redirect()->back()->with('success', 'FAQ updated successfully!');
    }

    /**
     * Delete FAQ
     */
    public function faqsDestroy(ThemeFaq $faq)
    {
        $faq->delete();
        
        $this->themeService->clearCacheFor('faqs');

        return redirect()->back()->with('success', 'FAQ deleted successfully!');
    }

    // ==================== FOOTER ====================
    
    /**
     * Show footer edit form
     */
    public function footerEdit()
    {
        $footer = ThemeFooter::active()->first() ?? new ThemeFooter();
        
        return Inertia::render('Admin/theme/Footer', [
            'footer' => $footer,
        ]);
    }

    /**
     * Update footer
     */
    public function footerUpdate(Request $request)
    {
        $validated = $request->validate([
            'cta_badge_text' => 'required|string|max:255',
            'cta_badge_icon' => 'required|string|max:100',
            'cta_headline' => 'required|string|max:500',
            'cta_description' => 'required|string',
            'cta_primary_text' => 'required|string|max:255',
            'cta_primary_icon' => 'nullable|string|max:100',
            'cta_secondary_text' => 'required|string|max:255',
            'trust_indicators' => 'nullable|array',
            'is_active' => 'required|boolean',
        ]);

        $footer = ThemeFooter::first() ?? new ThemeFooter();
        $footer->fill($validated);
        $footer->save();

        $this->themeService->clearCacheFor('footer');

        return redirect()->back()->with('success', 'Footer section updated successfully!');
    }

    /**
     * Upload logo
     */
    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpg,jpeg,png,svg|max:2048', // 2MB max
        ]);

        // Delete old logo if exists
        $settings = ThemeSetting::first();
        if ($settings && $settings->logo_path) {
            // Extract the path after 'storage/' to delete from public disk
            $oldPath = str_replace('/storage/', '', $settings->logo_path);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        // Store new logo
        $path = $request->file('logo')->store('theme', 'public');
        
        // Store the path as 'theme/filename.png' not the full URL
        if (!$settings) {
            $settings = new ThemeSetting();
        }
        $settings->logo_path = $path;
        $settings->save();

        $this->themeService->clearCacheFor('settings');

        return redirect()->back()->with('success', 'Logo uploaded successfully!');
    }

    /**
     * Delete logo
     */
    public function deleteLogo()
    {
        $settings = ThemeSetting::first();

        if ($settings && $settings->logo_path) {
            Storage::disk('public')->delete(str_replace('/storage/', '', $settings->logo_path));
            $settings->logo_path = null;
            $settings->save();

            $this->themeService->clearCacheFor('settings');

            return redirect()->back()->with('success', 'Logo deleted successfully!');
        }

        return redirect()->back()->with('error', 'No logo to delete');
    }

    /**
     * Upload OG image
     */
    public function uploadOgImage(Request $request)
    {
        $request->validate([
            'og_image' => 'required|image|mimes:jpg,jpeg,png,webp|max:4096', // 4MB max for OG images (1200x630)
        ]);

        // Delete old OG image if exists
        $settings = ThemeSetting::first();
        if ($settings && $settings->og_image_path) {
            $oldPath = str_replace('/storage/', '', $settings->og_image_path);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        // Store new OG image
        $path = $request->file('og_image')->store('theme', 'public');

        if (!$settings) {
            $settings = new ThemeSetting();
        }
        $settings->og_image_path = $path;
        $settings->save();

        $this->themeService->clearCacheFor('settings');

        return redirect()->back()->with('success', 'Social share image uploaded successfully!');
    }

    /**
     * Delete OG image
     */
    public function deleteOgImage()
    {
        $settings = ThemeSetting::first();

        if ($settings && $settings->og_image_path) {
            $oldPath = str_replace('/storage/', '', $settings->og_image_path);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
            $settings->og_image_path = null;
            $settings->save();

            $this->themeService->clearCacheFor('settings');

            return redirect()->back()->with('success', 'Social share image deleted successfully!');
        }

        return redirect()->back()->with('error', 'No social share image to delete');
    }
}

