import { router } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
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
import { useT } from '@/hooks/use-t';
import { status as statusRoute } from '@/routes/requests';
import type { AllowedTransition, RequestDetail } from '@/types/athar';

/**
 * Buttons for the moves the SERVER says the caller may make right now (allowed_transitions).
 * No workflow rule lives here: if the server changes the rules, the buttons follow.
 */
export function StatusActions({ request }: { request: RequestDetail }) {
    const { t } = useT();
    const [pending, setPending] = useState<AllowedTransition | null>(null);
    const [note, setNote] = useState('');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const label = (transition: AllowedTransition) =>
        request.status === 'BLOCKED' && transition.status !== 'CANCELLED'
            ? t('action.resume')
            : t(`action.${transition.status}`);

    const submit = (transition: AllowedTransition, reason: string) => {
        setProcessing(true);
        router.post(
            statusRoute({ serviceRequest: request.request_number }).url,
            { to_status: transition.status, note: reason || null },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setPending(null);
                    setNote('');
                    setErrors({});
                },
                onError: (serverErrors) => setErrors(serverErrors),
                onFinish: () => setProcessing(false),
            },
        );
    };

    const choose = (transition: AllowedTransition) => {
        setErrors({});

        if (transition.requires_note) {
            setNote('');
            setPending(transition);
        } else {
            submit(transition, '');
        }
    };

    if (request.allowed_transitions.length === 0) {
        return (
            <p className="text-muted-foreground text-sm">
                {t('show.no_actions')}
            </p>
        );
    }

    return (
        <>
            <div className="flex flex-col gap-2">
                {request.allowed_transitions.map((transition) => (
                    <Button
                        key={transition.status}
                        variant={
                            transition.status === 'CANCELLED'
                                ? 'outline'
                                : transition.status === 'BLOCKED'
                                  ? 'outline'
                                  : 'default'
                        }
                        className={
                            transition.status === 'CANCELLED'
                                ? 'text-destructive hover:text-destructive'
                                : undefined
                        }
                        disabled={processing}
                        onClick={() => choose(transition)}
                    >
                        {processing && !pending && <Spinner />}
                        {label(transition)}
                    </Button>
                ))}
                {!pending && errors.to_status && (
                    <InputError message={errors.to_status} />
                )}
            </div>

            <Dialog
                open={pending !== null}
                onOpenChange={(open) => !open && setPending(null)}
            >
                <DialogContent>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            if (pending) {
                                submit(pending, note);
                            }
                        }}
                        className="space-y-4"
                    >
                        <DialogHeader>
                            <DialogTitle>
                                {pending
                                    ? t(`action.dialog_title.${pending.status}`)
                                    : ''}
                            </DialogTitle>
                            <DialogDescription>
                                {t('action.note_visible')}
                            </DialogDescription>
                        </DialogHeader>

                        <div className="grid gap-2">
                            <Label htmlFor="status-note">
                                {t('action.note_label')}
                            </Label>
                            <Textarea
                                id="status-note"
                                value={note}
                                onChange={(event) =>
                                    setNote(event.target.value)
                                }
                                placeholder={t('action.note_placeholder')}
                                maxLength={1000}
                                autoFocus
                            />
                            <InputError
                                message={errors.note ?? errors.to_status}
                            />
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="ghost"
                                onClick={() => setPending(null)}
                            >
                                {t('common.cancel')}
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing}
                                variant={
                                    pending?.status === 'CANCELLED'
                                        ? 'destructive'
                                        : 'default'
                                }
                            >
                                {processing && <Spinner />}
                                {pending ? label(pending) : t('common.confirm')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}
