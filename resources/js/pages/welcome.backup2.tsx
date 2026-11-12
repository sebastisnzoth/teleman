import { dashboard, login, register } from '@/routes';
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

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth } = usePage<SharedData>().props;

    const heroFeatures = [
        { icon: CheckCircle, text: 'Natural-sounding AI voices in multiple languages' },
        { icon: CheckCircle, text: 'Launch campaigns in under 5 minutes' },
        { icon: CheckCircle, text: 'Interactive DTMF & call routing' },
        { icon: CheckCircle, text: 'Real-time analytics & detailed reports' },
    ];

    const mainFeatures = [
        {
            icon: Phone,
            title: 'AI Voice Campaigns',
            description: 'Deploy automated voice calling campaigns at scale. Natural-sounding voices with customizable scripts.'
        },
        {
            icon: MessageSquare,
            title: 'Interactive DTMF',
            description: 'Enable two-way communication. Let recipients respond via keypad for surveys, confirmations, and routing.'
        },
        {
            icon: BarChart3,
            title: 'Real-Time Analytics',
            description: 'Monitor campaign performance live. Track delivery rates, responses, and call outcomes instantly.'
        },
        {
            icon: Users,
            title: 'Contact Management',
            description: 'Import, organize, and segment your contacts. Upload CSV files with thousands of records instantly.'
        },
        {
            icon: Clock,
            title: 'Smart Scheduling',
            description: 'Schedule campaigns for optimal timing. Set timezone-aware delivery windows for better engagement.'
        },
        {
            icon: Shield,
            title: 'Enterprise Security',
            description: 'Bank-level encryption for all data. Compliant with GDPR, TCPA, and industry regulations.'
        }
    ];

    const pricingPlans = [
        {
            name: 'Starter',
            price: '$99',
            period: 'per month',
            credits: '1,000 credits included',
            features: [
                '1,000 voice calls per month',
                'Basic AI voices',
                'Email support',
                'Campaign templates',
                'Basic analytics'
            ]
        },
        {
            name: 'Professional',
            price: '$299',
            period: 'per month',
            credits: '5,000 credits included',
            popular: true,
            features: [
                '5,000 voice calls per month',
                'Premium AI voices',
                'Priority support',
                'Advanced templates',
                'Real-time analytics',
                'DTMF interactions',
                'API access'
            ]
        },
        {
            name: 'Enterprise',
            price: 'Custom',
            period: 'contact us',
            credits: 'Unlimited credits',
            features: [
                'Unlimited voice calls',
                'Custom AI voice training',
                'Dedicated support',
                'Custom integrations',
                'Advanced analytics',
                'White-label options',
                'SLA guarantee'
            ]
        }
    ];

    const faqs = [
        {
            question: 'How do voice credits work?',
            answer: 'Each successful call consumes 1 credit. Failed calls or busy signals don\'t count. Credits reset monthly with your subscription.'
        },
        {
            question: 'Can I use my own phone numbers?',
            answer: 'Yes! You can use your existing Twilio numbers or purchase new ones directly through our platform.'
        },
        {
            question: 'What languages are supported?',
            answer: 'We support 40+ languages with natural-sounding AI voices, including English, Spanish, French, German, and many more.'
        },
        {
            question: 'Is there a free trial?',
            answer: 'Yes! New users get 100 free credits to test the platform. No credit card required for the trial.'
        }
    ];

    return (
        <>
            <Head title="Teleman AI - AI-Powered Voice Calling Platform">
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
                            {/* Logo */}
                            <div className="flex items-center space-x-3">
                                <div className="w-8 h-8 bg-black rounded-lg flex items-center justify-center">
                                    <Phone className="w-5 h-5 text-white" />
                                </div>
                                <span className="text-xl font-semibold text-gray-900">
                                    Teleman AI
                                </span>
                            </div>

                            {/* Auth Links */}
                            <div className="flex items-center space-x-6">
                                {auth.user ? (
                                    <Link
                                        href={dashboard()}
                                        className="px-6 py-2 bg-black text-white font-medium rounded-lg hover:bg-gray-800 transition-colors"
                                    >
                                        Dashboard
                                    </Link>
                                ) : (
                                    <>
                                        <Link
                                            href={login()}
                                            className="text-gray-600 hover:text-gray-900 font-medium transition-colors"
                                        >
                                            Log in
                                        </Link>
                                        {canRegister && (
                                            <Link
                                                href={register()}
                                                className="px-6 py-2 bg-black text-white font-medium rounded-lg hover:bg-gray-800 transition-colors"
                                            >
                                                Get Started
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
                        {/* Heading */}
                        <h1 className="text-6xl md:text-7xl font-bold text-gray-900 mb-6 leading-tight">
                            AI Voice Calling
                            <br />
                            <span className="text-gray-400">Made Simple</span>
                        </h1>

                        {/* Subheading */}
                        <p className="text-xl text-gray-600 mb-12 max-w-2xl mx-auto leading-relaxed">
                            Launch automated voice campaigns in minutes. Natural-sounding AI voices,
                            real-time analytics, and powerful DTMF interactions.
                        </p>

                        {/* CTA Button */}
                        {!auth.user && canRegister && (
                            <Link
                                href={register()}
                                className="inline-flex items-center space-x-2 px-8 py-4 bg-black text-white font-medium rounded-xl hover:bg-gray-800 transition-all"
                            >
                                <span>Get Started Free</span>
                                <ArrowRight className="w-5 h-5" />
                            </Link>
                        )}
                    </div>
                </section>

                {/* Features Section */}
                <section className="py-20 bg-white">
                    <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-12">
                            {features.map((feature, index) => {
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

                {/* Use Cases Section */}
                <section className="py-20 bg-gray-50">
                    <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="grid md:grid-cols-3 gap-12">
                            {useCases.map((useCase, index) => {
                                const Icon = useCase.icon;
                                return (
                                    <div key={index}>
                                        <div className="w-12 h-12 bg-white rounded-xl flex items-center justify-center mb-4 shadow-sm">
                                            <Icon className="w-6 h-6 text-gray-900" />
                                        </div>
                                        <h3 className="text-xl font-semibold text-gray-900 mb-4">{useCase.title}</h3>
                                        <ul className="space-y-2">
                                            {useCase.items.map((item, i) => (
                                                <li key={i} className="flex items-start space-x-2">
                                                    <CheckCircle className="w-4 h-4 text-gray-400 mt-0.5 flex-shrink-0" />
                                                    <span className="text-gray-600 text-sm">{item}</span>
                                                </li>
                                            ))}
                                        </ul>
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
                            Ready to Scale Your
                            <br />
                            <span className="text-gray-400">Voice Campaigns?</span>
                        </h2>
                        <p className="text-xl text-gray-600 mb-10">
                            Join businesses automating thousands of calls daily with Teleman AI.
                        </p>
                        
                        {!auth.user && canRegister ? (
                            <Link
                                href={register()}
                                className="inline-flex items-center space-x-2 px-8 py-4 bg-black text-white font-medium rounded-xl hover:bg-gray-800 transition-all"
                            >
                                <span>Start Free Trial</span>
                                <ArrowRight className="w-5 h-5" />
                            </Link>
                        ) : auth.user ? (
                            <Link
                                href={dashboard()}
                                className="inline-flex items-center space-x-2 px-8 py-4 bg-black text-white font-medium rounded-xl hover:bg-gray-800 transition-all"
                            >
                                <span>Go to Dashboard</span>
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
                                <p className="text-sm text-gray-600">© 2025 Teleman AI. All rights reserved.</p>
                            </div>
                        </div>
                    </div>
                </footer>


            </div>
        </>
    );
}
                
