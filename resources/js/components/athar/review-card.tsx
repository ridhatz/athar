import { router } from '@inertiajs/react';
import { Check, RotateCcw } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useFormat } from '@/hooks/use-format';
import { useT } from '@/hooks/use-t';
import { cn } from '@/lib/utils';
import { review as reviewRoute } from '@/routes/requests';
import type { RequestDetail, ReviewEntry } from '@/types/athar';

const DECISION_STYLE: Record<string, string> = {
    PENDING:
        'bg-violet-100 text-violet-900 border-violet-300 dark:bg-violet-950 dark:text-violet-200',
    APPROVED:
        'bg-emerald-100 text-emerald-900 border-emerald-300 dark:bg-emerald-950 dark:text-emerald-200',
    REVISION_REQUESTED:
        'bg-amber-100 text-amber-900 border-amber-300 dark:bg-amber-950 dark:text-amber-200',
};

/** Every review round, and the approve / request-revision controls for the reviewer. */
export function ReviewCard({
    request,
    reviews,
}: {
    request: RequestDetail;
    reviews: ReviewEntry[];
}) {
    const { t } = useT();
    const format = useFormat();
    const [mode, setMode] = useState<'APPROVED' | 'REVISION_REQUESTED' | null>(
        null,
    );
    const [note, setNote] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const canDecide = request.permissions.review_decision;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        if (!mode) return;
        setProcessing(true);
        router.post(
            reviewRoute({ serviceRequest: request.request_number }).url,
            { decision: mode, note: note || null },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setMode(null);
                    setNote('');
                    setErrors({});
                },
                onError: (serverErrors) => setErrors(serverErrors),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <div className="space-y-4">
            {reviews.length === 0 ? (
                <p className="text-muted-foreground text-sm">
                    {t('review.none')}
                </p>
            ) : (
                <ol className="space-y-3">
                    {[...reviews].reverse().map((entry) => (
                        <li key={entry.id} className="text-sm">
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="font-medium">
                                    {t('review.round', { n: entry.round })}
                                </span>
                                <Badge
                                    variant="outline"
                                    className={cn(
                                        DECISION_STYLE[entry.decision],
                                    )}
                                >
                                    {t(`review.decision.${entry.decision}`)}
                                </Badge>
                            </div>
                            <div className="text-muted-foreground text-xs">
                                {entry.decided_at
                                    ? `${format.dateTime(entry.decided_at)}${entry.reviewer ? ` · ${t('review.by', { name: entry.reviewer.name })}` : ''}`
                                    : t('review.pending')}
                            </div>
                            {entry.decision_note && (
                                <blockquote className="text-muted-foreground mt-1 border-l-2 pl-3 italic">
                                    {entry.decision_note}
                                </blockquote>
                            )}
                        </li>
                    ))}
                </ol>
            )}

            {canDecide && (
                <div className="flex flex-col gap-2 border-t pt-4">
                    <Button onClick={() => setMode('APPROVED')}>
                        <Check />
                        {t('review.approve')}
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() => setMode('REVISION_REQUESTED')}
                    >
                        <RotateCcw />
                        {t('review.request_revision')}
                    </Button>
                </div>
            )}

            <Dialog
                open={mode !== null}
                onOpenChange={(open) => !open && setMode(null)}
            >
                <DialogContent>
                    <form onSubmit={submit} className="space-y-4">
                        <DialogHeader>
                            <DialogTitle>
                                {mode === 'APPROVED'
                                    ? t('review.approve_title')
                                    : t('review.revision_title')}
                            </DialogTitle>
                            <DialogDescription>
                                {mode === 'APPROVED'
                                    ? t('review.approve_hint')
                                    : t('review.revision_hint')}
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-2">
                            <Label htmlFor="review-note">
                                {t('review.note_label')}
                                {mode === 'APPROVED' && (
                                    <span className="text-muted-foreground font-normal">
                                        {' '}
                                        ({t('common.optional')})
                                    </span>
                                )}
                            </Label>
                            <Textarea
                                id="review-note"
                                value={note}
                                onChange={(event) =>
                                    setNote(event.target.value)
                                }
                                maxLength={2000}
                                autoFocus
                            />
                            <InputError
                                message={errors.note ?? errors.decision}
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setMode(null)}
                            >
                                {t('common.cancel')}
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                {mode === 'APPROVED'
                                    ? t('review.approve')
                                    : t('review.request_revision')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}
