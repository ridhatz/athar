import { useT } from '@/hooks/use-t';
import { LEVEL_COLOUR, PRIORITY_COLOUR } from '@/lib/urgency';
import { cn } from '@/lib/utils';
import type { LoadLevel, RequestPriority } from '@/types/athar';

/** A thin coloured stripe for a task: its urgency at a glance. Decorative; the badge carries the words. */
export function PriorityStripe({
    priority,
    className,
}: {
    priority: RequestPriority;
    className?: string;
}) {
    return (
        <span
            aria-hidden
            data-priority={priority}
            className={cn(
                'absolute inset-y-0 left-0 w-1.5',
                PRIORITY_COLOUR[priority],
                className,
            )}
        />
    );
}

/** A full-width bar with the priority written on it, for the top of a request. */
export function PriorityBanner({ priority }: { priority: RequestPriority }) {
    const { t } = useT();

    return (
        <div
            role="img"
            aria-label={t(`priority.${priority}`)}
            className="bg-muted flex h-2 w-full overflow-hidden rounded-full"
        >
            {(['LOW', 'NORMAL', 'HIGH', 'CRITICAL'] as RequestPriority[]).map(
                (step, index) => {
                    const reached =
                        index <=
                        ['LOW', 'NORMAL', 'HIGH', 'CRITICAL'].indexOf(priority);

                    return (
                        <span
                            key={step}
                            className={cn(
                                'h-full flex-1 border-r border-white/60 last:border-r-0 dark:border-black/40',
                                reached
                                    ? PRIORITY_COLOUR[step]
                                    : 'bg-transparent',
                            )}
                        />
                    );
                },
            )}
        </div>
    );
}

export function LevelDot({ level }: { level: LoadLevel }) {
    return (
        <span
            aria-hidden
            className={cn(
                'inline-block size-2.5 rounded-full',
                LEVEL_COLOUR[level],
            )}
        />
    );
}
