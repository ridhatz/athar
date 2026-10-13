import { Head, router } from '@inertiajs/react';
import {
    Activity,
    CheckCircle2,
    Clock,
    LineChart,
    TrendingDown,
    TrendingUp,
    Trophy,
    Users,
} from 'lucide-react';
import type { ComponentType } from 'react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useT } from '@/hooks/use-t';
import { analysis as index } from '@/routes/kpi';
import type {
    KpiDistributionSlice,
    KpiStatusName,
    KpiTrendPoint,
    NamedRef,
} from '@/types/athar';

type Props = {
    filters: {
        year: number;
        employee: number | '';
        department: number | '';
        period: string;
    };
    stats: {
        total: number;
        people: number;
        average: number;
        highest: number;
        lowest: number;
        pending: number;
        awaiting_review: number;
        reviewed: number;
        revision: number;
    };
    trend: KpiTrendPoint[];
    distribution: KpiDistributionSlice[];
    years: number[];
    employees: NamedRef[];
    departments: NamedRef[];
};

const ALL = 'all';

/**
 * Status colours, as CSS variables rather than fixed hex, so the charts follow the light/dark
 * theme the same way the rest of the UI does.
 */
const STATUS_COLOR: Record<KpiStatusName, string> = {
    PENDING: 'var(--color-amber-500, #f59e0b)',
    SUBMITTED: 'var(--color-indigo-500, #6366f1)',
    REVIEWED: 'var(--color-emerald-500, #10b981)',
    REVISION: 'var(--color-rose-500, #f43f5e)',
};

function StatCard({
    label,
    value,
    icon: Icon,
}: {
    label: string;
    value: string;
    icon: ComponentType<{ className?: string }>;
}) {
    return (
        <div className="rounded-lg border p-4">
            <div className="flex items-start justify-between gap-2">
                <span className="text-muted-foreground text-sm">{label}</span>
                <Icon className="text-muted-foreground size-4" />
            </div>
            <div className="mt-2 text-2xl font-semibold tracking-tight">
                {value}
            </div>
        </div>
    );
}

/**
 * Average score per month, drawn by hand.
 *
 * Months nobody was scored in are gaps, not zeroes — a line dropping to the floor would read as
 * "everyone failed" when it means "nothing recorded yet". So the path breaks and resumes.
 */
function TrendChart({ points }: { points: KpiTrendPoint[] }) {
    const { t, locale } = useT();
    const width = 720;
    const height = 220;
    const padding = { top: 16, right: 16, bottom: 28, left: 40 };

    const values = points
        .map((p) => p.average)
        .filter((v): v is number => v !== null);

    if (values.length === 0) {
        return (
            <p className="text-muted-foreground py-12 text-center text-sm">
                {t('kpi.analysis.no_data')}
            </p>
        );
    }

    const max = Math.max(...values, 100) * 1.1;
    const innerW = width - padding.left - padding.right;
    const innerH = height - padding.top - padding.bottom;

    const x = (i: number) =>
        padding.left + (innerW * i) / Math.max(points.length - 1, 1);
    const y = (v: number) => padding.top + innerH - (innerH * v) / max;

    // Each run of consecutive recorded months becomes its own path.
    const segments: string[] = [];
    let current: string[] = [];
    points.forEach((p, i) => {
        if (p.average === null) {
            if (current.length > 0) segments.push(current.join(' '));
            current = [];

            return;
        }
        current.push(
            `${current.length === 0 ? 'M' : 'L'}${x(i)},${y(p.average)}`,
        );
    });
    if (current.length > 0) segments.push(current.join(' '));

    const monthName = (m: number) =>
        new Date(2000, m - 1, 1).toLocaleDateString(
            locale === 'en' ? 'en-GB' : 'id-ID',
            { month: 'short' },
        );

    const ticks = [0, max / 2, max];

    return (
        <svg
            viewBox={`0 0 ${width} ${height}`}
            className="h-56 w-full"
            role="img"
            aria-label={t('kpi.analysis.trend', { year: '' })}
        >
            {ticks.map((v) => (
                <g key={v}>
                    <line
                        x1={padding.left}
                        x2={width - padding.right}
                        y1={y(v)}
                        y2={y(v)}
                        stroke="currentColor"
                        strokeOpacity={0.12}
                        strokeDasharray="3 3"
                    />
                    <text
                        x={padding.left - 8}
                        y={y(v) + 4}
                        textAnchor="end"
                        className="fill-current text-[10px] opacity-50"
                    >
                        {Math.round(v)}
                    </text>
                </g>
            ))}

            {segments.map((d, i) => (
                <path
                    key={i}
                    d={d}
                    fill="none"
                    stroke="var(--color-indigo-500, #6366f1)"
                    strokeWidth={2}
                    strokeLinejoin="round"
                />
            ))}

            {points.map((p, i) =>
                p.average === null ? null : (
                    <g key={p.month}>
                        <circle
                            cx={x(i)}
                            cy={y(p.average)}
                            r={3.5}
                            fill="var(--color-indigo-500, #6366f1)"
                        />
                        <title>{`${monthName(p.month)}: ${p.average}`}</title>
                    </g>
                ),
            )}

            {points.map((p, i) => (
                <text
                    key={p.month}
                    x={x(i)}
                    y={height - 8}
                    textAnchor="middle"
                    className="fill-current text-[10px] opacity-50"
                >
                    {monthName(p.month)}
                </text>
            ))}
        </svg>
    );
}

/** Status mix as a donut, drawn with stroke-dasharray so there is no path arithmetic to get wrong. */
function DonutChart({ slices }: { slices: KpiDistributionSlice[] }) {
    const { t } = useT();
    const total = slices.reduce((sum, s) => sum + s.total, 0);

    if (total === 0) {
        return (
            <p className="text-muted-foreground py-12 text-center text-sm">
                {t('kpi.analysis.no_data')}
            </p>
        );
    }

    const radius = 60;
    const circumference = 2 * Math.PI * radius;
    let offset = 0;

    return (
        <div className="flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
            <svg
                viewBox="0 0 160 160"
                className="size-40"
                role="img"
                aria-label={t('kpi.analysis.distribution')}
            >
                <g transform="rotate(-90 80 80)">
                    {slices.map((slice) => {
                        const length = (slice.total / total) * circumference;
                        const dash = `${length} ${circumference - length}`;
                        const element = (
                            <circle
                                key={slice.status}
                                cx={80}
                                cy={80}
                                r={radius}
                                fill="none"
                                stroke={STATUS_COLOR[slice.status]}
                                strokeWidth={20}
                                strokeDasharray={dash}
                                strokeDashoffset={-offset}
                            />
                        );
                        offset += length;

                        return element;
                    })}
                </g>
                <text
                    x={80}
                    y={78}
                    textAnchor="middle"
                    className="fill-current text-xl font-semibold"
                >
                    {total}
                </text>
                <text
                    x={80}
                    y={95}
                    textAnchor="middle"
                    className="fill-current text-[10px] opacity-60"
                >
                    {t('kpi.analysis.total_label')}
                </text>
            </svg>

            <ul className="space-y-1 text-sm">
                {slices.map((slice) => (
                    <li key={slice.status} className="flex items-center gap-2">
                        <span
                            className="size-3 rounded-sm"
                            style={{
                                background: STATUS_COLOR[slice.status],
                            }}
                            aria-hidden
                        />
                        <span>{t(`kpi.status.${slice.status}`)}</span>
                        <span className="text-muted-foreground">
                            {slice.total}
                        </span>
                    </li>
                ))}
            </ul>
        </div>
    );
}

export default function KpiAnalysis({
    filters,
    stats,
    trend,
    distribution,
    years,
    employees,
    departments,
}: Props) {
    const { t } = useT();

    const apply = (next: Partial<Record<string, string>>) => {
        const merged: Record<string, string> = {
            year: String(filters.year),
            employee: filters.employee === '' ? '' : String(filters.employee),
            department:
                filters.department === '' ? '' : String(filters.department),
            period: filters.period,
            ...next,
        };
        const query = Object.fromEntries(
            Object.entries(merged).filter(([, v]) => v !== ''),
        );
        router.get(index().url, query, { preserveState: true, replace: true });
    };

    return (
        <>
            <Head title={t('kpi.analysis.title')} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-5 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t('kpi.analysis.title')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {t('kpi.analysis.subtitle')}
                    </p>
                </div>

                <div className="grid gap-3 sm:grid-cols-3">
                    <Select
                        value={
                            filters.employee === ''
                                ? ALL
                                : String(filters.employee)
                        }
                        onValueChange={(v) =>
                            apply({ employee: v === ALL ? '' : v })
                        }
                    >
                        <SelectTrigger aria-label={t('kpi.col.employee')}>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>
                                {t('kpi.analysis.all_employees')}
                            </SelectItem>
                            {employees.map((e) => (
                                <SelectItem key={e.id} value={String(e.id)}>
                                    {e.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={
                            filters.department === ''
                                ? ALL
                                : String(filters.department)
                        }
                        onValueChange={(v) =>
                            apply({ department: v === ALL ? '' : v })
                        }
                    >
                        <SelectTrigger
                            aria-label={t('kpi.analysis.department')}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>
                                {t('kpi.analysis.all_departments')}
                            </SelectItem>
                            {departments.map((d) => (
                                <SelectItem key={d.id} value={String(d.id)}>
                                    {d.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={String(filters.year)}
                        onValueChange={(v) => apply({ year: v })}
                    >
                        <SelectTrigger aria-label={t('kpi.analysis.year')}>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {years.map((y) => (
                                <SelectItem key={y} value={String(y)}>
                                    {y}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <StatCard
                        label={t('kpi.analysis.total')}
                        value={String(stats.total)}
                        icon={Activity}
                    />
                    <StatCard
                        label={t('kpi.analysis.people')}
                        value={String(stats.people)}
                        icon={Users}
                    />
                    <StatCard
                        label={t('kpi.analysis.average')}
                        value={`${stats.average}%`}
                        icon={TrendingUp}
                    />
                    <StatCard
                        label={t('kpi.analysis.highest')}
                        value={`${stats.highest}%`}
                        icon={Trophy}
                    />
                    <StatCard
                        label={t('kpi.analysis.lowest')}
                        value={`${stats.lowest}%`}
                        icon={TrendingDown}
                    />
                    <StatCard
                        label={t('kpi.analysis.pending')}
                        value={String(stats.pending)}
                        icon={Clock}
                    />
                    <StatCard
                        label={t('kpi.analysis.awaiting')}
                        value={String(stats.awaiting_review)}
                        icon={LineChart}
                    />
                    <StatCard
                        label={t('kpi.analysis.reviewed')}
                        value={String(stats.reviewed)}
                        icon={CheckCircle2}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
                    <div className="rounded-lg border p-4 lg:col-span-2">
                        <h2 className="mb-2 text-sm font-semibold">
                            {t('kpi.analysis.trend', {
                                year: String(filters.year),
                            })}
                        </h2>
                        <TrendChart points={trend} />
                    </div>
                    <div className="rounded-lg border p-4">
                        <h2 className="mb-2 text-sm font-semibold">
                            {t('kpi.analysis.distribution')}
                        </h2>
                        <DonutChart slices={distribution} />
                    </div>
                </div>
            </div>
        </>
    );
}
