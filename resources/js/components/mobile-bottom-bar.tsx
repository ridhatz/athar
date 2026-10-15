import { UserMenuTrigger } from '@/components/athar/user-menu-trigger';

/**
 * The account menu, moved here on small screens. The top bar is already tight on mobile
 * (toggle, breadcrumb, bell), and a user menu squeezed in beside them reads as an afterthought
 * — a fixed bar of its own gives it a proper tap target instead.
 *
 * Desktop gets the equivalent trigger in the top-right of AppSidebarHeader instead; this bar is
 * `md:hidden` so the two never show at once.
 */
export function MobileBottomBar() {
    return (
        <nav className="bg-background fixed inset-x-0 bottom-0 z-40 border-t p-2 md:hidden">
            <UserMenuTrigger fullWidth />
        </nav>
    );
}
