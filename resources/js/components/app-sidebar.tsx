import { Link, usePage } from '@inertiajs/react';
import {
    Building2,
    ChartPie,
    ClipboardList,
    FolderTree,
    LayoutGrid,
    ListChecks,
    PlusCircle,
    Target,
    UserCog,
    Users,
    UsersRound,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import {
    Sidebar,
    SidebarContent,
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
import { analysis as kpiAnalysis, index as kpi } from '@/routes/kpi';
import { index as kpiTemplates } from '@/routes/kpi/templates';
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
        // Everyone has a KPI sheet; the list narrows itself to what you may see.
        { title: t('nav.kpi'), href: kpi(), icon: Target },
        { title: t('nav.kpi_analysis'), href: kpiAnalysis(), icon: ChartPie },
        // Blueprints are reference data, so only an administrator is offered them.
        ...(can.manage_reference
            ? [
                  {
                      title: t('nav.kpi_templates'),
                      href: kpiTemplates(),
                      icon: ClipboardList,
                  },
              ]
            : []),
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
            {/* The logo lives directly on the panel's own blue, same as the nav below it — one
                continuous surface instead of a white card sitting inside a blue one. The signed-in
                user used to live in a footer here; it now lives in the top bar (desktop) or the
                bottom bar (mobile) — see AppSidebarHeader / MobileBottomBar. */}
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
        </Sidebar>
    );
}
