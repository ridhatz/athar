import { Badge } from '@/components/ui/badge';
import { useT } from '@/hooks/use-t';
import { cn } from '@/lib/utils';
import type { RequestPriority, RequestStatus } from '@/types/athar';

/** Colour carries meaning here, but the label always says it too, so it is never colour-only. */
const STATUS_STYLES: Record<RequestStatus, string> = {
    NEW: 'bg-slate-100 text-slate-800 border-slate-300 dark:bg-slate-800 dark:text-slate-100 dark:border-slate-600',
    TRIAGED:
        'bg-sky-100 text-sky-900 border-sky-300 dark:bg-sky-950 dark:text-sky-200 dark:border-sky-800',
    ASSIGNED:
        'bg-indigo-100 text-indigo-900 border-indigo-300 dark:bg-indigo-950 dark:text-indigo-200 dark:border-indigo-800',
    IN_PROGRESS:
        'bg-amber-100 text-amber-900 border-amber-300 dark:bg-amber-950 dark:text-amber-200 dark:border-amber-800',
    REVIEW: 'bg-violet-100 text-violet-900 border-violet-300 dark:bg-violet-950 dark:text-violet-200 dark:border-violet-800',
    COMPLETED:
        'bg-emerald-100 text-emerald-900 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-200 dark:border-emerald-800',
    BLOCKED:
        'bg-red-100 text-red-900 border-red-300 dark:bg-red-950 dark:text-red-200 dark:border-red-800',
    CANCELLED:
        'bg-zinc-100 text-zinc-600 border-zinc-300 dark:bg-zinc-900 dark:text-zinc-400 dark:border-zinc-700',
};

const PRIORITY_STYLES: Record<RequestPriority, string> = {
    LOW: 'bg-transparent text-muted-foreground border-border',
    NORMAL: 'bg-transparent text-foreground border-border',
    HIGH: 'bg-orange-100 text-orange-900 border-orange-300 dark:bg-orange-950 dark:text-orange-200 dark:border-orange-800',
    CRITICAL:
        'bg-red-600 text-white border-red-700 dark:bg-red-700 dark:border-red-500',
};

export function StatusBadge({
    status,
    className,
}: {
    status: RequestStatus;
    className?: string;
}) {
    const { t } = useT();

    return (
        <Badge
            variant="outline"
            className={cn(STATUS_STYLES[status], className)}
        >
            {t(`status.${status}`)}
        </Badge>
    );
}

export function PriorityBadge({
    priority,
    className,
}: {
    priority: RequestPriority;
    className?: string;
}) {
    const { t } = useT();

    return (
        <Badge
            variant="outline"
            className={cn(PRIORITY_STYLES[priority], className)}
        >
            {t(`priority.${priority}`)}
        </Badge>
    );
}
