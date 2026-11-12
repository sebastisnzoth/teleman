import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import {
    BookOpen,
    FileAudio,
    Folder,
    Home,
    Megaphone,
    Phone,
    PhoneCall,
    BarChart3,
    Users,
    Shield,
    ShieldCheck,
    UserCog,
    Wallet,
    CreditCard,
    DollarSign,
    TrendingUp,
    PhoneIncoming,
    Tag,
    Activity,
    Paintbrush,
    AlertTriangle,
    Workflow,
    Plug,
    Bot,
    Radio,
    History,
    Flame,
    Clock,
    MessageSquare,
    FileText,
    Settings,
    Network,
    Calculator,
    Globe,
    RadioTower
} from 'lucide-react';
import AppLogo from './app-logo';
import { useAuth } from '@/hooks/useAuth';
import { useTranslation } from 'react-i18next';
import { usePage } from '@inertiajs/react';
import { isHidden, useTelephony } from '@/lib/telephony';

const useMainNavItems = (): NavItem[] => {
    const { isAdmin, isCustomer, isAgent } = useAuth();
    const { t } = useTranslation();
    const { hidden } = useTelephony();

    const allItems: NavItem[] = [
        // ==================== DASHBOARD (Standalone) ====================
        {
            title: t('nav.home'),
            href: dashboard(),
            icon: Home,
            visible: true,
        },

        // ==================== COMMUNICATION GROUP ====================
        {
            title: t('nav.communication'),
            isGroup: true,
            visible: true,
            items: [
                {
                    title: t('nav.softphone'),
                    href: '/softphone',
                    icon: Phone,
                    visible: true,
                },
                {
                    title: t('nav.history'),
                    href: '/calls',
                    icon: PhoneCall,
                    visible: true,
                },
                {
                    title: t('nav.phone_numbers'),
                    icon: PhoneIncoming,
                    visible: isCustomer(),
                    items: [
                        {
                            title: t('nav.my_numbers'),
                            href: '/numbers/my-numbers',
                            icon: PhoneIncoming,
                            visible: isCustomer(),
                        },
                        {
                            title: t('nav.purchase_new'),
                            href: '/numbers/available',
                            icon: Tag,
                            visible: isCustomer(),
                        },
                        {
                            title: t('nav.price_calculator'),
                            href: '/pricing/calculator',
                            icon: Calculator,
                            visible: isCustomer(),
                        },
                    ],
                },
                {
                    title: t('nav.my_numbers'),
                    href: '/agent/my-numbers',
                    icon: PhoneIncoming,
                    visible: isAgent(),
                },
                {
                    title: t('nav.my_call_history'),
                    href: '/my-calls',
                    icon: PhoneCall,
                    visible: isCustomer(),
                },
            ],
        },

        // ==================== CONVERSATIONAL AI GROUP ====================
        {
            title: t('nav.conversational_ai'),
            isGroup: true,
            visible: isAdmin() || isCustomer(),
            items: [
                {
                    title: t('nav.ai_agents'),
                    href: '/ai-agents',
                    icon: Bot,
                    visible: isAdmin() || isCustomer(),
                },
                {
                    title: t('nav.knowledge_base'),
                    href: '/knowledge-bases',
                    icon: BookOpen,
                    visible: isAdmin() || isCustomer(),
                },
                {
                    title: t('nav.live_calls'),
                    href: '/ai-agents/live',
                    icon: Radio,
                    visible: isAdmin() || isCustomer(),
                },
                {
                    title: t('nav.call_history'),
                    href: '/ai-agents/calls',
                    icon: History,
                    visible: isAdmin() || isCustomer(),
                },
            ],
        },

        // ==================== CAMPAIGNS GROUP ====================
        {
            title: t('nav.campaigns'),
            isGroup: true,
            visible: true,
            items: [
                {
                    title: t('nav.all_campaigns'),
                    href: '/campaigns',
                    icon: Megaphone,
                    visible: true,
                },
                {
                    title: t('nav.sequences'),
                    href: '/sequences',
                    icon: Workflow,
                    visible: true,
                },
                {
                    title: t('nav.templates'),
                    href: '/campaign-templates',
                    icon: BookOpen,
                    visible: isAdmin() || isCustomer(),
                },
                {
                    title: t('nav.analytics'),
                    icon: BarChart3,
                    visible: true,
                    items: [
                        {
                            title: t('nav.overview'),
                            href: '/analytics/dashboard',
                            icon: BarChart3,
                            visible: true,
                        },
                        {
                            title: t('nav.hot_leads'),
                            href: '/analytics/hot-leads',
                            icon: Flame,
                            visible: true,
                        },
                        {
                            title: t('nav.smart_scheduling'),
                            href: '/analytics/smart-scheduling',
                            icon: Clock,
                            visible: true,
                        },
                    ],
                },
            ],
        },
        {
            title: t('nav.sms'),
            isGroup: true,
            visible: isAdmin() || isCustomer(),
            items: [
                {
                    title: t('nav.phone_numbers'),
                    href: '/sms',
                    icon: Phone,
                    visible: true,
                },
                {
                    title: t('nav.conversations'),
                    href: '/sms/conversations',
                    icon: MessageSquare,
                    visible: true,
                },
                {
                    title: t('nav.templates'),
                    href: '/sms/templates',
                    icon: FileText,
                    visible: true,
                },
                {
                    title: t('nav.analytics'),
                    href: '/sms/analytics',
                    icon: BarChart3,
                    visible: true,
                },
            ],
        },

        // ==================== CONTACTS GROUP ====================
        {
            title: t('nav.contacts'),
            isGroup: true,
            visible: true,
            items: [
                {
                    title: t('nav.all_contacts'),
                    href: '/contacts',
                    icon: Users,
                    visible: true,
                },
                {
                    title: t('nav.contact_lists'),
                    href: '/contact-lists',
                    icon: Folder,
                    visible: isAdmin() || isCustomer(),
                },
            ],
        },

        // ==================== INTEGRATIONS GROUP ====================
        {
            title: t('nav.integrations'),
            isGroup: true,
            visible: isAdmin() || isCustomer(),
            items: [
                {
                    title: t('nav.crm_integrations'),
                    href: '/integrations',
                    icon: Plug,
                    visible: isAdmin() || isCustomer(),
                },
                {
                    title: t('nav.sip_trunk'),
                    href: '/sip-trunk',
                    icon: Network,
                    visible: isAdmin() && !isHidden('sip_trunk', hidden),
                },
            ],
        },

        // ==================== RESOURCES GROUP ====================
        {
            title: t('nav.resources'),
            isGroup: true,
            visible: isAdmin() || isCustomer(),
            items: [
                {
                    title: t('nav.audio_files'),
                    href: '/audio-files',
                    icon: FileAudio,
                    visible: isAdmin() || isCustomer(),
                },
            ],
        },

        // ==================== BILLING GROUP ====================
        {
            title: t('nav.billing'),
            isGroup: true,
            visible: isCustomer(),
            items: [
                {
                    title: t('nav.my_credits'),
                    href: '/credit',
                    icon: Wallet,
                    visible: isCustomer(),
                },
                {
                    title: t('nav.top_up'),
                    href: '/credit/top-up',
                    icon: CreditCard,
                    visible: isCustomer(),
                },
                {
                    title: t('nav.account_statement'),
                    href: '/credit/history',
                    icon: DollarSign,
                    visible: isCustomer(),
                },
            ],
        },

        // ==================== MY TEAM GROUP (Customer) ====================
        {
            title: t('nav.my_team'),
            isGroup: true,
            visible: isCustomer(),
            items: [
                {
                    title: t('nav.agents'),
                    href: '/agents',
                    icon: Users,
                    visible: isCustomer(),
                },
            ],
        },

        // ==================== ADMIN SEPARATOR ====================
        {
            title: t('nav.administration'),
            isSeparator: true,
            visible: isAdmin(),
        },

        // ==================== ADMIN: USER MANAGEMENT ====================
        {
            title: t('nav.user_management'),
            isGroup: true,
            visible: isAdmin(),
            items: [
                {
                    title: t('nav.users'),
                    href: '/users',
                    icon: UserCog,
                    visible: isAdmin(),
                },
                {
                    title: t('nav.roles_permissions'),
                    href: '/roles',
                    icon: Shield,
                    visible: isAdmin(),
                },
                {
                    title: t('nav.kyc_review'),
                    href: '/admin/kyc',
                    icon: ShieldCheck,
                    visible: isAdmin(),
                },
            ],
        },

        // ==================== ADMIN: PHONE MANAGEMENT ====================
        {
            title: t('nav.phone_management'),
            isGroup: true,
            visible: isAdmin(),
            items: [
                {
                    title: t('nav.number_inventory'),
                    href: '/admin/numbers',
                    icon: PhoneIncoming,
                    visible: isAdmin(),
                },
                {
                    title: t('nav.number_requests'),
                    href: '/admin/number-requests',
                    icon: Tag,
                    visible: isAdmin(),
                },
            ],
        },

        // ==================== ADMIN: FINANCIAL MANAGEMENT ====================
        {
            title: t('nav.financial_management'),
            isGroup: true,
            visible: isAdmin(),
            items: [
                {
                    title: t('nav.credit_management'),
                    href: '/admin/credit-management',
                    icon: Wallet,
                    visible: isAdmin(),
                },
                {
                    title: t('nav.all_transactions'),
                    href: '/admin/credit-management/transactions',
                    icon: DollarSign,
                    visible: isAdmin(),
                },
                {
                    title: t('nav.pricing_rates'),
                    icon: DollarSign,
                    visible: isAdmin(),
                    items: [
                        {
                            title: t('nav.manage_pricing'),
                            href: '/admin/pricing',
                            icon: DollarSign,
                            visible: isAdmin(),
                        },
                        {
                            title: t('nav.price_calculator'),
                            href: '/admin/pricing/calculator',
                            icon: Calculator,
                            visible: isAdmin(),
                        },
                    ],
                },
                {
                    title: t('nav.profit_tracking'),
                    href: '/admin/profit-analytics',
                    icon: TrendingUp,
                    visible: isAdmin(),
                },
            ],
        },

        // ==================== ADMIN: SYSTEM MONITORING ====================
        {
            title: t('nav.system_monitoring'),
            isGroup: true,
            visible: isAdmin(),
            items: [
                {
                    title: t('nav.call_logs'),
                    href: '/admin/call-logs',
                    icon: PhoneCall,
                    visible: isAdmin(),
                },
                {
                    title: t('nav.error_logs'),
                    href: '/admin/error-logs',
                    icon: AlertTriangle,
                    visible: isAdmin(),
                },
                {
                    title: t('nav.cron_jobs'),
                    href: '/admin/cron-monitor',
                    icon: Activity,
                    visible: isAdmin(),
                },
            ],
        },

        // ==================== ADMIN: INTEGRATIONS ====================
        {
            title: t('nav.integrations'),
            isGroup: true,
            visible: isAdmin(),
            items: [
                // The telephony provider page: the only place Plivo
                // credentials can be entered, the only place carrying the
                // reset danger zone, and the only link to the provider
                // chooser. Deliberately NOT passed through isHidden():
                // it has to be reachable under every lock state — Twilio,
                // Plivo, and no lock at all — precisely because the entries
                // around it are not. On a Plivo lock both the Twilio
                // settings entry below and the SIP trunk entry in the
                // customer-facing Integrations group disappear; without
                // this one a Plivo buyer would be left with less telephony
                // navigation than a Twilio buyer and no way to reach their
                // own credentials.
                //
                // A sibling of the Twilio entry rather than its parent: the
                // visibility filter at the bottom of this file drops any
                // item whose nested `items` array ends up empty, so a
                // Telephony parent holding only the (gated) Twilio entry
                // would itself vanish on a Plivo lock — the exact failure
                // this entry exists to prevent.
                {
                    title: t('admin.telephony.telephony'),
                    href: '/admin/telephony',
                    icon: RadioTower,
                    visible: isAdmin(),
                },
                {
                    title: t('nav.twilio_settings'),
                    href: '/settings/twilio',
                    icon: Phone,
                    visible: isAdmin() && !isHidden('account_migration', hidden),
                },
                {
                    title: t('nav.system_config'),
                    href: '/admin/system-config',
                    icon: CreditCard,
                    visible: isAdmin(),
                },
                {
                    title: t('nav.languages'),
                    href: '/admin/languages',
                    icon: Globe,
                    visible: isAdmin(),
                },
                {
                    title: t('nav.pages'),
                    href: '/admin/pages',
                    icon: FileText,
                    visible: isAdmin(),
                },
            ],
        },

        // ==================== ADMIN: THEME MANAGEMENT ====================
        {
            title: t('nav.theme_management'),
            isGroup: true,
            visible: isAdmin(),
            items: [
                {
                    title: t('nav.theme_settings'),
                    href: '/admin/theme',
                    icon: Paintbrush,
                    visible: isAdmin(),
                },
            ],
        },
    ];

    // Filter out items and nested items based on visibility
    return allItems
        .filter((item) => item.visible !== false)
        .map((item) => {
            if (item.items) {
                const visibleItems = item.items
                    .filter((subItem) => subItem.visible !== false)
                    .map((subItem) => {
                        if (subItem.items) {
                            return {
                                ...subItem,
                                items: subItem.items.filter((child) => child.visible !== false),
                            };
                        }
                        return subItem;
                    })
                    .filter((subItem) => !subItem.items || subItem.items.length > 0);

                if (visibleItems.length === 0 && item.isGroup) {
                    return null;
                }

                return { ...item, items: visibleItems };
            }
            return item;
        })
        .filter((item): item is NavItem => item !== null);
};

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
    const mainNavItems = useMainNavItems();
    const { locale, activeLanguages } = usePage().props as unknown as { locale: string; activeLanguages: { code: string; is_rtl: boolean }[] };
    const isRtl = activeLanguages?.find((l) => l.code === locale)?.is_rtl ?? false;

    return (
        <Sidebar collapsible="icon" variant="inset" side={isRtl ? 'right' : 'left'}>
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
