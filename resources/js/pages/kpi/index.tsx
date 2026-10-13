import { Head, router } from '@inertiajs/react';
import { Check, Eye, RotateCcw, Search, Sparkles } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useT } from '@/hooks/use-t';
import { cn } from '@/lib/utils';
import { fill, generate, index, review, submit } from '@/routes/kpi';
import type { Kpi, KpiStatusName, NamedRef, Paginated } from '@/types/athar';

type Props = {
    kpis: Paginated<Kpi>;
    filters: { q: string; status: string; period: string };
    statuses: KpiStatusName[];
    periods: string[];
    canCreate: boolean;
    employees: NamedRef[];
};

const ALL = 'all';

/** Green once a target is met, amber while it is short. Always paired with the number itself. */
function scoreTone(score: number | null): string {
    if (score === null) return 'text-muted-foreground';

    return score >= 100
        ? 'text-emerald-700 dark:text-emerald-300'
        : score >= 80
          ? 'text-amber-700 dark:text-amber-300'
          : 'text-rose-700 dark:text-rose-300';
}

function statusTone(status: KpiStatusName): string {
    switch (status) {
        case 'REVIEWED':
            return 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200';
        case 'SUBMITTED':
            return 'border-indigo-300 bg-indigo-50 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-200';
        case 'REVISION':
            return 'border-rose-300 bg-rose-50 text-rose-800 dark:bg-rose-950 dark:text-rose-200';
        default:
            return 'border-amber-300 bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-200';
    }
}

/** Month name for a "2026-09" key, in the reader's language. */
function periodLabel(period: string, locale: string): string {
    const [year, month] = period.split('-').map(Number);

    return new Date(year, month - 1, 1).toLocaleDateString(
        locale === 'en' ? 'en-GB' : 'id-ID',
        { month: 'long', year: 'numeric' },
    );
}

/**
 * The sheet itself: the table of KPIs, the achievement boxes when it is yours to fill, and the
 * approve / send-back buttons when it is yours to sign off.
 */
function KpiDetailDialog({ kpi, onClose }: { kpi: Kpi; onClose: () => void }) {
    const { t, locale } = useT();
    const canFill = kpi.can.fill === true;

    const [values, setValues] = useState<Record<number, string>>(() =>
        Object.fromEntries(
            kpi.items.map((item) => [
                item.id,
                item.achievement === null ? '' : String(item.achievement),
            ]),
        ),
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [returning, setReturning] = useState(false);
    const [note, setNote] = useState('');

    const run = (call: () => void) => {
        setProcessing(true);
        setErrors({});
        call();
    };

    const options = {
        preserveScroll: true,
        onError: (serverErrors: Record<string, string>) =>
            setErrors(serverErrors),
        onFinish: () => setProcessing(false),
    };

    const save = () =>
        run(() =>
            router.patch(
                fill({ kpi: kpi.id }).url,
                {
                    achievements: Object.fromEntries(
                        Object.entries(values).map(([id, v]) => [
                            id,
                            v === '' ? null : Number(v),
                        ]),
                    ),
                },
                options,
            ),
        );

    const sendUp = () =>
        run(() =>
            router.post(
                submit({ kpi: kpi.id }).url,
                {},
                {
                    ...options,
                    onSuccess: () => onClose(),
                },
            ),
        );

    const decide = (approved: boolean) =>
        run(() =>
            router.post(
                review({ kpi: kpi.id }).url,
                { approved, review_note: note },
                { ...options, onSuccess: () => onClose() },
            ),
        );

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-4xl">
                <DialogHeader>
                    <DialogTitle>
                        {periodLabel(kpi.period, locale)}
                        {kpi.user ? ` — ${kpi.user.name}` : ''}
                    </DialogTitle>
                </DialogHeader>

                <div className="flex flex-wrap items-center gap-2">
                    <Badge
                        variant="outline"
                        className={cn(statusTone(kpi.status))}
                    >
                        {t(`kpi.status.${kpi.status}`)}
                    </Badge>
                    <Badge variant="outline">
                        {kpi.final_score === null
                            ? t('kpi.detail.no_score')
                            : t('kpi.detail.score', {
                                  score: String(kpi.final_score),
                              })}
                    </Badge>
                </div>

                {kpi.review_note && (
                    <div className="rounded-md border border-rose-200 bg-rose-50 p-3 text-sm dark:border-rose-900 dark:bg-rose-950">
                        <div className="font-medium">
                            {t('kpi.detail.note')}
                        </div>
                        <p className="text-muted-foreground">
                            {kpi.review_note}
                        </p>
                        {kpi.reviewer && (
                            <p className="text-muted-foreground mt-1 text-xs">
                                {t('kpi.detail.reviewed_by', {
                                    name: kpi.reviewer,
                                })}
                            </p>
                        )}
                    </div>
                )}

                <h3 className="text-sm font-semibold">
                    {t('kpi.detail.list')}
                </h3>

                <div className="overflow-x-auto rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>{t('kpi.item.name')}</TableHead>
                                <TableHead>{t('kpi.item.target')}</TableHead>
                                <TableHead>
                                    {t('kpi.item.assessment')}
                                </TableHead>
                                <TableHead className="w-20">
                                    {t('kpi.item.weight')}
                                </TableHead>
                                <TableHead className="w-36">
                                    {t('kpi.item.achievement')}
                                </TableHead>
                                <TableHead className="w-20">
                                    {t('kpi.item.score')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {kpi.items.map((item) => (
                                <TableRow key={item.id}>
                                    <TableCell className="max-w-72 text-sm">
                                        {item.name}
                                        <div className="text-muted-foreground text-xs">
                                            {t(
                                                `kpi.direction.${item.direction}`,
                                            )}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-sm whitespace-nowrap">
                                        {item.target_label}
                                    </TableCell>
                                    <TableCell>
                                        <Badge variant="secondary">
                                            {t(
                                                `kpi.frequency.${item.frequency}`,
                                            )}
                                        </Badge>
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        {item.weight}
                                    </TableCell>
                                    <TableCell>
                                        {canFill ? (
                                            <Input
                                                type="number"
                                                step="any"
                                                value={values[item.id] ?? ''}
                                                aria-label={`${t('kpi.item.achievement')} ${item.name}`}
                                                onChange={(e) =>
                                                    setValues((current) => ({
                                                        ...current,
                                                        [item.id]:
                                                            e.target.value,
                                                    }))
                                                }
                                            />
                                        ) : (
                                            <span className="text-sm">
                                                {item.achievement_label}
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell
                                        className={cn(
                                            'text-sm font-semibold',
                                            scoreTone(
                                                item.score === null
                                                    ? null
                                                    : (item.score /
                                                          item.weight) *
                                                          100,
                                            ),
                                        )}
                                    >
                                        {item.score ?? '—'}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <InputError message={errors.achievements ?? errors.status} />

                {kpi.can.review && (
                    <div className="space-y-2">
                        <Label htmlFor="kpi-note">
                            {t('kpi.return_reason')}
                        </Label>
                        <Textarea
                            id="kpi-note"
                            rows={2}
                            value={note}
                            onChange={(e) => {
                                setNote(e.target.value);
                                setReturning(true);
                            }}
                        />
                        <p className="text-muted-foreground text-xs">
                            {t('kpi.return_hint')}
                        </p>
                        <InputError message={errors.review_note} />
                    </div>
                )}

                <DialogFooter>
                    <Button variant="ghost" onClick={onClose}>
                        {t('common.cancel')}
                    </Button>

                    {canFill && (
                        <>
                            <Button
                                variant="outline"
                                disabled={processing}
                                onClick={save}
                            >
                                {processing && <Spinner />}
                                {t('common.save')}
                            </Button>
                            <Button disabled={processing} onClick={sendUp}>
                                {t('kpi.action.submit')}
                            </Button>
                        </>
                    )}

                    {kpi.can.review && (
                        <>
                            <Button
                                variant="outline"
                                disabled={processing || !returning}
                                onClick={() => decide(false)}
                            >
                                <RotateCcw />
                                {t('kpi.action.return')}
                            </Button>
                            <Button
                                disabled={processing}
                                onClick={() => decide(true)}
                            >
                                <Check />
                                {t('kpi.action.approve')}
                            </Button>
                        </>
                    )}
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

/** Opens one month of sheets for everybody from their team's template. */
function GenerateDialog({ onClose }: { onClose: () => void }) {
    const { t } = useT();
    const [period, setPeriod] = useState(new Date().toISOString().slice(0, 7));
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{t('kpi.generate_title')}</DialogTitle>
                </DialogHeader>

                <div className="space-y-2">
                    <Label htmlFor="generate-period">
                        {t('kpi.form.period')}
                    </Label>
                    <Input
                        id="generate-period"
                        type="month"
                        value={period}
                        onChange={(e) => setPeriod(e.target.value)}
                    />
                    <p className="text-muted-foreground text-xs">
                        {t('kpi.generate_hint')}
                    </p>
                    <InputError message={errors.period} />
                </div>

                <DialogFooter>
                    <Button variant="ghost" onClick={onClose}>
                        {t('common.cancel')}
                    </Button>
                    <Button
                        disabled={processing}
                        onClick={() => {
                            setProcessing(true);
                            router.post(
                                generate().url,
                                { period },
                                {
                                    preserveScroll: true,
                                    onSuccess: () => onClose(),
                                    onError: setErrors,
                                    onFinish: () => setProcessing(false),
                                },
                            );
                        }}
                    >
                        {processing && <Spinner />}
                        <Sparkles />
                        {t('kpi.generate')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function KpiIndex({
    kpis,
    filters,
    statuses,
    periods,
    canCreate,
}: Props) {
    const { t, locale } = useT();

    const [search, setSearch] = useState(filters.q);
    const [open, setOpen] = useState<Kpi | null>(null);
    const [generating, setGenerating] = useState(false);

    const apply = (next: Partial<typeof filters>) => {
        const merged = { ...filters, ...next };
        const query: Record<string, string> = {};
        if (merged.q) query.q = merged.q;
        if (merged.status) query.status = merged.status;
        if (merged.period) query.period = merged.period;
        router.get(index().url, query, { preserveState: true, replace: true });
    };

    return (
        <>
            <Head title={t('kpi.title')} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('kpi.title')}
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {t('kpi.subtitle')}
                        </p>
                    </div>
                    {canCreate && (
                        <Button
                            variant="outline"
                            onClick={() => setGenerating(true)}
                        >
                            <Sparkles />
                            {t('kpi.generate')}
                        </Button>
                    )}
                </div>

                <form
                    className="flex flex-wrap items-center gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        apply({ q: search });
                    }}
                >
                    <div className="relative min-w-56 flex-1 sm:max-w-xs">
                        <Search
                            className="text-muted-foreground pointer-events-none absolute top-2.5 left-2.5 size-4"
                            aria-hidden
                        />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder={t('kpi.search')}
                            aria-label={t('kpi.search')}
                            className="pl-8"
                        />
                    </div>

                    <Select
                        value={filters.status || ALL}
                        onValueChange={(v) =>
                            apply({ status: v === ALL ? '' : v })
                        }
                    >
                        <SelectTrigger
                            className="w-48"
                            aria-label={t('kpi.col.status')}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>
                                {t('kpi.all_status')}
                            </SelectItem>
                            {statuses.map((s) => (
                                <SelectItem key={s} value={s}>
                                    {t(`kpi.status.${s}`)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    {periods.length > 0 && (
                        <Select
                            value={filters.period || ALL}
                            onValueChange={(v) =>
                                apply({ period: v === ALL ? '' : v })
                            }
                        >
                            <SelectTrigger
                                className="w-48"
                                aria-label={t('kpi.col.period')}
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={ALL}>
                                    {t('kpi.all_period')}
                                </SelectItem>
                                {periods.map((p) => (
                                    <SelectItem key={p} value={p}>
                                        {periodLabel(p, locale)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                </form>

                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>{t('kpi.col.employee')}</TableHead>
                                <TableHead>{t('kpi.col.period')}</TableHead>
                                <TableHead className="w-40">
                                    {t('kpi.col.progress')}
                                </TableHead>
                                <TableHead className="w-28">
                                    {t('kpi.col.score')}
                                </TableHead>
                                <TableHead className="w-36">
                                    {t('kpi.col.status')}
                                </TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {kpis.data.map((kpi) => (
                                <TableRow key={kpi.id}>
                                    <TableCell>
                                        <div className="font-medium">
                                            {kpi.user?.name ?? '—'}
                                        </div>
                                        <div className="text-muted-foreground text-xs">
                                            {kpi.user?.teams.join(', ') ||
                                                kpi.user?.department ||
                                                '—'}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        <div className="font-medium">
                                            {periodLabel(kpi.period, locale)}
                                        </div>
                                        {kpi.due_date && (
                                            <div className="text-muted-foreground text-xs">
                                                {t('kpi.due', {
                                                    date: kpi.due_date,
                                                })}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <div
                                            className="bg-muted h-2 w-full overflow-hidden rounded-full"
                                            role="progressbar"
                                            aria-valuenow={kpi.progress}
                                            aria-valuemin={0}
                                            aria-valuemax={100}
                                        >
                                            <div
                                                className="bg-primary h-full rounded-full transition-all"
                                                style={{
                                                    width: `${kpi.progress}%`,
                                                }}
                                            />
                                        </div>
                                        <div className="text-muted-foreground mt-1 text-xs">
                                            {kpi.progress}%
                                        </div>
                                    </TableCell>
                                    <TableCell
                                        className={cn(
                                            'text-sm font-semibold',
                                            scoreTone(kpi.final_score),
                                        )}
                                    >
                                        {kpi.final_score === null
                                            ? '—'
                                            : `${kpi.final_score}%`}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant="outline"
                                            className={cn(
                                                statusTone(kpi.status),
                                            )}
                                        >
                                            {t(`kpi.status.${kpi.status}`)}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`${t('kpi.action.view')} ${kpi.user?.name ?? ''}`}
                                                title={t('kpi.action.view')}
                                                onClick={() => setOpen(kpi)}
                                            >
                                                <Eye />
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {kpis.data.length === 0 && (
                                <TableRow className="hover:bg-transparent">
                                    <TableCell
                                        colSpan={6}
                                        className="text-muted-foreground py-12 text-center"
                                    >
                                        {t('kpi.empty')}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>

            {/* Keyed so reopening a sheet always starts from its current numbers. */}
            {open && (
                <KpiDetailDialog
                    key={open.id}
                    kpi={open}
                    onClose={() => setOpen(null)}
                />
            )}

            {generating && (
                <GenerateDialog onClose={() => setGenerating(false)} />
            )}
        </>
    );
}
