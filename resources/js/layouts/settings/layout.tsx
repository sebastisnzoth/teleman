import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { cn, isSameUrl, resolveUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { show } from '@/routes/two-factor';
import { edit as editPassword } from '@/routes/user-password';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: edit(),
        icon: null,
    },
    {
        title: 'Password',
        href: editPassword(),
        icon: null,
    },
    {
        title: 'Two-Factor Auth',
        href: show(),
        icon: null,
    },
    {
        title: 'KYC Verification',
        href: '/settings/kyc',
        icon: null,
        roles: ['customer'], // Only show for customers
        badge: 'NEW',
    },
    {
        title: 'KYC',
        href: '/admin/kyc/settings',
        icon: null,
        roles: ['admin'], // Only show for admins
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: null,
    },
    {
        title: 'API Keys',
        href: '/settings/api-keys',
        icon: null,
    },

];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const currentPath = typeof window !== 'undefined' ? window.location.pathname : '';
    const { auth, kycEnabled } = usePage().props as any;
    const userRoles = auth?.user?.roles?.map((r: any) => r.slug.toLowerCase()) || [];

    // Filter menu items based on user role and KYC enabled status
    const filteredNavItems = sidebarNavItems.filter(item => {
        // Hide KYC-related items if KYC is disabled
        if (!kycEnabled && (item.href === '/settings/kyc' || item.href === '/admin/kyc/settings')) {
            return false;
        }
        
        if (!item.roles) return true; // Show items without role restriction
        return item.roles.some((role: string) => userRoles.includes(role.toLowerCase()));
    });

    return (
        <div className="px-4 py-6">
            <Heading
                title="Settings"
                description="Manage your profile and account settings"
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav className="flex flex-col space-y-1 space-x-0">
                        {filteredNavItems.map((item, index) => (
                            <Button
                                key={`${resolveUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': isSameUrl(
                                        currentPath,
                                        item.href,
                                    ),
                                })}
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon className="h-4 w-4" />
                                    )}
                                    {item.title}
                                    {item.badge && (
                                        <span className="ml-auto inline-flex items-center rounded-md bg-primary/10 px-2 py-1 text-xs font-medium text-primary ring-1 ring-inset ring-primary/20">
                                            {item.badge}
                                        </span>
                                    )}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-12">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
