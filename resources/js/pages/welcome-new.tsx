import { dashboard, login, register } from '@/routes';
import { useTranslation } from 'react-i18next';
import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
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
  Mic,
  FileText,
  TrendingUp,
  ChevronRight
} from 'lucide-react';

export default function WelcomeNew({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { t } = useTranslation();
    const { auth } = usePage<SharedData>().props;

    const heroFeatures = [
        { icon: CheckCircle, text: t('welcome.hero.feature_1') },
        { icon: CheckCircle, text: t('welcome.hero.feature_2') },
        { icon: CheckCircle, text: t('welcome.hero.feature_3') },
        { icon: CheckCircle, text: t('welcome.hero.feature_4') },
    ];

    const mainFeatures = [
        {
            icon: Phone,
            title: t('welcome.feature.ai_voice_campaigns.title'),
            description: t('welcome.feature.ai_voice_campaigns.description'),
        },
        {
            icon: MessageSquare,
            title: t('welcome.feature.interactive_dtmf.title'),
            description: t('welcome.feature.interactive_dtmf.description'),
        },
        {
            icon: BarChart3,
            title: t('welcome.feature.realtime_analytics.title'),
            description: t('welcome.feature.realtime_analytics.description'),
        },
        {
            icon: Users,
            title: t('welcome.feature.contact_management.title'),
            description: t('welcome.feature.contact_management.description'),
        },
        {
            icon: Clock,
            title: t('welcome.feature.smart_scheduling.title'),
            description: t('welcome.feature.smart_scheduling.description'),
        },
        {
            icon: Shield,
            title: t('welcome.feature.enterprise_security.title'),
            description: t('welcome.feature.enterprise_security.description'),
        },
    ];

    const pricingPlans = [
        {
            name: t('welcome.pricing.starter.name'),
            price: t('welcome.pricing.starter.price'),
            period: t('welcome.pricing.starter.period'),
            credits: t('welcome.pricing.starter.credits'),
            features: [
                '1,000 voice calls per month',
                'Basic AI voices',
                'Email support',
                'Campaign templates',
                'Basic analytics',
            ],
        },
        {
            name: t('welcome.pricing.professional.name'),
            price: t('welcome.pricing.professional.price'),
            period: t('welcome.pricing.professional.period'),
            credits: t('welcome.pricing.professional.credits'),
            popular: true,
            features: [
                '5,000 voice calls per month',
                'Premium AI voices',
                'Priority support',
                'Advanced templates',
                'Real-time analytics',
                'DTMF interactions',
                'API access',
            ],
        },
        {
            name: t('welcome.pricing.enterprise.name'),
            price: t('welcome.pricing.enterprise.price'),
            period: t('welcome.pricing.enterprise.period'),
            credits: t('welcome.pricing.enterprise.credits'),
            features: [
                'Unlimited voice calls',
                'Custom AI voice training',
                'Dedicated support',
                'Custom integrations',
                'Advanced analytics',
                'White-label options',
                'SLA guarantee',
            ],
        },
    ];

    const faqs = [
        {
            question: t('welcome.faq.q1'),
            answer: t('welcome.faq.a1'),
        },
        {
            question: t('welcome.faq.q2'),
            answer: t('welcome.faq.a2'),
        },
        {
            question: t('welcome.faq.q3'),
            answer: t('welcome.faq.a3'),
        },
        {
            question: t('welcome.faq.q4'),
            answer: t('welcome.faq.a4'),
        },
    ];

    return (
        <>
            <Head title={t('welcome.teleman_ai_aipowered_voice_calling_platform')}>
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link
                    href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700"
                    rel="stylesheet"
                />
            </Head>

            <div className="min-h-screen bg-gradient-to-br from-gray-50 to-white">

                {/* Navigation */}
                <nav className="border-b border-gray-100 bg-white">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex justify-between items-center h-16">
                            <div className="flex items-center space-x-3">
                                <div className="w-8 h-8 bg-black rounded-lg flex items-center justify-center">
                                    <Phone className="w-5 h-5 text-white" />
                                </div>
                                <span className="text-xl font-semibold text-gray-900">
                                    Teleman AI
                                </span>
                            </div>

                            <div className="flex items-center space-x-6">
                                {auth.user ? (
                                    <Link
                                        href={dashboard()}
                                        className="px-6 py-2 bg-black text-white font-medium rounded-lg hover:bg-gray-800 transition-colors"
                                    >
                                        {t('welcome.nav.dashboard')}
                                    </Link>
                                ) : (
                                    <>
                                        <Link
                                            href={login()}
                                            className="text-gray-600 hover:text-gray-900 font-medium transition-colors"
                                        >
                                            {t('welcome.nav.login')}
                                        </Link>
                                        {canRegister && (
                                            <Link
                                                href={register()}
                                                className="px-6 py-2 bg-black text-white font-medium rounded-lg hover:bg-gray-800 transition-colors"
                                            >
                                                {t('welcome.nav.get_started')}
                                            </Link>
                                        )}
                                    </>
                                )}
                            </div>
                        </div>
                    </div>
                </nav>

                {/* Hero Section */}
                <section className="pt-32 pb-20">
                    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                        <h1 className="text-6xl md:text-7xl font-bold text-gray-900 mb-6 leading-tight">
                            {t('welcome.hero.title_line1')}
                            <br />
                            <span className="text-gray-400">{t('welcome.hero.title_line2')}</span>
                        </h1>

                        <p className="text-xl text-gray-600 mb-12 max-w-2xl mx-auto leading-relaxed">
                            {t('welcome.hero.subtitle')}
                        </p>

                        {!auth.user && canRegister && (
                            <Link
                                href={register()}
                                className="inline-flex items-center space-x-2 px-8 py-4 bg-black text-white font-medium rounded-xl hover:bg-gray-800 transition-all"
                            >
                                <span>{t('welcome.hero.get_started_free')}</span>
                                <ArrowRight className="w-5 h-5" />
                            </Link>
                        )}
                    </div>
                </section>

                {/* Features Section */}
                <section className="py-20 bg-white">
                    <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-12">
                            {mainFeatures.map((feature, index) => {
                                const Icon = feature.icon;
                                return (
                                    <div key={index} className="text-center">
                                        <div className="w-12 h-12 bg-gray-100 rounded-xl flex items-center justify-center mx-auto mb-4">
                                            <Icon className="w-6 h-6 text-gray-900" />
                                        </div>
                                        <h3 className="text-lg font-semibold text-gray-900 mb-2">{feature.title}</h3>
                                        <p className="text-gray-600 text-sm leading-relaxed">{feature.description}</p>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </section>

                {/* CTA Section */}
                <section className="py-32 bg-white">
                    <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                        <h2 className="text-5xl md:text-6xl font-bold text-gray-900 mb-4">
                            {t('welcome.cta.heading_line1')}
                            <br />
                            <span className="text-gray-400">{t('welcome.cta.heading_line2')}</span>
                        </h2>
                        <p className="text-xl text-gray-600 mb-10">
                            {t('welcome.cta.subtitle')}
                        </p>

                        {!auth.user && canRegister ? (
                            <Link
                                href={register()}
                                className="inline-flex items-center space-x-2 px-8 py-4 bg-black text-white font-medium rounded-xl hover:bg-gray-800 transition-all"
                            >
                                <span>{t('welcome.cta.start_free_trial')}</span>
                                <ArrowRight className="w-5 h-5" />
                            </Link>
                        ) : auth.user ? (
                            <Link
                                href={dashboard()}
                                className="inline-flex items-center space-x-2 px-8 py-4 bg-black text-white font-medium rounded-xl hover:bg-gray-800 transition-all"
                            >
                                <span>{t('welcome.cta.go_to_dashboard')}</span>
                                <ArrowRight className="w-5 h-5" />
                            </Link>
                        ) : null}
                    </div>
                </section>

                {/* Footer */}
                <footer className="border-t border-gray-100 py-12 bg-white">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex flex-col md:flex-row justify-between items-center">
                            <div className="flex items-center space-x-3 mb-4 md:mb-0">
                                <div className="w-8 h-8 bg-black rounded-lg flex items-center justify-center">
                                    <Phone className="w-5 h-5 text-white" />
                                </div>
                                <span className="text-lg font-semibold text-gray-900">Teleman AI</span>
                            </div>
                            <div className="text-center md:text-right">
                                <p className="text-sm text-gray-600">{t('welcome.footer.copyright')}</p>
                            </div>
                        </div>
                    </div>
                </footer>

            </div>
        </>
    );
}
