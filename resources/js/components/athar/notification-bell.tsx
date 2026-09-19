import { router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useFormat } from '@/hooks/use-format';
import { useT } from '@/hooks/use-t';
import { cn } from '@/lib/utils';
import { open, readAll } from '@/routes/notifications';
import type { NotificationEntry } from '@/types/athar';

/** "Admin assigned ATH-000001 to you": the stored code and parameters, put into the reader's language. */
export function useNotificationText() {
    const { t } = useT();

    return (entry: NotificationEntry): string => {
        const params: Record<string, string | number> = {
            ...(entry.params as Record<string, string | number>),
            number: entry.request_number ?? '',
            title: entry.title ?? '',
        };
        for (const key of ['from', 'to']) {
            if (params[key]) {
                params[key] = t(`status.${params[key]}`);
            }
        }

        return t(`notify.${entry.type}`, params);
    };
}

export function NotificationBell() {
    const { notifications } = usePage().props;
    const { t } = useT();
    const format = useFormat();
    const describe = useNotificationText();

    if (!notifications) {
        return null;
    }

    const { unread, recent } = notifications;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative"
                    aria-label={`${t('notify.title')}${unread > 0 ? ` (${t('notify.unread', { count: unread })})` : ''}`}
                >
                    <Bell />
                    {unread > 0 && (
                        <span className="bg-destructive absolute -top-0.5 -right-0.5 flex min-w-4 items-center justify-center rounded-full px-1 text-[10px] leading-4 font-semibold text-white">
                            {unread > 9 ? '9+' : unread}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80">
                <DropdownMenuLabel className="flex items-center justify-between">
                    <span>{t('notify.title')}</span>
                    {unread > 0 && (
                        <button
                            type="button"
                            className="text-primary text-xs font-normal underline-offset-4 hover:underline"
                            onClick={() =>
                                router.post(
                                    readAll().url,
                                    {},
                                    {
                                        preserveScroll: true,
                                        preserveState: true,
                                    },
                                )
                            }
                        >
                            {t('notify.mark_all')}
                        </button>
                    )}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {recent.length === 0 ? (
                    <p className="text-muted-foreground px-2 py-6 text-center text-sm">
                        {t('notify.empty')}
                    </p>
                ) : (
                    recent.map((entry) => (
                        <DropdownMenuItem
                            key={entry.id}
                            className="flex cursor-pointer flex-col items-start gap-0.5 py-2"
                            onSelect={() =>
                                router.post(open({ id: entry.id }).url)
                            }
                        >
                            <span
                                className={cn(
                                    'text-sm leading-snug',
                                    !entry.read_at && 'font-medium',
                                )}
                            >
                                {!entry.read_at && (
                                    <span
                                        aria-hidden
                                        className="bg-primary mr-1.5 inline-block size-1.5 rounded-full align-middle"
                                    />
                                )}
                                {describe(entry)}
                            </span>
                            {entry.title && (
                                <span className="text-muted-foreground line-clamp-1 text-xs">
                                    {entry.title}
                                </span>
                            )}
                            <time
                                dateTime={entry.created_at}
                                className="text-muted-foreground text-xs"
                            >
                                {format.dateTime(entry.created_at)}
                            </time>
                        </DropdownMenuItem>
                    ))
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
