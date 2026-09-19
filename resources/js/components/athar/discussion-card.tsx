import { router } from '@inertiajs/react';
import { Paperclip, Send } from 'lucide-react';
import { useRef, useState } from 'react';
import { FileRow } from '@/components/athar/files-card';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useFormat } from '@/hooks/use-format';
import { useT } from '@/hooks/use-t';
import { cn } from '@/lib/utils';
import { store as storeComment } from '@/routes/requests/comments';
import type { CommentEntry, RequestDetail } from '@/types/athar';

/** The discussion thread: public messages for everyone, internal notes for the working team only. */
export function DiscussionCard({
    request,
    comments,
}: {
    request: RequestDetail;
    comments: CommentEntry[];
}) {
    const { t } = useT();
    const format = useFormat();
    const input = useRef<HTMLInputElement>(null);
    const [body, setBody] = useState('');
    const [file, setFile] = useState<File | null>(null);
    const [internal, setInternal] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const { comment, comment_internal } = request.permissions;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        setProcessing(true);
        router.post(
            storeComment({ serviceRequest: request.request_number }).url,
            {
                body,
                is_internal: internal ? 1 : 0,
                ...(file ? { file } : {}),
            },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    setBody('');
                    setFile(null);
                    setInternal(false);
                    setErrors({});
                    if (input.current) input.current.value = '';
                },
                onError: (serverErrors) => setErrors(serverErrors),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <div className="space-y-5">
            {comments.length === 0 ? (
                <p className="text-muted-foreground text-sm">
                    {t('discussion.empty')}
                </p>
            ) : (
                <ul className="space-y-4">
                    {comments.map((entry) => (
                        <li
                            key={entry.id}
                            className={cn(
                                'rounded-lg border p-3',
                                entry.is_internal &&
                                    'border-dashed bg-amber-50 dark:bg-amber-950/30',
                            )}
                        >
                            <div className="mb-1 flex flex-wrap items-center gap-2 text-xs">
                                <span className="text-sm font-medium">
                                    {entry.author?.name}
                                </span>
                                <time
                                    dateTime={entry.created_at}
                                    className="text-muted-foreground"
                                >
                                    {format.dateTime(entry.created_at)}
                                </time>
                                {entry.is_internal && (
                                    <Badge variant="secondary">
                                        {t('discussion.internal')}
                                    </Badge>
                                )}
                            </div>
                            <p className="text-sm leading-relaxed break-words whitespace-pre-wrap">
                                {entry.body}
                            </p>
                            {entry.attachments &&
                                entry.attachments.length > 0 && (
                                    <ul className="mt-3 space-y-2 border-t pt-3">
                                        {entry.attachments.map((f) => (
                                            <FileRow key={f.id} file={f} />
                                        ))}
                                    </ul>
                                )}
                        </li>
                    ))}
                </ul>
            )}

            {comment && (
                <form onSubmit={submit} className="space-y-3">
                    <Textarea
                        value={body}
                        onChange={(event) => setBody(event.target.value)}
                        placeholder={t('discussion.placeholder')}
                        aria-label={t('discussion.title')}
                        maxLength={5000}
                        className="min-h-24"
                    />
                    <InputError message={errors.body} />

                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-wrap items-center gap-4">
                            <label className="text-muted-foreground hover:text-foreground flex cursor-pointer items-center gap-1.5 text-sm">
                                <Paperclip className="size-4" aria-hidden />
                                <span>
                                    {file ? file.name : t('discussion.attach')}
                                </span>
                                <input
                                    ref={input}
                                    type="file"
                                    className="sr-only"
                                    onChange={(event) =>
                                        setFile(event.target.files?.[0] ?? null)
                                    }
                                />
                            </label>
                            {comment_internal && (
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="comment-internal"
                                        checked={internal}
                                        onCheckedChange={(v) =>
                                            setInternal(v === true)
                                        }
                                    />
                                    <Label
                                        htmlFor="comment-internal"
                                        className="font-normal"
                                        title={t('discussion.internal_hint')}
                                    >
                                        {t('discussion.internal')}
                                    </Label>
                                </div>
                            )}
                        </div>
                        <Button
                            type="submit"
                            disabled={processing || body.trim() === ''}
                        >
                            {processing ? <Spinner /> : <Send />}
                            {t('discussion.send')}
                        </Button>
                    </div>
                    <InputError message={errors.file} />
                </form>
            )}
        </div>
    );
}
