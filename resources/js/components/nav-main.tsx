import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { Badge } from '@/components/ui/badge';
import { resolveUrl } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Minus, Plus } from 'lucide-react';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const page = usePage();
    
    // Helper function to check if a menu item is active
    const isActive = (href: string | undefined): boolean => {
        if (!href) return false;
        const resolvedHref = resolveUrl(href);
        const currentUrl = page.url;
        
        // Exact match
        if (currentUrl === resolvedHref) return true;
        
        // For paths with subpaths, only match if it's followed by a slash or query string
        // This prevents /calls from matching /softphone
        if (currentUrl.startsWith(resolvedHref)) {
            const nextChar = currentUrl.charAt(resolvedHref.length);
            return nextChar === '/' || nextChar === '?' || nextChar === '#' || nextChar === '';
        }
        
        return false;
    };
    
    return (
        <>
            {items.map((item, index) => {
                // Handle visual separator
                if (item.isSeparator) {
                    return (
                        <div key={`separator-${index}`} className="my-1.5 px-2">
                            <div className="border-t border-sidebar-border" />
                            {item.title && (
                                <div className="px-2 py-1.5 text-xs font-semibold text-sidebar-foreground/70 uppercase tracking-wider whitespace-nowrap truncate">
                                    {item.title}
                                </div>
                            )}
                        </div>
                    );
                }

                // Handle groups with nested items
                if (item.isGroup && item.items && item.items.length > 0) {
                    return (
                        <SidebarGroup key={item.title} className="px-2 py-0">
                            <SidebarMenu>
                                <Collapsible
                                    defaultOpen={true}
                                    className="group/collapsible"
                                >
                                    <SidebarMenuItem>
                                        <CollapsibleTrigger asChild>
                                            <SidebarMenuButton className="font-medium">
                                                <span className="whitespace-nowrap truncate">{item.title}</span>
                                                {item.badge && (
                                                    <Badge variant="secondary" className="ms-2 text-[10px] px-1.5 py-0 h-4">
                                                        {item.badge}
                                                    </Badge>
                                                )}
                                                <Plus className="ms-auto group-data-[state=open]/collapsible:hidden" />
                                                <Minus className="ms-auto group-data-[state=closed]/collapsible:hidden" />
                                            </SidebarMenuButton>
                                        </CollapsibleTrigger>
                                        {item.items?.length ? (
                                            <CollapsibleContent>
                                                <SidebarMenuSub>
                                                    {item.items.map((subItem) => {
                                                        // Handle nested items (like Phone Numbers with sub-items)
                                                        if (subItem.items && subItem.items.length > 0) {
                                                            return (
                                                                <Collapsible
                                                                    key={subItem.title}
                                                                    defaultOpen={true}
                                                                    className="group/nested"
                                                                >
                                                                    <SidebarMenuSubItem>
                                                                        <CollapsibleTrigger asChild>
                                                                            <SidebarMenuSubButton>
                                                                                {subItem.icon && <subItem.icon />}
                                                                                <span className="whitespace-nowrap truncate">{subItem.title}</span>
                                                                                {subItem.badge && (
                                                                                    <Badge variant="secondary" className="ms-2 text-[10px] px-1.5 py-0 h-4">
                                                                                        {subItem.badge}
                                                                                    </Badge>
                                                                                )}
                                                                                <Plus className="ms-auto group-data-[state=open]/nested:hidden" />
                                                                                <Minus className="ms-auto group-data-[state=closed]/nested:hidden" />
                                                                            </SidebarMenuSubButton>
                                                                        </CollapsibleTrigger>
                                                                        <CollapsibleContent>
                                                                            <SidebarMenuSub>
                                                                                {subItem.items.map((childItem) => (
                                                                                    <SidebarMenuSubItem key={childItem.title}>
                                                                                        <SidebarMenuSubButton
                                                                                            asChild
                                                                                            isActive={isActive(childItem.href)}
                                                                                        >
                                                                                            <Link href={childItem.href || '#'} prefetch={true}>
                                                                                                <span className="whitespace-nowrap truncate">{childItem.title}</span>
                                                                                                {childItem.badge && (
                                                                                                    <Badge variant="secondary" className="ms-2 text-[10px] px-1.5 py-0 h-4">
                                                                                                        {childItem.badge}
                                                                                                    </Badge>
                                                                                                )}
                                                                                            </Link>
                                                                                        </SidebarMenuSubButton>
                                                                                    </SidebarMenuSubItem>
                                                                                ))}
                                                                            </SidebarMenuSub>
                                                                        </CollapsibleContent>
                                                                    </SidebarMenuSubItem>
                                                                </Collapsible>
                                                            );
                                                        }
                                                        
                                                        // Regular sub-item
                                                        return (
                                                            <SidebarMenuSubItem key={subItem.title}>
                                                                <SidebarMenuSubButton
                                                                    asChild
                                                                    isActive={isActive(subItem.href)}
                                                                >
                                                                    <Link href={subItem.href || '#'} prefetch={true}>
                                                                        {subItem.icon && <subItem.icon />}
                                                                        <span className="whitespace-nowrap truncate">{subItem.title}</span>
                                                                        {subItem.badge && (
                                                                            <Badge variant="secondary" className="ms-2 text-[10px] px-1.5 py-0 h-4">
                                                                                {subItem.badge}
                                                                            </Badge>
                                                                        )}
                                                                    </Link>
                                                                </SidebarMenuSubButton>
                                                            </SidebarMenuSubItem>
                                                        );
                                                    })}
                                                </SidebarMenuSub>
                                            </CollapsibleContent>
                                        ) : null}
                                    </SidebarMenuItem>
                                </Collapsible>
                            </SidebarMenu>
                        </SidebarGroup>
                    );
                }

                // Handle standalone items (like Dashboard, Settings)
                return (
                    <SidebarGroup key={item.title} className="px-2 py-0">
                        <SidebarMenu>
                            <SidebarMenuItem>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isActive(item.href)}
                                    tooltip={{ children: item.title }}
                                >
                                    <Link href={item.href || '#'} prefetch={true}>
                                        {item.icon && <item.icon />}
                                        <span className="whitespace-nowrap truncate">{item.title}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroup>
                );
            })}
        </>
    );
}
