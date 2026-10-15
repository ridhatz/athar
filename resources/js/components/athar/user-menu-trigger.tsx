import { ChevronsUpDown } from 'lucide-react';
import { usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { UserMenuContent } from '@/components/user-menu-content';
import { cn } from '@/lib/utils';

/**
 * The signed-in person's avatar, name, and account menu (settings, language, log out).
 *
 * Deliberately NOT built from the sidebar's menu primitives: this trigger lives in the top bar
 * on desktop and in a fixed bar on mobile, neither of which is inside the Sidebar's own DOM
 * tree, so it has no business depending on sidebar-specific styling.
 */
export function UserMenuTrigger({
    fullWidth = false,
}: {
    /** The mobile bottom bar wants a full-width tap target; the desktop top bar does not. */
    fullWidth?: boolean;
}) {
    const { auth } = usePage().props;

    if (!auth.user) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    data-test="user-menu-trigger"
                    className={cn(
                        'h-auto gap-2 px-2 py-1.5',
                        fullWidth && 'w-full justify-start',
                    )}
                >
                    <UserInfo user={auth.user} />
                    <ChevronsUpDown className="text-muted-foreground ml-auto size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent
                className="w-56 rounded-lg"
                align="end"
                side={fullWidth ? 'top' : 'bottom'}
            >
                <UserMenuContent user={auth.user} />
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
