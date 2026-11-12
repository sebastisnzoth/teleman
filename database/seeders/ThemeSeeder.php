<?php

namespace Database\Seeders;

use App\Models\ThemeBenefit;
use App\Models\ThemeFaq;
use App\Models\ThemeFeature;
use App\Models\ThemeFooter;
use App\Models\ThemeHero;
use App\Models\ThemePricing;
use App\Models\ThemeSetting;
use App\Models\ThemeStat;
use App\Models\ThemeUseCase;
use Illuminate\Database\Seeder;

class ThemeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Re-running (an interrupted install, a second db:seed) must not
        // duplicate the landing page; an existing theme is left as it is.
        if (ThemeSetting::query()->exists()) {
            return;
        }

        // Theme Settings
        ThemeSetting::create([
            'site_name' => 'Teleman AI',
            'site_tagline' => 'Voice Calling Reimagined',
            'logo_path' => null,
            'primary_color' => '#000000',
            'secondary_color' => '#ffffff',
            'can_register' => true,
            'trust_badges' => [
                ['icon' => 'CheckCircle', 'text' => 'No subscription', 'color' => 'green'],
                ['icon' => 'Coins', 'text' => 'Pay as you go', 'color' => 'yellow'],
                ['icon' => 'Zap', 'text' => 'Instant activation', 'color' => 'blue'],
            ],
            'social_links' => [
                ['platform' => 'twitter', 'url' => '#', 'icon' => 'Twitter'],
                ['platform' => 'linkedin', 'url' => '#', 'icon' => 'Linkedin'],
                ['platform' => 'github', 'url' => '#', 'icon' => 'Github'],
                ['platform' => 'mail', 'url' => '#', 'icon' => 'Mail'],
            ],
            'legal_links' => [
                ['name' => 'Privacy Policy', 'url' => '/page/privacy-policy'],
                ['name' => 'Terms & Conditions', 'url' => '/page/terms-and-conditions'],
            ],
            'copyright_text' => '© 2025 Teleman AI. All rights reserved.',
        ]);

        // Hero Section
        ThemeHero::create([
            'badge_text' => 'Pay As You Go • Credit Based System',
            'badge_icon_left' => 'Sparkles',
            'badge_icon_right' => 'Coins',
            'typewriter_text' => 'Voice Calling Reimagined',
            'subtitle' => 'Launch automated voice campaigns in minutes. Pay only for what you use with our flexible credit system',
            'subtitle_emoji' => '💳',
            'primary_cta_text' => 'Get Started',
            'primary_cta_icon' => 'Coins',
            'secondary_cta_text' => 'How It Works',
            'secondary_cta_icon' => 'Zap',
            'background_gradient' => 'from-gray-900 via-gray-800 to-black',
            'is_active' => true,
        ]);

        // Stats
        $stats = [
            ['number' => '10M+', 'label' => 'Calls Delivered', 'icon' => 'PhoneCall', 'order' => 1],
            ['number' => '99.9%', 'label' => 'Uptime SLA', 'icon' => 'Activity', 'order' => 2],
            ['number' => '40+', 'label' => 'Languages', 'icon' => 'Globe', 'order' => 3],
            ['number' => '<100ms', 'label' => 'Latency', 'icon' => 'Zap', 'order' => 4],
        ];

        foreach ($stats as $stat) {
            ThemeStat::create(array_merge($stat, ['is_active' => true]));
        }

        // Benefits
        $benefits = [
            [
                'icon' => 'Coins',
                'title' => 'Pay As You Go',
                'description' => 'Only pay for what you use',
                'order' => 1,
            ],
            [
                'icon' => 'Zap',
                'title' => 'Instant Activation',
                'description' => 'Credits available immediately',
                'order' => 2,
            ],
            [
                'icon' => 'Percent',
                'title' => 'Volume Discounts',
                'description' => 'Better rates for bulk purchases',
                'order' => 3,
            ],
            [
                'icon' => 'CheckCircle2',
                'title' => 'No Expiration',
                'description' => 'Credits never expire',
                'order' => 4,
            ],
        ];

        foreach ($benefits as $benefit) {
            ThemeBenefit::create(array_merge($benefit, ['is_active' => true]));
        }

        // Features
        $features = [
            [
                'icon' => 'Bot',
                'title' => 'AI Voice Campaigns',
                'description' => 'Deploy automated voice calling campaigns at scale with natural-sounding AI voices in 40+ languages.',
                'gradient' => 'from-gray-900 via-gray-800 to-gray-700',
                'icon_bg' => 'from-gray-900 to-gray-700',
                'order' => 1,
            ],
            [
                'icon' => 'Workflow',
                'title' => 'Interactive DTMF',
                'description' => 'Enable two-way communication with keypad responses for surveys, confirmations, and smart routing.',
                'gradient' => 'from-gray-800 via-gray-700 to-gray-600',
                'icon_bg' => 'from-gray-800 to-gray-600',
                'order' => 2,
            ],
            [
                'icon' => 'LineChart',
                'title' => 'Real-Time Analytics',
                'description' => 'Monitor campaign performance live with detailed insights on delivery rates and call outcomes.',
                'gradient' => 'from-gray-700 via-gray-600 to-gray-500',
                'icon_bg' => 'from-gray-700 to-gray-500',
                'order' => 3,
            ],
            [
                'icon' => 'Users',
                'title' => 'Contact Management',
                'description' => 'Import, organize, and segment thousands of contacts instantly with CSV upload support.',
                'gradient' => 'from-gray-900 via-gray-800 to-gray-700',
                'icon_bg' => 'from-gray-900 to-gray-700',
                'order' => 4,
            ],
            [
                'icon' => 'Calendar',
                'title' => 'Smart Scheduling',
                'description' => 'Schedule campaigns with timezone-aware delivery windows for maximum engagement rates.',
                'gradient' => 'from-gray-800 via-gray-700 to-gray-600',
                'icon_bg' => 'from-gray-800 to-gray-600',
                'order' => 5,
            ],
            [
                'icon' => 'Lock',
                'title' => 'Enterprise Security',
                'description' => 'Bank-level encryption with GDPR and TCPA compliance built-in for complete data protection.',
                'gradient' => 'from-gray-700 via-gray-600 to-gray-500',
                'icon_bg' => 'from-gray-700 to-gray-500',
                'order' => 6,
            ],
        ];

        foreach ($features as $feature) {
            ThemeFeature::create(array_merge($feature, ['is_active' => true]));
        }

        // Use Cases
        $useCases = [
            [
                'icon' => 'Target',
                'title' => 'Lead Qualification',
                'description' => 'Score and qualify leads automatically with intelligent call routing',
                'items' => [
                    'Automated lead scoring',
                    'Real-time qualification',
                    'CRM integration ready',
                    'Smart follow-ups',
                ],
                'color' => 'from-gray-900 to-gray-700',
                'order' => 1,
            ],
            [
                'icon' => 'Radio',
                'title' => 'Voice Broadcasts',
                'description' => 'Reach thousands instantly with mass notification campaigns',
                'items' => [
                    'Mass notifications',
                    'Emergency alerts',
                    'Promotional campaigns',
                    'Event reminders',
                ],
                'color' => 'from-gray-800 to-gray-600',
                'order' => 2,
            ],
            [
                'icon' => 'FileAudio',
                'title' => 'Surveys & Feedback',
                'description' => 'Collect valuable insights through automated voice surveys',
                'items' => [
                    'Customer satisfaction',
                    'Product feedback',
                    'Market research',
                    'NPS tracking',
                ],
                'color' => 'from-gray-700 to-gray-500',
                'order' => 3,
            ],
        ];

        foreach ($useCases as $useCase) {
            ThemeUseCase::create(array_merge($useCase, ['is_active' => true]));
        }

        // Pricing
        $pricing = [
            [
                'credits' => '100',
                'price' => '$10',
                'per_credit' => '$0.10',
                'popular' => false,
                'icon' => 'Coins',
                'savings' => null,
                'order' => 1,
            ],
            [
                'credits' => '500',
                'price' => '$45',
                'per_credit' => '$0.09',
                'popular' => true,
                'icon' => 'CreditCard',
                'savings' => '10% off',
                'order' => 2,
            ],
            [
                'credits' => '1,000',
                'price' => '$80',
                'per_credit' => '$0.08',
                'popular' => false,
                'icon' => 'Award',
                'savings' => '20% off',
                'order' => 3,
            ],
            [
                'credits' => '5,000+',
                'price' => 'Custom',
                'per_credit' => 'Best Rate',
                'popular' => false,
                'icon' => 'Sparkles',
                'savings' => 'Volume Discount',
                'order' => 4,
            ],
        ];

        foreach ($pricing as $price) {
            ThemePricing::create(array_merge($price, ['is_active' => true]));
        }

        // FAQs
        $faqs = [
            [
                'question' => 'How do voice credits work?',
                'answer' => 'Each successful call consumes 1 credit. Failed calls or busy signals don\'t count. Buy credits as you need them with our flexible pay-as-you-go model.',
                'icon' => 'DollarSign',
                'order' => 1,
            ],
            [
                'question' => 'Can I use my own phone numbers?',
                'answer' => 'Yes! You can use your existing Twilio numbers or purchase new ones directly through our platform.',
                'icon' => 'Phone',
                'order' => 2,
            ],
            [
                'question' => 'What languages are supported?',
                'answer' => 'We support 40+ languages with natural-sounding AI voices, including English, Spanish, French, German, and many more.',
                'icon' => 'Globe',
                'order' => 3,
            ],
            [
                'question' => 'Do credits expire?',
                'answer' => 'No! Your credits never expire. Buy them once and use them whenever you need them.',
                'icon' => 'Star',
                'order' => 4,
            ],
        ];

        foreach ($faqs as $faq) {
            ThemeFaq::create(array_merge($faq, ['is_active' => true]));
        }

        // Footer/CTA Section
        ThemeFooter::create([
            'cta_badge_text' => 'Join 10,000+ businesses',
            'cta_badge_icon' => 'Rocket',
            'cta_headline' => 'Ready to Transform Your Voice Campaigns?',
            'cta_description' => 'Start making calls today with our flexible pay-as-you-go credit system. No subscriptions, no commitments.',
            'cta_primary_text' => 'Buy Credits',
            'cta_primary_icon' => 'Coins',
            'cta_secondary_text' => 'Sign In',
            'trust_indicators' => [
                ['icon' => 'Shield', 'text' => 'Enterprise Security'],
                ['icon' => 'Star', 'text' => '99.9% Uptime'],
                ['icon' => 'Headphones', 'text' => '24/7 Support'],
            ],
            'background_gradient' => 'from-gray-900 via-gray-800 to-black',
            'is_active' => true,
        ]);
    }
}
