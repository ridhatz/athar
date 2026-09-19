import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useT } from '@/hooks/use-t';
import { cn } from '@/lib/utils';
import { index } from '@/routes/requests';
import type {
    DashboardCard,
    DashboardData,
    GroupCount,
    Perspective,
} from '@/types/athar';

type Props = {
    perspectives: Perspective[];
    data: DashboardData;
};

/** Cards that call for attention get a stronger colour; the label always says what they are. */
const ATTENTION = new Set(['overdue', 'blocked', 'needs_triage']);

function CardGrid({ cards }: { cards: DashboardCard[] }) {
    const { t } = useT();

    return (
        <div className="grid grid-cols-2 gap-3 md:grid-cols-3">
            {cards.map((card) => (
                <Link
                    key={card.key}
                    // The card IS the list: same filters, same query, so the number can be checked by clicking it.
                    href={index({ query: card.filters })}
                    className="group focus-visible:ring-ring/50 rounded-xl outline-none focus-visible:ring-[3px]"
                >
                    <Card className="group-hover:border-primary/40 h-full transition-colors">
                        <CardHeader className="pb-1">
                            <CardTitle className="text-muted-foreground text-sm font-medium">
                                {t(`dashboard.card.${card.key}`)}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <span
                                className={cn(
                                    'text-3xl font-semibold tabular-nums',
                                    card.count > 0 &&
                                        ATTENTION.has(card.key) &&
                                        'text-destructive',
                                )}
                            >
                                {card.count}
                            </span>
                        </CardContent>
                    </Card>
                </Link>
            ))}
        </div>
    );
}

/** A plain horizontal bar list. No chart library: the numbers are the point. */
function Bars({
    title,
    rows,
    filterKey,
}: {
    title: string;
    rows: GroupCount[];
    filterKey?: 'category_id' | 'team_id';
}) {
    const { t } = useT();
    const max = Math.max(1, ...rows.map((row) => row.count));

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">{title}</CardTitle>
            </CardHeader>
            <CardContent>
                <ul className="space-y-3">
                    {rows.map((row) => {
                        const label = row.name ?? t('dashboard.no_team');
                        const body = (
                            <>
                                <div className="mb-1 flex justify-between gap-3 text-sm">
                                    <span className="truncate">{label}</span>
                                    <span className="font-medium tabular-nums">
                                        {row.count}
                                    </span>
                                </div>
                                <div
                                    className="bg-muted h-2 overflow-hidden rounded-full"
                                    aria-hidden
                                >
                                    <div
                                        className="bg-primary h-full rounded-full"
                                        style={{
                                            width: `${(row.count / max) * 100}%`,
                                        }}
                                    />
                                </div>
                            </>
                        );

                        return (
                            <li key={`${row.id}-${label}`}>
                                {filterKey && row.id !== null ? (
                                    <Link
                                        href={index({
                                            query: { [filterKey]: row.id },
                                        })}
                                        className="block rounded hover:opacity-80"
                                    >
                                        {body}
                                    </Link>
                                ) : (
                                    body
                                )}
                            </li>
                        );
                    })}
                    {rows.length === 0 && (
                        <li className="text-muted-foreground text-sm">—</li>
                    )}
                </ul>
            </CardContent>
        </Card>
    );
}

export default function Dashboard({ perspectives, data }: Props) {
    const { t } = useT();
    // The last perspective on offer is the most "senior" one for this person.
    const [active, setActive] = useState<Perspective>(
        perspectives[perspectives.length - 1] ?? 'requester',
    );

    const management = data.management;
    const hours = management?.avg_completion_hours ?? null;

    return (
        <>
            <Head title={t('dashboard.title')} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-5 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t('dashboard.title')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {t('dashboard.subtitle')}
                    </p>
                </div>

                {perspectives.length > 1 && (
                    <div
                        role="tablist"
                        className="bg-muted inline-flex w-fit gap-1 rounded-lg p-1"
                    >
                        {perspectives.map((perspective) => (
                            <button
                                key={perspective}
                                role="tab"
                                type="button"
                                aria-selected={active === perspective}
                                onClick={() => setActive(perspective)}
                                className={cn(
                                    'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                    active === perspective
                                        ? 'bg-background shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground',
                                )}
                            >
                                {t(`dashboard.tab.${perspective}`)}
                            </button>
                        ))}
                    </div>
                )}

                {active === 'requester' && data.requester && (
                    <CardGrid cards={data.requester.cards} />
                )}
                {active === 'staff' && data.staff && (
                    <CardGrid cards={data.staff.cards} />
                )}

                {active === 'management' && management && (
                    <>
                        <CardGrid cards={management.cards} />

                        <Card>
                            <CardHeader className="pb-1">
                                <CardTitle className="text-muted-foreground text-sm font-medium">
                                    {t('dashboard.avg_completion')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <span className="text-3xl font-semibold tabular-nums">
                                    {hours === null
                                        ? t('dashboard.avg_none')
                                        : hours >= 48
                                          ? t('dashboard.days', {
                                                n: (hours / 24).toFixed(1),
                                            })
                                          : t('dashboard.hours', { n: hours })}
                                </span>
                            </CardContent>
                        </Card>

                        <div className="grid gap-4 lg:grid-cols-3">
                            <Bars
                                title={t('dashboard.by_category')}
                                rows={management.by_category}
                                filterKey="category_id"
                            />
                            <Bars
                                title={t('dashboard.by_team')}
                                rows={management.by_team}
                                filterKey="team_id"
                            />
                            <Bars
                                title={t('dashboard.by_department')}
                                rows={management.by_department}
                            />
                        </div>
                    </>
                )}
            </div>
        </>
    );
}
