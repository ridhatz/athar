import { Head, router } from '@inertiajs/react';
import { CheckCircle2, RotateCcw } from 'lucide-react';
import { useMemo, useState } from 'react';
import { DiscGuide } from '@/components/athar/disc-guide';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useFormat } from '@/hooks/use-format';
import { useT } from '@/hooks/use-t';
import { cn } from '@/lib/utils';
import { store } from '@/routes/assessment';
import type {
    AssessmentAnswer,
    AssessmentQuestion,
    AssessmentRecommendationName,
    AssessmentResult,
    Paginated,
} from '@/types/athar';

type Props = {
    questions: AssessmentQuestion[];
    myLatest: AssessmentResult | null;
    canViewAll: boolean;
    all: Paginated<AssessmentResult> | null;
    stats: { eligible: number; submitted: number } | null;
};

const DIMENSIONS = ['D', 'I', 'S', 'C'] as const;

function recommendationTone(rec: AssessmentRecommendationName): string {
    switch (rec) {
        case 'SALES':
            return 'border-indigo-300 bg-indigo-50 text-indigo-800 dark:bg-indigo-950 dark:text-indigo-200';
        case 'MARKETING':
            return 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200';
        default:
            return 'border-amber-300 bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-200';
    }
}

/** A net score of -24..24 drawn as a bar from the midline, so negative reads as "behind zero". */
function ScoreBar({ dimension, value }: { dimension: string; value: number }) {
    const { t } = useT();
    const pct = Math.min(100, Math.max(0, ((value + 24) / 48) * 100));

    return (
        <div className="flex items-center gap-2">
            <span className="w-6 shrink-0 text-xs font-semibold">
                {dimension}
            </span>
            <div
                className="bg-muted relative h-2 flex-1 overflow-hidden rounded-full"
                role="progressbar"
                aria-label={t(`assessment.dimension.${dimension}`)}
                aria-valuemin={-24}
                aria-valuemax={24}
                aria-valuenow={value}
            >
                <div
                    className="bg-primary absolute inset-y-0 left-0 rounded-full transition-all"
                    style={{ width: `${pct}%` }}
                />
                <div className="bg-border absolute inset-y-0 left-1/2 w-px" />
            </div>
            <span className="w-8 shrink-0 text-right text-xs tabular-nums">
                {value}
            </span>
        </div>
    );
}

function ResultCard({
    result,
    onRetake,
}: {
    result: AssessmentResult;
    onRetake: () => void;
}) {
    const { t } = useT();
    const { date } = useFormat();

    return (
        <div className="space-y-4 rounded-lg border p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <Badge
                        variant="outline"
                        className={cn(
                            recommendationTone(result.recommendation),
                        )}
                    >
                        {t(
                            `assessment.recommendation.${result.recommendation}`,
                        )}
                    </Badge>
                    <p className="text-muted-foreground mt-1 text-xs">
                        {t(
                            `assessment.recommendation_hint.${result.recommendation}`,
                        )}
                    </p>
                </div>
                <Button variant="outline" size="sm" onClick={onRetake}>
                    <RotateCcw />
                    {t('assessment.retake')}
                </Button>
            </div>

            <div className="grid gap-2 sm:max-w-sm">
                {DIMENSIONS.map((dim) => (
                    <ScoreBar
                        key={dim}
                        dimension={dim}
                        value={result.scores[dim]}
                    />
                ))}
            </div>

            <p className="text-muted-foreground text-xs">
                {t('assessment.dominant_style')}:{' '}
                <span className="text-foreground font-medium">
                    {t(`assessment.dimension.${result.dominant_style}`)}
                </span>
                {' · '}
                {t('assessment.submitted_at', {
                    date: date(result.submitted_at),
                })}
            </p>
        </div>
    );
}

/** One of the 24 items: four traits, each row choosing into the Most or Least column. */
function QuestionRow({
    number,
    options,
    answer,
    onChange,
}: {
    number: number;
    options: AssessmentQuestion;
    answer: AssessmentAnswer;
    onChange: (next: AssessmentAnswer) => void;
}) {
    const { t } = useT();

    const pick = (column: 'most' | 'least', index: number) => {
        // Choosing a trait as Most clears it from Least (and vice versa) — the two can never be
        // the same trait, so the UI simply won't let that state exist rather than erroring later.
        const other = column === 'most' ? 'least' : 'most';
        onChange({
            ...answer,
            [column]: index,
            [other]: answer[other] === index ? null : answer[other],
        });
    };

    return (
        <div className="space-y-2 rounded-lg border p-3">
            <h3 className="text-sm font-semibold">
                {t('assessment.question_label', { number: String(number) })}
            </h3>
            <table className="w-full text-sm">
                <thead>
                    <tr className="text-muted-foreground text-xs">
                        <th className="w-1/2 py-1 text-left font-normal" />
                        <th className="w-1/4 py-1 text-center font-normal">
                            {t('assessment.col.most')}
                        </th>
                        <th className="w-1/4 py-1 text-center font-normal">
                            {t('assessment.col.least')}
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {options.map((word, index) => (
                        <tr key={index} className="border-t">
                            <td className="py-1.5">{word}</td>
                            <td className="py-1.5 text-center">
                                <button
                                    type="button"
                                    aria-pressed={answer.most === index}
                                    aria-label={`${t('assessment.col.most')}: ${word}`}
                                    onClick={() => pick('most', index)}
                                    className={cn(
                                        'size-5 rounded-full border-2 transition-colors',
                                        answer.most === index
                                            ? 'border-primary bg-primary'
                                            : 'border-muted-foreground/40 hover:border-primary',
                                    )}
                                />
                            </td>
                            <td className="py-1.5 text-center">
                                <button
                                    type="button"
                                    aria-pressed={answer.least === index}
                                    aria-label={`${t('assessment.col.least')}: ${word}`}
                                    onClick={() => pick('least', index)}
                                    className={cn(
                                        'size-5 rounded-full border-2 transition-colors',
                                        answer.least === index
                                            ? 'border-destructive bg-destructive'
                                            : 'border-muted-foreground/40 hover:border-destructive',
                                    )}
                                />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function AssessmentForm({
    questions,
    onDone,
    onCancel,
    cancellable,
}: {
    questions: AssessmentQuestion[];
    onDone: () => void;
    onCancel: () => void;
    cancellable: boolean;
}) {
    const { t } = useT();
    const [answers, setAnswers] = useState<AssessmentAnswer[]>(() =>
        questions.map(() => ({ most: null, least: null })),
    );
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const answered = answers.filter(
        (a) => a.most !== null && a.least !== null,
    ).length;
    const complete = answered === questions.length;

    const submit = () => {
        setProcessing(true);
        setError(null);
        router.post(
            store().url,
            { answers },
            {
                preserveScroll: true,
                onSuccess: () => onDone(),
                onError: (errors) =>
                    setError(Object.values(errors)[0] as string),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <div className="space-y-4">
            <p className="text-muted-foreground text-sm">
                {t('assessment.instruction')}
            </p>

            <div className="space-y-3">
                {questions.map((options, i) => (
                    <QuestionRow
                        key={i}
                        number={i + 1}
                        options={options}
                        answer={answers[i]}
                        onChange={(next) =>
                            setAnswers((current) =>
                                current.map((a, idx) => (idx === i ? next : a)),
                            )
                        }
                    />
                ))}
            </div>

            {error && <p className="text-destructive text-sm">{error}</p>}

            <div className="bg-background sticky bottom-0 flex items-center justify-between gap-3 border-t py-3">
                <span className="text-muted-foreground text-sm">
                    {t('assessment.progress', {
                        answered: String(answered),
                        total: String(questions.length),
                    })}
                </span>
                <div className="flex gap-2">
                    {cancellable && (
                        <Button variant="ghost" onClick={onCancel}>
                            {t('assessment.cancel_retake')}
                        </Button>
                    )}
                    <Button disabled={!complete || processing} onClick={submit}>
                        {processing && <Spinner />}
                        <CheckCircle2 />
                        {t('assessment.submit')}
                    </Button>
                </div>
            </div>
        </div>
    );
}

function AdminRoster({
    all,
    stats,
}: {
    all: Paginated<AssessmentResult>;
    stats: { eligible: number; submitted: number };
}) {
    const { t } = useT();
    const { date } = useFormat();

    return (
        <div className="space-y-3">
            <div>
                <h2 className="text-lg font-semibold">
                    {t('assessment.admin.title')}
                </h2>
                <p className="text-muted-foreground text-sm">
                    {t('assessment.admin.subtitle')}
                </p>
                <p className="text-muted-foreground mt-1 text-xs">
                    {t('assessment.admin.stats', {
                        submitted: String(stats.submitted),
                        eligible: String(stats.eligible),
                    })}
                </p>
            </div>

            <div className="rounded-lg border">
                <Table>
                    <TableHeader>
                        <TableRow className="hover:bg-transparent">
                            <TableHead>
                                {t('assessment.admin.col.name')}
                            </TableHead>
                            <TableHead>
                                {t('assessment.admin.col.scores')}
                            </TableHead>
                            <TableHead>
                                {t('assessment.admin.col.recommendation')}
                            </TableHead>
                            <TableHead>
                                {t('assessment.admin.col.submitted_at')}
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {all.data.map((result) => (
                            <TableRow key={result.id}>
                                <TableCell className="font-medium">
                                    {result.user?.name ?? '—'}
                                </TableCell>
                                <TableCell className="text-muted-foreground font-mono text-xs">
                                    {DIMENSIONS.map(
                                        (d) =>
                                            `${d}${result.scores[d] >= 0 ? '+' : ''}${result.scores[d]}`,
                                    ).join('  ')}
                                </TableCell>
                                <TableCell>
                                    <Badge
                                        variant="outline"
                                        className={cn(
                                            recommendationTone(
                                                result.recommendation,
                                            ),
                                        )}
                                    >
                                        {t(
                                            `assessment.recommendation.${result.recommendation}`,
                                        )}
                                    </Badge>
                                </TableCell>
                                <TableCell className="text-sm">
                                    {date(result.submitted_at)}
                                </TableCell>
                            </TableRow>
                        ))}
                        {all.data.length === 0 && (
                            <TableRow className="hover:bg-transparent">
                                <TableCell
                                    colSpan={4}
                                    className="text-muted-foreground py-10 text-center"
                                >
                                    {t('assessment.admin.empty')}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>
        </div>
    );
}

export default function AssessmentIndex({
    questions,
    myLatest,
    canViewAll,
    all,
    stats,
}: Props) {
    const { t } = useT();
    const [showForm, setShowForm] = useState(myLatest === null);

    // The form is uncontrolled React state that resets on remount, so swapping it out after a
    // successful submit (rather than just hiding it) guarantees a retake never starts prefilled
    // with the previous answers.
    const formKey = useMemo(
        () => myLatest?.id ?? 'new',
        [myLatest?.id, showForm],
    );

    return (
        <>
            <Head title={t('assessment.title')} />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        {t('assessment.title')}
                    </h1>
                    <p className="text-muted-foreground text-sm">
                        {t('assessment.subtitle')}
                    </p>
                </div>

                {!showForm && myLatest && (
                    <ResultCard
                        result={myLatest}
                        onRetake={() => setShowForm(true)}
                    />
                )}

                {!showForm && !myLatest && (
                    <div className="rounded-lg border border-dashed p-6 text-center">
                        <p className="text-muted-foreground text-sm">
                            {t('assessment.not_submitted_yet')}
                        </p>
                        <Button
                            className="mt-3"
                            onClick={() => setShowForm(true)}
                        >
                            {t('assessment.take_now')}
                        </Button>
                    </div>
                )}

                {showForm && (
                    <AssessmentForm
                        key={formKey}
                        questions={questions}
                        cancellable={myLatest !== null}
                        onDone={() => setShowForm(false)}
                        onCancel={() => setShowForm(false)}
                    />
                )}

                {canViewAll && all && stats && (
                    <div className="space-y-4 border-t pt-6">
                        <DiscGuide />
                        <AdminRoster all={all} stats={stats} />
                    </div>
                )}
            </div>
        </>
    );
}
