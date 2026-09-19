import { router } from '@inertiajs/react';
import { Download, FileText, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useFormat } from '@/hooks/use-format';
import { useT } from '@/hooks/use-t';
import { store as storeAttachment } from '@/routes/requests/attachments';
import type { AttachmentEntry, RequestDetail } from '@/types/athar';

const USER_KINDS = ['BRIEF', 'REFERENCE', 'LOGO', 'DOCUMENT', 'OTHER'];
const ALL_KINDS = [
    'BRIEF',
    'REFERENCE',
    'LOGO',
    'DOCUMENT',
    'DRAFT',
    'REVISION',
    'DELIVERABLE',
    'OTHER',
];

export function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

/** A single file line, shared by the files card and by comments that carry a file. */
export function FileRow({ file }: { file: AttachmentEntry }) {
    const { t } = useT();
    const format = useFormat();

    return (
        <li className="flex items-start gap-3 text-sm">
            <FileText
                className="text-muted-foreground mt-0.5 size-4 shrink-0"
                aria-hidden
            />
            <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <a
                        href={file.download_url}
                        className="font-medium break-all underline-offset-4 hover:underline"
                    >
                        {file.original_name}
                    </a>
                    <Badge variant="secondary">
                        {t(`files.kind.${file.kind}`)}
                    </Badge>
                    <span className="text-muted-foreground text-xs">
                        {t('files.version', { n: file.version })}
                    </span>
                    {file.is_internal && (
                        <Badge variant="outline">{t('files.internal')}</Badge>
                    )}
                </div>
                <div className="text-muted-foreground text-xs">
                    {formatSize(file.size)} · {format.dateTime(file.created_at)}
                    {file.uploader &&
                        ` · ${t('files.by', { name: file.uploader.name })}`}
                </div>
            </div>
            <Button asChild variant="ghost" size="icon" className="shrink-0">
                <a
                    href={file.download_url}
                    aria-label={`${t('files.download')} ${file.original_name}`}
                >
                    <Download />
                </a>
            </Button>
        </li>
    );
}

export function FilesCard({
    request,
    files,
    maxMb,
}: {
    request: RequestDetail;
    files: AttachmentEntry[];
    maxMb: number;
}) {
    const { t } = useT();
    const input = useRef<HTMLInputElement>(null);
    const [file, setFile] = useState<File | null>(null);
    const [kind, setKind] = useState('DOCUMENT');
    const [internal, setInternal] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const { attach, attach_work_product, comment_internal } =
        request.permissions;
    const kinds = attach_work_product ? ALL_KINDS : USER_KINDS;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        if (!file) return;
        setProcessing(true);
        router.post(
            storeAttachment({ serviceRequest: request.request_number }).url,
            { file, kind, is_internal: internal ? 1 : 0 },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
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
        <div className="space-y-4">
            {files.length === 0 ? (
                <p className="text-muted-foreground text-sm">
                    {t('files.empty')}
                </p>
            ) : (
                <ul className="space-y-3">
                    {files.map((entry) => (
                        <FileRow key={entry.id} file={entry} />
                    ))}
                </ul>
            )}

            {attach && (
                <form onSubmit={submit} className="space-y-3 border-t pt-4">
                    <div className="grid gap-2">
                        <Label htmlFor="file-input">{t('files.upload')}</Label>
                        <input
                            ref={input}
                            id="file-input"
                            type="file"
                            onChange={(event) =>
                                setFile(event.target.files?.[0] ?? null)
                            }
                            className="file:bg-secondary file:text-secondary-foreground text-sm file:mr-3 file:rounded-md file:border-0 file:px-3 file:py-1.5 file:text-sm file:font-medium"
                        />
                        <p className="text-muted-foreground text-xs">
                            {t('files.hint', { max: maxMb })}
                        </p>
                        <InputError message={errors.file} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="file-kind">{t('files.kind')}</Label>
                        <Select value={kind} onValueChange={setKind}>
                            <SelectTrigger id="file-kind" className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {kinds.map((value) => (
                                    <SelectItem key={value} value={value}>
                                        {t(`files.kind.${value}`)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={errors.kind} />
                    </div>

                    {comment_internal && (
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="file-internal"
                                checked={internal}
                                onCheckedChange={(value) =>
                                    setInternal(value === true)
                                }
                            />
                            <Label
                                htmlFor="file-internal"
                                className="font-normal"
                            >
                                {t('discussion.internal')}
                            </Label>
                        </div>
                    )}

                    <Button
                        type="submit"
                        className="w-full"
                        disabled={!file || processing}
                    >
                        {processing ? <Spinner /> : <Upload />}
                        {t('files.upload')}
                    </Button>
                </form>
            )}
        </div>
    );
}
