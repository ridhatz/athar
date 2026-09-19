import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid, ListChecks, PlusCircle } from 'lucide-react';
import AppLogo from '@/components/app-logo';
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
import { useT } from '@/hooks/use-t';
import { dashboard } from '@/routes';
import { create, index } from '@/routes/requests';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { t } = useT();
    const { can } = usePage().props;

    const mainNavItems: NavItem[] = [
        { title: t('nav.dashboard'), href: dashboard(), icon: LayoutGrid },
        { title: t('nav.requests'), href: index(), icon: ListChecks },
        // Whether the person may file requests is decided by the server (management is observe-only).
        ...(can.create_request
            ? [
                  {
                      title: t('nav.new_request'),
                      href: create(),
                      icon: PlusCircle,
                  },
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
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
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
