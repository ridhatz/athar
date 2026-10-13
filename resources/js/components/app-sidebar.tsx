import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    FolderTree,
    LayoutGrid,
    ListChecks,
    PlusCircle,
    UserCog,
    Users,
    UsersRound,
} from 'lucide-react';
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
import { index as categories } from '@/routes/admin/categories';
import { index as departments } from '@/routes/admin/departments';
import { index as teams } from '@/routes/admin/teams';
import { index as people } from '@/routes/admin/users';
import { index as workload } from '@/routes/workload';
import { create, index } from '@/routes/requests';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { t } = useT();
    const { can } = usePage().props;

    const mainNavItems: NavItem[] = [
        { title: t('nav.dashboard'), href: dashboard(), icon: LayoutGrid },
        { title: t('nav.requests'), href: index(), icon: ListChecks },
        { title: t('nav.workload'), href: workload(), icon: Users },
        // Only administrators are offered the People screen (the server enforces it regardless).
        ...(can.manage_users
            ? [{ title: t('nav.people'), href: people(), icon: UserCog }]
            : []),
        // Reference data, same stance: ADMIN only, and the server enforces it regardless.
        ...(can.manage_reference
            ? [
                  { title: t('nav.teams'), href: teams(), icon: UsersRound },
                  {
                      title: t('nav.categories'),
                      href: categories(),
                      icon: FolderTree,
                  },
                  {
                      title: t('nav.departments'),
                      href: departments(),
                      icon: Building2,
                  },
              ]
            : []),
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
