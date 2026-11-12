<?php

namespace App\Services;

use App\Models\ThemeSetting;
use App\Models\ThemeHero;
use App\Models\ThemeStat;
use App\Models\ThemeFeature;
use App\Models\ThemeBenefit;
use App\Models\ThemeUseCase;
use App\Models\ThemePricing;
use App\Models\ThemeFaq;
use App\Models\ThemeFooter;
use Illuminate\Support\Facades\Cache;

class ThemeService
{
    const CACHE_TTL = 86400; // 24 hours
    const CACHE_PREFIX = 'theme:';

    /**
     * Get all theme data with caching
     */
    public function getAllThemeData(): array
    {
        return Cache::remember(self::CACHE_PREFIX . 'all', self::CACHE_TTL, function () {
            return [
                'settings' => $this->getSettings(),
                'hero' => $this->getHero(),
                'stats' => $this->getStats(),
                'benefits' => $this->getBenefits(),
                'features' => $this->getFeatures(),
                'useCases' => $this->getUseCases(),
                'pricing' => $this->getPricing(),
                'faqs' => $this->getFaqs(),
                'footer' => $this->getFooter(),
            ];
        });
    }

    /**
     * Get theme settings
     */
    public function getSettings()
    {
        return Cache::remember(self::CACHE_PREFIX . 'settings', self::CACHE_TTL, function () {
            return ThemeSetting::getSettings();
        });
    }

    /**
     * Get hero section
     */
    public function getHero()
    {
        return Cache::remember(self::CACHE_PREFIX . 'hero', self::CACHE_TTL, function () {
            return ThemeHero::getActive();
        });
    }

    /**
     * Get stats
     */
    public function getStats()
    {
        return Cache::remember(self::CACHE_PREFIX . 'stats', self::CACHE_TTL, function () {
            return ThemeStat::getActive();
        });
    }

    /**
     * Get benefits
     */
    public function getBenefits()
    {
        return Cache::remember(self::CACHE_PREFIX . 'benefits', self::CACHE_TTL, function () {
            return ThemeBenefit::getActive();
        });
    }

    /**
     * Get features
     */
    public function getFeatures()
    {
        return Cache::remember(self::CACHE_PREFIX . 'features', self::CACHE_TTL, function () {
            return ThemeFeature::getActive();
        });
    }

    /**
     * Get use cases
     */
    public function getUseCases()
    {
        return Cache::remember(self::CACHE_PREFIX . 'use_cases', self::CACHE_TTL, function () {
            return ThemeUseCase::getActive();
        });
    }

    /**
     * Get pricing
     */
    public function getPricing()
    {
        return Cache::remember(self::CACHE_PREFIX . 'pricing', self::CACHE_TTL, function () {
            return ThemePricing::getActive();
        });
    }

    /**
     * Get FAQs
     */
    public function getFaqs()
    {
        return Cache::remember(self::CACHE_PREFIX . 'faqs', self::CACHE_TTL, function () {
            return ThemeFaq::getActive();
        });
    }

    /**
     * Get footer
     */
    public function getFooter()
    {
        return Cache::remember(self::CACHE_PREFIX . 'footer', self::CACHE_TTL, function () {
            return ThemeFooter::getActive();
        });
    }

    /**
     * Clear all theme cache
     */
    public function clearCache(): void
    {
        $keys = ['all', 'settings', 'hero', 'stats', 'benefits', 'features', 'use_cases', 'pricing', 'faqs', 'footer'];
        foreach ($keys as $key) {
            Cache::forget(self::CACHE_PREFIX . $key);
        }
    }

    /**
     * Clear specific theme cache
     */
    public function clearCacheFor(string $key): void
    {
        Cache::forget(self::CACHE_PREFIX . $key);
        Cache::forget(self::CACHE_PREFIX . 'all');
    }

    /**
     * Get data for deferred loading (heavy sections)
     */
    public function getDeferredData(): array
    {
        return [
            'features' => $this->getFeatures(),
            'useCases' => $this->getUseCases(),
            'pricing' => $this->getPricing(),
            'faqs' => $this->getFaqs(),
        ];
    }

    /**
     * Get data for immediate loading (above the fold)
     */
    public function getImmediateData(): array
    {
        return [
            'settings' => $this->getSettings(),
            'hero' => $this->getHero(),
            'stats' => $this->getStats(),
            'benefits' => $this->getBenefits(),
            'footer' => $this->getFooter(),
        ];
    }
}
