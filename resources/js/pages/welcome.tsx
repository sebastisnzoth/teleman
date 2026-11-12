import { dashboard, login, register } from '@/routes';
import { useTranslation } from 'react-i18next';
import { type SharedData } from '@/types';
import { BrandLogo } from '@/components/brand-logo';
import { Head, Link, usePage } from '@inertiajs/react';
import React, { useState, useEffect } from 'react';
import {
  Phone,
  BarChart3,
  Zap,
  Users,
  MessageSquare,
  CheckCircle,
  ArrowRight,
  Clock,
  Shield,
  Sparkles,
  TrendingUp,
  FileAudio,
  Globe,
  ChevronDown,
  Star,
  Cpu,
  Radio,
  Activity,
  Target,
  Settings,
  Database,
  Mic2,
  HeadphonesIcon,
  Rocket,
  Layers,
  TrendingDown,
  Award,
  Lock,
  Bell,
  Calendar,
  DollarSign,
  BarChart,
  Send,
  CheckCircle2,
  ArrowUpRight,
  PlayCircle,
  Headphones,
  Volume2,
  PhoneCall,
  Bot,
  Workflow,
  LineChart,
  Filter,
  Gauge,
  Mail,
  Twitter,
  Linkedin,
  Github,
  Waves,
  Circle,
  Square,
  Triangle,
  Hash,
  Music,
  Mic,
  Signal,
  Wifi,
  Coins,
  CreditCard,
  Percent
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { NetworkBackground } from '@/components/ui/3d/network-background';
import { ActivityGlobe } from '@/components/ui/3d/activity-globe';
import { AudioWave } from '@/components/ui/3d/audio-wave';
import { WarpTunnel } from '@/components/ui/3d/warp-tunnel';

// Dynamic icon mapping — curated map avoids importing the entire lucide-react library
const IconMap: Record<string, React.ComponentType<any>> = {
  Phone, BarChart3, Zap, Users, MessageSquare, CheckCircle, ArrowRight, Clock,
  Shield, Sparkles, TrendingUp, FileAudio, Globe, ChevronDown, Star, Cpu, Radio,
  Activity, Target, Settings, Database, Mic2, HeadphonesIcon, Rocket, Layers,
  TrendingDown, Award, Lock, Bell, Calendar, DollarSign, BarChart, Send,
  CheckCircle2, ArrowUpRight, PlayCircle, Headphones, Volume2, PhoneCall, Bot,
  Workflow, LineChart, Filter, Gauge, Mail, Twitter, Linkedin, Github, Waves,
  Circle, Square, Triangle, Hash, Music, Mic, Signal, Wifi, Coins, CreditCard,
  Percent,
};

// Type definitions for theme data
interface ThemeSettings {
    site_name: string;
    site_tagline: string;
    logo_path?: string;
    logo_url?: string;
    primary_color: string;
    secondary_color: string;
    can_register: boolean;
    trust_badges?: Array<{icon: string; text: string; color: string}>;
    social_links?: Array<{platform: string; url: string; icon: string}>;
    legal_links?: Array<{text: string; url: string}>;
    copyright_text: string;
}

interface ThemeHero {
    badge_text: string;
    badge_icon_left: string;
    badge_icon_right: string;
    typewriter_text: string;
    subtitle: string;
    subtitle_emoji?: string;
    primary_cta_text: string;
    primary_cta_icon: string;
    secondary_cta_text: string;
    secondary_cta_icon: string;
    background_gradient: string;
}

interface ThemeStat {
    number: string;
    label: string;
    icon: string;
}

interface ThemeFeature {
    icon: string;
    title: string;
    description: string;
    gradient: string;
    icon_bg: string;
}

interface ThemeBenefit {
    icon: string;
    title: string;
    description: string;
}

interface ThemeUseCase {
    icon: string;
    title: string;
    description: string;
    items: string[];
    color: string;
}

interface ThemePricing {
    credits: string;
    price: string;
    per_credit: string;
    popular: boolean;
    icon: string;
    savings?: string;
}

interface ThemeFaq {
    question: string;
    answer: string;
    icon: string;
}

interface ThemeFooter {
    cta_badge_text: string;
    cta_badge_icon: string;
    cta_headline: string;
    cta_description: string;
    cta_primary_text: string;
    cta_primary_icon: string;
    cta_secondary_text: string;
    trust_indicators?: Array<{icon: string; text: string}>;
    background_gradient: string;
}

interface WelcomeProps {
    canRegister?: boolean;
    settings: ThemeSettings;
    hero: ThemeHero;
    stats: ThemeStat[];
    benefits: ThemeBenefit[];
    footer: ThemeFooter;
    features?: ThemeFeature[];
    useCases?: ThemeUseCase[];
    pricing?: ThemePricing[];
    faqs?: ThemeFaq[];
}

// Helper to get Lucide icon component by name
const getIcon = (iconName: string) => {
    return IconMap[iconName] || Circle;
};

export default function Welcome({
    canRegister = true,
    settings,
    hero,
    stats,
    benefits,
    footer,
    features = [],
    useCases = [],
    pricing = [],
    faqs = [],
}: WelcomeProps) {
    const { t } = useTranslation();
    const { auth } = usePage<SharedData>().props;
    const [mounted, setMounted] = useState(false);
    const [typedText, setTypedText] = useState('');
    const fullText = hero?.typewriter_text || 'Voice Calling Reimagined';
    const [showCursor, setShowCursor] = useState(true);

    useEffect(() => {
        setMounted(true);

        // Typewriter effect
        let currentIndex = 0;
        const typingInterval = setInterval(() => {
            if (currentIndex <= fullText.length) {
                setTypedText(fullText.substring(0, currentIndex));
                currentIndex++;
            } else {
                clearInterval(typingInterval);
            }
        }, 80);

        // Cursor blinking
        const cursorInterval = setInterval(() => {
            setShowCursor(prev => !prev);
        }, 500);

        return () => {
            clearInterval(typingInterval);
            clearInterval(cursorInterval);
        };
    }, [fullText]);

    return (
        <>
            <Head title={settings?.meta_title || `${settings?.site_name || 'Teleman AI'} - ${settings?.site_tagline || t('welcome.teleman_ai_aipowered_voice_calling_platform')}`}>
                <meta name="description" content={settings?.meta_description || 'Transform your telemarketing with AI-powered voice calling'} />
                {settings?.meta_keywords && <meta name="keywords" content={settings.meta_keywords} />}

                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link
                    href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800,900"
                    rel="stylesheet"
                />
            </Head>

            <div className="min-h-screen bg-white overflow-hidden">

                {/* Clean White Navigation */}
                <nav className="fixed top-4 left-1/2 -translate-x-1/2 z-50 w-[95%] max-w-4xl animate-slideDown px-4">
                    <div className="bg-white rounded-2xl shadow-lg border border-gray-100 px-5 md:px-6 py-2.5">
                        <div className="flex items-center justify-between">
                            {/* Logo */}
                            <Link href="/" className="flex items-center gap-2 group cursor-pointer">
                                <BrandLogo
                                    surface="landing"
                                    themeSwap={false}
                                    textClassName="text-base md:text-lg font-bold text-gray-900 tracking-tight"
                                    fallback={
                                        <div className="w-8 h-8 bg-black rounded-lg flex items-center justify-center transition-all duration-300 group-hover:scale-105 group-hover:rotate-3">
                                            <Phone className="w-4 h-4 text-white" />
                                        </div>
                                    }
                                />
                            </Link>

                            {/* Navigation Links */}
                            <div className="flex items-center gap-2">
                                {auth.user ? (
                                    <Button
                                        asChild
                                        size="sm"
                                        className="bg-black hover:bg-gray-800 text-white font-medium transition-all duration-300 group rounded-lg px-4 h-8"
                                    >
                                        <Link href={dashboard()}>
                                            <span className="hidden sm:inline">{t('welcome.nav.dashboard')}</span>
                                            <span className="sm:hidden">{t('welcome.nav.panel')}</span>
                                            <ArrowRight className="ml-1.5 w-3.5 h-3.5 transition-transform duration-300 group-hover:translate-x-0.5" />
                                        </Link>
                                    </Button>
                                ) : (
                                    <>
                                        <Button
                                            asChild
                                            variant="ghost"
                                            size="sm"
                                            className="text-gray-600 hover:text-gray-900 hover:bg-gray-50 font-medium transition-all duration-200 rounded-lg px-3 h-8"
                                        >
                                            <Link href={login()}>
                                                {t('welcome.nav.login')}
                                            </Link>
                                        </Button>
                                        {canRegister && (
                                            <Button
                                                asChild
                                                size="sm"
                                                className="bg-black hover:bg-gray-800 text-white font-medium group transition-all duration-300 rounded-lg px-4 h-8"
                                            >
                                                <Link href={register()}>
                                                    <span className="hidden sm:inline">{t('welcome.nav.get_started')}</span>
                                                    <span className="sm:hidden">{t('welcome.nav.start')}</span>
                                                    <ArrowRight className="ml-1.5 w-3.5 h-3.5 transition-transform duration-300 group-hover:translate-x-0.5" />
                                                </Link>
                                            </Button>
                                        )}
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                </nav>

                {/* Hero Section */}
                <section className="relative pt-24 pb-20 md:pt-32 md:pb-24 overflow-hidden min-h-screen flex items-center bg-gradient-to-br from-gray-900 via-gray-800 to-black text-white">
                    <div className="absolute inset-0 z-0">
                        <NetworkBackground className="opacity-60" />
                        <div className="absolute inset-0 bg-[linear-gradient(to_right,#ffffff08_1px,transparent_1px),linear-gradient(to_bottom,#ffffff08_1px,transparent_1px)] bg-[size:64px_64px] opacity-20" />
                        <div className="absolute top-0 left-0 w-96 h-96 bg-white rounded-full blur-3xl opacity-5 animate-blob" />
                        <div className="absolute bottom-0 right-0 w-96 h-96 bg-white rounded-full blur-3xl opacity-5 animate-blob animation-delay-2000" />
                        <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[500px] h-[500px] bg-white rounded-full blur-3xl opacity-[0.03] animate-pulse-slow" />
                    </div>

                    <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
                        <div className="text-center space-y-8">
                            {/* Badge */}
                            <div className={`flex justify-center ${mounted ? 'animate-slideDown' : 'opacity-0'}`}>
                                <Badge
                                    variant="outline"
                                    className="px-5 py-2.5 text-xs font-bold border-2 border-white/20 hover:border-white/40 transition-all duration-300 hover:shadow-lg group cursor-default bg-white/10 backdrop-blur-sm"
                                >
                                    <div className="flex items-center gap-2">
                                        <div className="w-2 h-2 bg-green-400 rounded-full animate-pulse shadow-lg shadow-green-400/50" />
                                        {React.createElement(getIcon(hero?.badge_icon_left || 'Sparkles'), { className: "w-3.5 h-3.5 group-hover:rotate-12 transition-transform duration-300 text-white" })}
                                        <span className="tracking-wide text-white">{hero?.badge_text || 'Pay As You Go • Credit Based System'}</span>
                                        {React.createElement(getIcon(hero?.badge_icon_right || 'Coins'), { className: "w-3.5 h-3.5 group-hover:scale-110 transition-transform duration-300 text-yellow-400" })}
                                    </div>
                                </Badge>
                            </div>

                            {/* Typewriter Headline */}
                            <div className={`space-y-4 ${mounted ? 'animate-slideUp' : 'opacity-0'}`}>
                                <h1 className="text-5xl sm:text-6xl md:text-7xl lg:text-8xl font-black text-white tracking-tight leading-tight relative">
                                    <span className="block">
                                        {typedText}
                                        {(typedText.length < fullText.length || showCursor) && (
                                            <span className="inline-block w-1 h-16 md:h-20 lg:h-24 bg-white ml-2 align-middle" />
                                        )}
                                    </span>
                                </h1>
                                <div className="flex justify-center pt-2">
                                    <div className="w-32 h-1.5 bg-gradient-to-r from-transparent via-white to-transparent animate-shimmer rounded-full" />
                                </div>
                            </div>

                            {/* Subtitle */}
                            <div className={`${mounted ? 'animate-fadeIn animation-delay-200' : 'opacity-0'}`}>
                                <p className="text-lg sm:text-xl md:text-2xl text-gray-300 max-w-4xl mx-auto leading-relaxed font-light">
                                    {hero?.subtitle || 'Launch automated voice campaigns in minutes. Pay only for what you use with our flexible credit system'}
                                    {hero?.subtitle_emoji && <span className="inline-block ml-2 text-2xl">{hero.subtitle_emoji}</span>}
                                </p>
                            </div>

                            {/* CTA Buttons */}
                            <div className={`flex flex-col sm:flex-row items-center justify-center gap-4 pt-2 ${mounted ? 'animate-slideUp animation-delay-400' : 'opacity-0'}`}>
                                {!auth.user && settings?.can_register && (
                                    <>
                                        <Button
                                            asChild
                                            size="lg"
                                            className="relative bg-white hover:bg-gray-100 text-gray-900 font-bold px-8 py-6 text-base group shadow-2xl hover:shadow-3xl hover:scale-105 transition-all duration-500 overflow-hidden rounded-xl"
                                        >
                                            <Link href={register()}>
                                                <div className="absolute inset-0 bg-gradient-to-r from-gray-900/0 via-gray-900/10 to-gray-900/0 animate-shimmer-button" />
                                                {React.createElement(getIcon(hero?.primary_cta_icon || 'Coins'), { className: "w-5 h-5 mr-2 group-hover:rotate-12 transition-transform duration-300" })}
                                                <span className="relative z-10">{hero?.primary_cta_text || t('welcome.nav.get_started')}</span>
                                                <ArrowRight className="ml-2 w-5 h-5 transition-transform group-hover:translate-x-2" />
                                            </Link>
                                        </Button>
                                        <Button
                                            asChild
                                            size="lg"
                                            variant="outline"
                                            className="px-8 py-6 text-base font-semibold border-2 border-white/30 hover:border-white bg-transparent hover:bg-white/10 transition-all duration-300 hover:scale-105 group rounded-xl text-white hover:text-white"
                                        >
                                            <Link href="#faq">
                                                {React.createElement(getIcon(hero?.secondary_cta_icon || 'Zap'), { className: "w-5 h-5 mr-2 group-hover:scale-110 transition-transform duration-300 text-white" })}
                                                {hero?.secondary_cta_text || 'How It Works'}
                                                <ChevronDown className="ml-2 w-5 h-5 animate-bounce text-white" />
                                            </Link>
                                        </Button>
                                    </>
                                )}
                                {auth.user && (
                                    <Button asChild size="lg" className="bg-white hover:bg-gray-100 text-gray-900 font-bold px-8 py-6 text-base group shadow-2xl hover:scale-105 transition-all duration-300 rounded-xl">
                                        <Link href={dashboard()}>
                                            <BarChart className="w-5 h-5 mr-2" />
                                            {t('welcome.cta.go_to_dashboard')}
                                            <ArrowRight className="ml-2 w-5 h-5 transition-transform group-hover:translate-x-2" />
                                        </Link>
                                    </Button>
                                )}
                            </div>

                            {/* Trust Badges */}
                            <div className={`pt-6 flex flex-wrap items-center justify-center gap-4 md:gap-6 text-xs md:text-sm ${mounted ? 'animate-fadeIn animation-delay-600' : 'opacity-0'}`}>
                                {settings?.trust_badges?.map((badge, index) => {
                                    const colorMap: Record<string, string> = {
                                        green: 'bg-green-500/20 border-green-500/30 text-green-400',
                                        yellow: 'bg-yellow-500/20 border-yellow-500/30 text-yellow-400',
                                        blue: 'bg-blue-500/20 border-blue-500/30 text-blue-400',
                                    };
                                    const colorClass = colorMap[badge.color] || 'bg-gray-500/20 border-gray-500/30 text-gray-400';
                                    return (
                                        <div key={index} className="flex items-center gap-2 group hover:scale-110 transition-all duration-300 cursor-default">
                                            <div className={`w-6 h-6 md:w-7 md:h-7 ${colorClass.split(' ')[0]} rounded-lg flex items-center justify-center group-hover:rotate-12 transition-transform duration-300 border ${colorClass.split(' ')[1]}`}>
                                                {React.createElement(getIcon(badge.icon), { className: `w-3.5 h-3.5 md:w-4 md:h-4 ${colorClass.split(' ')[2]}` })}
                                            </div>
                                            <span className="font-semibold text-gray-200">{badge.text}</span>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </div>
                </section>

                {/* Stats Section */}
                <section className="py-20 bg-gradient-to-br from-gray-900 via-gray-800 to-black text-white relative overflow-hidden">
                    <div className="absolute right-0 top-0 h-full w-full md:w-1/2 opacity-30 pointer-events-none">
                        <ActivityGlobe />
                    </div>
                    <div className="absolute inset-0 bg-[linear-gradient(to_right,#ffffff08_1px,transparent_1px),linear-gradient(to_bottom,#ffffff08_1px,transparent_1px)] bg-[size:64px_64px]" />
                    <div className="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-transparent via-white to-transparent opacity-20" />

                    <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-8 md:gap-12">
                            {stats?.map((stat, index) => {
                                const Icon = getIcon(stat.icon);
                                return (
                                    <div
                                        key={index}
                                        className="text-center space-y-3 md:space-y-4 group cursor-default animate-fadeIn hover:scale-110 transition-all duration-500"
                                        style={{ animationDelay: `${index * 100}ms` }}
                                    >
                                        <div className="flex justify-center">
                                            <div className="w-12 h-12 md:w-16 md:h-16 bg-white/10 rounded-2xl flex items-center justify-center group-hover:bg-white/20 transition-all duration-300 group-hover:rotate-12">
                                                <Icon className="w-6 h-6 md:w-8 md:h-8 text-gray-300 group-hover:text-white transition-colors duration-300" />
                                            </div>
                                        </div>
                                        <div className="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-black tracking-tight group-hover:scale-110 transition-transform duration-300">{stat.number}</div>
                                        <div className="text-xs md:text-sm text-gray-400 font-semibold uppercase tracking-wider">{stat.label}</div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </section>

                {/* Benefits Section */}
                <section className="py-16 md:py-20 bg-gradient-to-br from-gray-50 via-white to-gray-50">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6 md:gap-8">
                            {benefits?.map((benefit, index) => {
                                const Icon = getIcon(benefit.icon);
                                return (
                                    <div
                                        key={index}
                                        className="relative text-center space-y-3 md:space-y-4 p-5 md:p-6 rounded-2xl bg-white/60 backdrop-blur-xl border border-white/50 shadow-lg shadow-gray-900/5 hover:shadow-2xl hover:shadow-gray-900/10 hover:bg-white/80 transition-all duration-500 group cursor-default animate-fadeIn"
                                        style={{ animationDelay: `${index * 100}ms` }}
                                    >
                                        <div className="absolute inset-0 rounded-2xl bg-gradient-to-br from-white/60 via-white/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none" />
                                        <div className="relative flex justify-center">
                                            <div className="w-12 h-12 md:w-14 md:h-14 bg-gradient-to-br from-gray-900 to-gray-700 rounded-2xl flex items-center justify-center group-hover:scale-110 group-hover:rotate-12 transition-all duration-500 shadow-lg group-hover:shadow-2xl">
                                                <Icon className="w-6 h-6 md:w-7 md:h-7 text-white" />
                                            </div>
                                        </div>
                                        <h3 className="relative text-xl md:text-2xl font-black text-gray-900">{benefit.title}</h3>
                                        <p className="relative text-sm md:text-base text-gray-600 font-medium">{benefit.description}</p>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </section>

                {/* Features Section */}
                <section id="features" className="py-32 bg-gradient-to-b from-gray-50 to-white relative overflow-hidden">
                    <AudioWave className="opacity-40" />

                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
                        <div className="text-center space-y-6 mb-20">
                            <Badge variant="outline" className="px-6 py-3 text-sm font-semibold border-gray-200 hover:border-gray-900 transition-all duration-300 group">
                                <Cpu className="w-4 h-4 mr-2 group-hover:rotate-12 transition-transform duration-300" />
                                {t('welcome.features.badge')}
                            </Badge>
                            <h2 className="text-5xl md:text-6xl font-black text-gray-900 tracking-tight">
                                {t('welcome.features.heading')}
                            </h2>
                            <p className="text-xl md:text-2xl text-gray-600 max-w-3xl mx-auto font-light leading-relaxed">
                                {t('welcome.features.subheading')}
                            </p>
                        </div>

                        <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                            {features?.map((feature, index) => {
                                const Icon = getIcon(feature.icon);
                                return (
                                    <Card
                                        key={index}
                                        className="group hover:shadow-2xl transition-all duration-500 border-white/50 hover:border-gray-300 animate-fadeIn overflow-hidden relative hover:scale-105 cursor-default bg-white/50 backdrop-blur-xl"
                                        style={{ animationDelay: `${index * 100}ms` }}
                                    >
                                        <div className="absolute inset-0 bg-gradient-to-br from-white/60 via-white/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none" />
                                        <CardContent className="p-8 space-y-6 relative">
                                            <div className={`w-16 h-16 bg-gradient-to-br ${feature.icon_bg} rounded-2xl flex items-center justify-center transition-all duration-500 group-hover:scale-110 group-hover:rotate-12 shadow-lg group-hover:shadow-2xl`}>
                                                <Icon className="w-8 h-8 text-white" />
                                            </div>
                                            <h3 className="text-2xl font-black text-gray-900 group-hover:text-gray-800 transition-colors">{feature.title}</h3>
                                            <p className="text-gray-600 leading-relaxed font-medium">{feature.description}</p>
                                            <div className="flex items-center text-gray-900 font-semibold group-hover:translate-x-2 transition-transform duration-300">
                                                <span className="text-sm">{t('welcome.features.learn_more')}</span>
                                                <ArrowUpRight className="w-4 h-4 ml-2" />
                                            </div>
                                        </CardContent>
                                    </Card>
                                );
                            })}
                        </div>
                    </div>
                </section>

                {/* Use Cases Section */}
                <section className="py-32 bg-white relative overflow-hidden">
                    <div className="absolute top-0 right-0 w-1/3 h-full bg-gradient-to-l from-gray-50 to-transparent opacity-50" />

                    <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="text-center space-y-6 mb-20">
                            <Badge variant="outline" className="px-6 py-3 text-sm font-semibold border-gray-200 hover:border-gray-900 transition-all duration-300 bg-white group">
                                <Target className="w-4 h-4 mr-2 group-hover:rotate-12 transition-transform duration-300" />
                                {t('welcome.use_cases.badge')}
                            </Badge>
                            <h2 className="text-5xl md:text-6xl font-black text-gray-900 tracking-tight">
                                {t('welcome.use_cases.heading')}
                            </h2>
                            <p className="text-xl md:text-2xl text-gray-600 max-w-3xl mx-auto font-light leading-relaxed">
                                {t('welcome.use_cases.subheading')}
                            </p>
                        </div>

                        <div className="grid md:grid-cols-3 gap-8">
                            {useCases?.map((useCase, index) => {
                                const Icon = getIcon(useCase.icon);
                                return (
                                    <Card key={index} className="border-white/50 hover:shadow-2xl transition-all duration-500 animate-fadeIn group hover:scale-105 overflow-hidden bg-white/50 backdrop-blur-xl relative" style={{ animationDelay: `${index * 100}ms` }}>
                                        <div className="absolute inset-0 bg-gradient-to-br from-white/60 via-white/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none" />
                                        <CardContent className="p-8 space-y-6 relative">
                                            <div className={`w-20 h-20 bg-gradient-to-br ${useCase.color} rounded-2xl flex items-center justify-center group-hover:scale-110 group-hover:rotate-12 transition-all duration-500 shadow-xl`}>
                                                <Icon className="w-10 h-10 text-white" />
                                            </div>
                                            <div className="space-y-2">
                                                <h3 className="text-2xl font-black text-gray-900">{useCase.title}</h3>
                                                <p className="text-gray-600 font-medium">{useCase.description}</p>
                                            </div>
                                            <ul className="space-y-3">
                                                {useCase.items.map((item, i) => (
                                                    <li key={i} className="flex items-start space-x-3 group/item">
                                                        <div className="mt-0.5">
                                                            <CheckCircle2 className="w-5 h-5 text-gray-900 group-hover/item:scale-110 transition-transform duration-300" />
                                                        </div>
                                                        <span className="text-gray-700 font-medium">{item}</span>
                                                    </li>
                                                ))}
                                            </ul>
                                        </CardContent>
                                    </Card>
                                );
                            })}
                        </div>
                    </div>
                </section>

                {/* Credit Pricing Section */}
                <section className="py-32 bg-gradient-to-b from-gray-50 to-white">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="text-center space-y-6 mb-20">
                            <Badge variant="outline" className="px-6 py-3 text-sm font-semibold border-gray-200 hover:border-gray-900 transition-all duration-300 group">
                                <Coins className="w-4 h-4 mr-2 group-hover:rotate-12 transition-transform duration-300" />
                                {t('welcome.pricing.badge')}
                            </Badge>
                            <h2 className="text-5xl md:text-6xl font-black text-gray-900 tracking-tight">
                                {t('welcome.pricing.heading')}
                            </h2>
                            <p className="text-xl md:text-2xl text-gray-600 max-w-3xl mx-auto font-light leading-relaxed">
                                {t('welcome.pricing.subheading')}
                            </p>
                        </div>

                        <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                            {pricing?.map((pkg, index) => {
                                const Icon = getIcon(pkg.icon);
                                return (
                                <Card
                                    key={index}
                                    className={`relative overflow-hidden transition-all duration-500 animate-fadeIn group cursor-default ${
                                        pkg.popular
                                            ? 'border-gray-900 shadow-2xl scale-105 hover:scale-110 bg-gradient-to-br from-gray-900 via-gray-800 to-gray-700 text-white'
                                            : 'border-white/50 hover:shadow-xl hover:scale-105 hover:border-gray-300 bg-white/50 backdrop-blur-xl'
                                    }`}
                                    style={{ animationDelay: `${index * 100}ms` }}
                                >
                                    {pkg.popular && (
                                        <div className="absolute top-0 right-0">
                                            <div className="relative">
                                                <div className="absolute inset-0 bg-gradient-to-r from-yellow-400 to-yellow-500 blur-lg opacity-50" />
                                                <div className="relative bg-gradient-to-r from-yellow-400 to-yellow-500 px-4 py-1 text-xs font-black text-gray-900 flex items-center gap-1">
                                                    <Star className="w-3 h-3 fill-current" />
                                                    {t('welcome.pricing.popular')}
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                    {pkg.savings && !pkg.popular && (
                                        <div className="absolute top-2 right-2">
                                            <Badge variant="secondary" className="text-xs font-bold">
                                                {pkg.savings}
                                            </Badge>
                                        </div>
                                    )}
                                    {!pkg.popular && (
                                        <div className="absolute inset-0 bg-gradient-to-br from-white/60 via-white/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none" />
                                    )}
                                    <CardContent className="p-6 space-y-4 relative">
                                        <div className="flex justify-center">
                                            <div className={`w-16 h-16 ${pkg.popular ? 'bg-white/20' : 'bg-gray-100'} rounded-2xl flex items-center justify-center group-hover:scale-110 group-hover:rotate-12 transition-all duration-500`}>
                                                <Icon className={`w-8 h-8 ${pkg.popular ? 'text-white' : 'text-gray-900'}`} />
                                            </div>
                                        </div>

                                        <div className="text-center space-y-1">
                                            <div className={`text-4xl font-black ${pkg.popular ? 'text-white' : 'text-gray-900'}`}>{pkg.credits}</div>
                                            <div className={`text-xs uppercase tracking-wider font-bold ${pkg.popular ? 'text-gray-300' : 'text-gray-500'}`}>{t('welcome.pricing.credits_label')}</div>
                                        </div>

                                        <Separator className={pkg.popular ? 'bg-white/20' : ''} />

                                        <div className="text-center space-y-1">
                                            <div className={`text-3xl font-black ${pkg.popular ? 'text-white' : 'text-gray-900'}`}>{pkg.price}</div>
                                            <div className={`text-xs ${pkg.popular ? 'text-gray-300' : 'text-gray-600'} font-medium`}>{pkg.per_credit} {t('welcome.pricing.per_call')}</div>
                                        </div>

                                        <Button
                                            asChild
                                            size="lg"
                                            className={`w-full font-bold group/button relative overflow-hidden ${
                                                pkg.popular
                                                    ? 'bg-white hover:bg-gray-100 text-gray-900 shadow-xl'
                                                    : 'bg-gradient-to-r from-gray-900 to-gray-700 hover:from-gray-800 hover:to-gray-600 text-white'
                                            }`}
                                        >
                                            {canRegister && !auth.user ? (
                                                <Link href={register()}>
                                                    {t('welcome.pricing.buy_now')}
                                                    <ArrowRight className="ml-2 w-4 h-4 group-hover/button:translate-x-1 transition-transform duration-300" />
                                                </Link>
                                            ) : (
                                                <Link href={dashboard()}>
                                                    {t('welcome.pricing.buy_now')}
                                                    <ArrowRight className="ml-2 w-4 h-4 group-hover/button:translate-x-1 transition-transform duration-300" />
                                                </Link>
                                            )}
                                        </Button>
                                    </CardContent>
                                </Card>
                            )})}
                        </div>

                        <div className="mt-12 text-center">
                            <p className="text-gray-600 font-medium">
                                {t('welcome.pricing.contact_us')}
                            </p>
                        </div>
                    </div>
                </section>

                {/* FAQ Section */}
                <section id="faq" className="py-32 bg-white">
                    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="text-center space-y-6 mb-20">
                            <Badge variant="outline" className="px-6 py-3 text-sm font-semibold border-gray-200 hover:border-gray-900 transition-all duration-300 group">
                                <MessageSquare className="w-4 h-4 mr-2 group-hover:rotate-12 transition-transform duration-300" />
                                {t('welcome.faq.badge')}
                            </Badge>
                            <h2 className="text-5xl md:text-6xl font-black text-gray-900 tracking-tight">
                                {t('welcome.faq.heading')}
                            </h2>
                            <p className="text-xl md:text-2xl text-gray-600 font-light">
                                {t('welcome.faq.subheading')}
                            </p>
                        </div>

                        <div className="space-y-6">
                            {faqs?.map((faq, index) => {
                                const Icon = getIcon(faq.icon);
                                return (
                                    <Card key={index} className="border-white/50 hover:shadow-xl hover:border-gray-300 transition-all duration-500 animate-fadeIn group bg-white/50 backdrop-blur-xl relative overflow-hidden" style={{ animationDelay: `${index * 100}ms` }}>
                                        <div className="absolute inset-0 bg-gradient-to-br from-white/60 via-white/20 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none" />
                                        <CardContent className="p-8 space-y-4 relative">
                                            <div className="flex items-start gap-4">
                                                <div className="flex-shrink-0">
                                                    <div className="w-12 h-12 bg-gradient-to-br from-gray-900 to-gray-700 rounded-2xl flex items-center justify-center group-hover:scale-110 group-hover:rotate-12 transition-all duration-500 shadow-lg">
                                                        <Icon className="w-6 h-6 text-white" />
                                                    </div>
                                                </div>
                                                <div className="flex-1 space-y-3">
                                                    <h3 className="text-xl font-black text-gray-900">{faq.question}</h3>
                                                    <p className="text-gray-600 leading-relaxed font-medium">{faq.answer}</p>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                );
                            })}
                        </div>
                    </div>
                </section>

                {/* CTA Section */}
                <section className="py-32 bg-gradient-to-br from-gray-900 via-gray-800 to-black text-white relative overflow-hidden">
                    <WarpTunnel />
                    <div className="absolute inset-0 pointer-events-none">
                        <div className="absolute inset-0 bg-[linear-gradient(to_right,#ffffff08_1px,transparent_1px),linear-gradient(to_bottom,#ffffff08_1px,transparent_1px)] bg-[size:64px_64px]" />
                        <div className="absolute top-0 left-0 w-96 h-96 bg-white rounded-full blur-3xl opacity-5 animate-blob" />
                        <div className="absolute bottom-0 right-0 w-96 h-96 bg-white rounded-full blur-3xl opacity-5 animate-blob animation-delay-2000" />
                    </div>

                    <div className="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-10">
                        <div className="relative inline-flex items-center gap-2 px-6 py-3 bg-white/10 rounded-full backdrop-blur-xl border border-white/20 shadow-lg shadow-gray-900/50 hover:bg-white/20 transition-all duration-300 group">
                            <div className="absolute inset-0 rounded-full bg-gradient-to-br from-white/20 via-white/5 to-transparent pointer-events-none" />
                            {React.createElement(getIcon(footer?.cta_badge_icon || 'Rocket'), {
                                className: "relative w-5 h-5 group-hover:rotate-12 transition-transform duration-300"
                            })}
                            <span className="relative font-semibold">{footer?.cta_badge_text || 'Join 10,000+ businesses'}</span>
                        </div>

                        <h2 className="text-5xl md:text-6xl lg:text-7xl font-black tracking-tight leading-none animate-slideUp">
                            {footer?.cta_headline || 'Ready to Transform Your Voice Campaigns?'}
                        </h2>
                        <p className="text-xl md:text-2xl text-gray-300 max-w-3xl mx-auto font-light leading-relaxed animate-fadeIn">
                            {footer?.cta_description || 'Start making calls today with our flexible pay-as-you-go credit system. No subscriptions, no commitments.'}
                        </p>

                        <div className="flex flex-col sm:flex-row items-center justify-center gap-6 pt-4 animate-slideUp">
                            {!auth.user && canRegister ? (
                                <>
                                    <Button asChild size="lg" className="bg-white hover:bg-gray-100 text-gray-900 font-bold px-10 py-7 text-lg group shadow-2xl hover:scale-110 transition-all duration-300">
                                        <Link href={register()}>
                                            {React.createElement(getIcon(footer?.cta_primary_icon || 'Coins'), {
                                                className: "w-5 h-5 mr-2 group-hover:rotate-12 transition-transform duration-300"
                                            })}
                                            {footer?.cta_primary_text || 'Buy Credits'}
                                            <ArrowRight className="ml-2 w-5 h-5 transition-transform group-hover:translate-x-2" />
                                        </Link>
                                    </Button>
                                    <Button asChild size="lg" variant="outline" className="border-2 border-gray-600 hover:bg-white/10 text-white px-10 py-7 text-lg font-bold hover:scale-110 transition-all duration-300 bg-transparent">
                                        <Link href={login()}>
                                            {footer?.cta_secondary_text || t('welcome.nav.login')}
                                        </Link>
                                    </Button>
                                </>
                            ) : auth.user ? (
                                <Button asChild size="lg" className="bg-white hover:bg-gray-100 text-gray-900 font-bold px-10 py-7 text-lg group shadow-2xl hover:scale-110 transition-all duration-300">
                                    <Link href={dashboard()}>
                                        <BarChart className="w-5 h-5 mr-2" />
                                        {t('welcome.cta.go_to_dashboard')}
                                        <ArrowRight className="ml-2 w-5 h-5 transition-transform group-hover:translate-x-2" />
                                    </Link>
                                </Button>
                            ) : null}
                        </div>

                        {/* Trust Indicators */}
                        <div className="pt-8 flex flex-wrap items-center justify-center gap-8 text-sm text-gray-400 animate-fadeIn">
                            {footer?.trust_indicators?.map((indicator: any, index: number) => {
                                const Icon = getIcon(indicator.icon);
                                return (
                                    <div key={index} className="flex items-center gap-3 hover:text-white transition-colors duration-300 group">
                                        <div className="relative w-10 h-10 bg-white/10 backdrop-blur-xl rounded-xl flex items-center justify-center group-hover:scale-110 transition-transform duration-300 border border-white/10">
                                            <div className="absolute inset-0 rounded-xl bg-gradient-to-br from-white/20 via-white/5 to-transparent pointer-events-none" />
                                            <Icon className="w-5 h-5" />
                                        </div>
                                        <span className="font-semibold">{indicator.text}</span>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </section>

                {/* Footer */}
                <footer className="relative bg-black text-white py-16 overflow-hidden">
                    <div className="absolute inset-0 bg-[linear-gradient(to_right,#ffffff08_1px,transparent_1px),linear-gradient(to_bottom,#ffffff08_1px,transparent_1px)] bg-[size:32px_32px]" />

                    <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="text-center space-y-12">
                            <div className="flex justify-center">
                                <div className="inline-block space-y-3 md:space-y-4">
                                    <h2 className="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-black tracking-tighter relative">
                                        <span className="inline-block relative group cursor-default">
                                            <span className="text-transparent bg-clip-text bg-gradient-to-r from-gray-400 via-gray-200 to-gray-400 animate-gradient bg-[length:200%_auto] dotted-text uppercase">
                                                {settings?.site_name || 'TELEMAN AI'}
                                            </span>
                                            <div className="absolute inset-0 blur-2xl opacity-30 group-hover:opacity-50 transition-opacity duration-500">
                                                <span className="text-transparent bg-clip-text bg-gradient-to-r from-white via-gray-300 to-white uppercase">
                                                    {settings?.site_name || 'TELEMAN AI'}
                                                </span>
                                            </div>
                                        </span>
                                    </h2>
                                    <p className="text-gray-400 text-xs sm:text-sm md:text-base font-medium tracking-wider uppercase">
                                        {settings?.site_tagline || 'Voice Calling Reimagined'}
                                    </p>
                                </div>
                            </div>

                            <Separator className="bg-gray-800" />

                            <div className="flex flex-col md:flex-row justify-between items-center gap-6 md:gap-4">
                                <div className="text-xs md:text-sm text-gray-400 font-medium order-2 md:order-1">
                                    {settings?.copyright_text || t('welcome.footer.copyright')}
                                </div>

                                <div className="flex items-center gap-3 md:gap-4 order-1 md:order-2">
                                    {settings?.social_links?.map((social: any, index: number) => {
                                        const Icon = getIcon(social.icon);
                                        return (
                                            <a key={index} href={social.url} target="_blank" rel="noopener noreferrer" className="w-9 h-9 md:w-10 md:h-10 bg-gray-800 hover:bg-gray-700 rounded-xl flex items-center justify-center transition-all duration-300 hover:scale-110 group">
                                                <Icon className="w-4 h-4 md:w-5 md:h-5 text-gray-400 group-hover:text-white transition-colors" />
                                            </a>
                                        );
                                    })}
                                </div>

                                <div className="flex items-center gap-4 md:gap-6 text-xs md:text-sm text-gray-400 order-3">
                                    {settings?.legal_links?.map((link: any, index: number) => (
                                        <a key={index} href={link.url} className="hover:text-white transition-colors duration-300 font-medium">{link.name}</a>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="absolute bottom-0 left-0 w-64 h-64 bg-gradient-to-br from-gray-800 to-transparent rounded-full blur-3xl opacity-20 -translate-x-1/2 translate-y-1/2" />
                    <div className="absolute top-0 right-0 w-64 h-64 bg-gradient-to-bl from-gray-800 to-transparent rounded-full blur-3xl opacity-20 translate-x-1/2 -translate-y-1/2" />
                </footer>

            </div>

            {/* Custom Animations */}
            <style>{`
                html {
                    scroll-behavior: smooth;
                }

                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }

                @keyframes slideUp {
                    from { opacity: 0; transform: translateY(40px); }
                    to { opacity: 1; transform: translateY(0); }
                }

                @keyframes slideDown {
                    from { opacity: 0; transform: translateY(-40px); }
                    to { opacity: 1; transform: translateY(0); }
                }

                @keyframes blob {
                    0%, 100% { transform: translate(0, 0) scale(1); }
                    33% { transform: translate(30px, -50px) scale(1.1); }
                    66% { transform: translate(-20px, 20px) scale(0.9); }
                }

                @keyframes shimmer {
                    0% { transform: translateX(-100%); }
                    100% { transform: translateX(100%); }
                }

                @keyframes shimmer-button {
                    0% { transform: translateX(-100%) skewX(-15deg); }
                    100% { transform: translateX(200%) skewX(-15deg); }
                }

                @keyframes gradient {
                    0%, 100% { background-position: 0% 50%; }
                    50% { background-position: 100% 50%; }
                }

                @keyframes pulse-slow {
                    0%, 100% { opacity: 0.03; }
                    50% { opacity: 0.06; }
                }

                .animate-fadeIn { animation: fadeIn 1s ease-out forwards; }
                .animate-slideUp { animation: slideUp 1s ease-out forwards; }
                .animate-slideDown { animation: slideDown 1s ease-out forwards; }
                .animate-blob { animation: blob 7s infinite; }
                .animate-shimmer { animation: shimmer 3s infinite; }
                .animate-shimmer-button { animation: shimmer-button 3s infinite; }
                .animate-gradient { animation: gradient 8s linear infinite; }
                .animate-pulse-slow { animation: pulse-slow 4s ease-in-out infinite; }
                .animation-delay-200 { animation-delay: 200ms; }
                .animation-delay-400 { animation-delay: 400ms; }
                .animation-delay-600 { animation-delay: 600ms; }
                .animation-delay-2000 { animation-delay: 2000ms; }

                .dotted-text {
                    text-shadow:
                        0 0 1px currentColor,
                        0 0 2px currentColor,
                        0 0 4px currentColor,
                        0 0 8px currentColor;
                    letter-spacing: 0.1em;
                    font-weight: 900;
                    filter: blur(0.3px);
                }
            `}</style>
        </>
    );
}

// Missing import for Building icon - using a fallback
function Building({ className }: { className?: string }) {
    return <Award className={className} />;
}
