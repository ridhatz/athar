import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { MobileBottomBar } from '@/components/mobile-bottom-bar';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            {/* pb-16 clears the fixed MobileBottomBar; md:pb-0 because that bar is mobile-only. */}
            <AppContent
                variant="sidebar"
                className="min-w-0 overflow-x-clip pb-16 md:pb-0"
            >
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
            <MobileBottomBar />
        </AppShell>
    );
}
