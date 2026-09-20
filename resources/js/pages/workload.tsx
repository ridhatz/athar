import { Head } from '@inertiajs/react';
import { LevelDot } from '@/components/athar/urgency-bar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import { useT } from '@/hooks/use-t';
import { LEVEL_COLOUR, LEVEL_TEXT } from '@/lib/urgency';
import { cn } from '@/lib/utils';
import type { LoadLevel, WorkloadEntry } from '@/types/athar';

type Props = {
    board: WorkloadEntry[];
    thresholds: { yellow_from: number; orange_from: number; red_from: number };
};

const LEVELS: LoadLevel[] = ['green', 'yellow', 'orange', 'red'];

export default function Workload({ board, thresholds }: Props) {
    const { t } = useT();
    // The bar is full at the red line, so "red" always looks full.
    const full = thresholds.red_from;

    const range = (level: LoadLevel) => {
        const { yellow_from, orange_from, red_from } = thresholds;
        switch (level) {
            case 'green':
                return `0–${yellow_from - 1}`;
            case 'yellow':
                return `${yellow_from}–${orange_from - 1}`;
            case 'orange':
                return `${orange_from}–${red_from - 1}`;
            default:
                return `${red_from}+`;
        }
    };

    return (
        <>
            <Head title={t('workload.title')} />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-5 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t('workload.title')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {t('workload.subtitle')}
                    </p>
                </div>

                <ul
                    className="flex flex-wrap gap-x-5 gap-y-2 text-sm"
                    aria-label={t('workload.legend')}
                >
                    {LEVELS.map((level) => (
                        <li key={level} className="flex items-center gap-2">
                            <LevelDot level={level} />
                            <span
                                className={cn('font-medium', LEVEL_TEXT[level])}
                            >
                                {t(`workload.level.${level}`)}
                            </span>
                            <span className="text-muted-foreground">
                                {t('workload.tasks_range', {
                                    range: range(level),
                                })}
                            </span>
                        </li>
                    ))}
                </ul>
                <p className="text-muted-foreground -mt-2 text-xs">
                    {t('workload.note')}
                </p>

                <ol className="space-y-3">
                    {board.map((person) => (
                        <li key={person.id}>
                            <Card>
                                <CardContent className="flex flex-col gap-3 sm:flex-row sm:items-center">
                                    <div className="flex min-w-0 flex-1 items-center gap-3">
                                        <span
                                            className="bg-muted text-muted-foreground flex size-8 shrink-0 items-center justify-center rounded-full text-sm font-semibold"
                                            aria-label={`#${person.rank}`}
                                        >
                                            {person.rank}
                                        </span>
                                        <div className="min-w-0">
                                            <div className="truncate font-medium">
                                                {person.name}
                                            </div>
                                            <div className="text-muted-foreground truncate text-xs">
                                                {person.teams.join(', ') || '—'}
                                            </div>
                                        </div>
                                    </div>

                                    <div className="flex-1 space-y-1.5 sm:max-w-xs">
                                        <div className="flex items-baseline justify-between text-sm">
                                            <span
                                                className={cn(
                                                    'font-semibold',
                                                    LEVEL_TEXT[person.level],
                                                )}
                                            >
                                                {t('workload.open', {
                                                    n: person.open,
                                                })}
                                            </span>
                                            <span className="text-muted-foreground text-xs">
                                                {t(
                                                    `workload.level.${person.level}`,
                                                )}
                                            </span>
                                        </div>
                                        <div
                                            className="bg-muted h-2.5 overflow-hidden rounded-full"
                                            role="progressbar"
                                            aria-valuemin={0}
                                            aria-valuemax={full}
                                            aria-valuenow={Math.min(
                                                person.open,
                                                full,
                                            )}
                                            aria-label={`${person.name}: ${t('workload.open', { n: person.open })}`}
                                        >
                                            <div
                                                className={cn(
                                                    'h-full rounded-full transition-all',
                                                    LEVEL_COLOUR[person.level],
                                                )}
                                                style={{
                                                    width: `${Math.min(100, (person.open / full) * 100)}%`,
                                                }}
                                            />
                                        </div>
                                    </div>

                                    <div className="sm:w-56">
                                        {person.working_on.length === 0 ? (
                                            <span className="text-muted-foreground text-sm">
                                                {t('workload.nothing')}
                                            </span>
                                        ) : (
                                            <div className="flex flex-wrap gap-1.5">
                                                {person.working_on.map(
                                                    (item) => (
                                                        <Badge
                                                            key={item.category}
                                                            variant="secondary"
                                                        >
                                                            {item.category}
                                                            {item.count > 1 &&
                                                                ` ×${item.count}`}
                                                        </Badge>
                                                    ),
                                                )}
                                            </div>
                                        )}
                                        <div className="text-muted-foreground mt-1 text-xs">
                                            {t('workload.done_30d', {
                                                n: person.completed_30d,
                                            })}
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </li>
                    ))}
                    {board.length === 0 && (
                        <li className="text-muted-foreground text-sm">—</li>
                    )}
                </ol>
            </div>
        </>
    );
}
