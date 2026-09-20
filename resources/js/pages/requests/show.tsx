import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, TriangleAlert } from 'lucide-react';
import { ActivityTimeline } from '@/components/athar/activity-timeline';
import { AssignPanel } from '@/components/athar/assign-panel';
import { DiscussionCard } from '@/components/athar/discussion-card';
import { EditRequestDialog } from '@/components/athar/edit-request-dialog';
import { FilesCard } from '@/components/athar/files-card';
import { ReviewCard } from '@/components/athar/review-card';
import { StatusActions } from '@/components/athar/status-actions';
import { PriorityBadge, StatusBadge } from '@/components/athar/status-badge';
import { PriorityBanner } from '@/components/athar/urgency-bar';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useFormat } from '@/hooks/use-format';
import { useT } from '@/hooks/use-t';
import { cn } from '@/lib/utils';
import { index } from '@/routes/requests';
import type {
    ActivityEntry,
    Assignables,
    AssignmentEntry,
    AttachmentEntry,
    Category,
    CommentEntry,
    RequestDetail,
    ReviewEntry,
} from '@/types/athar';

type Props = {
    request: { data: RequestDetail };
    activity: { data: ActivityEntry[] };
    assignments: { data: AssignmentEntry[] } | [];
    assignables: Assignables | null;
    categories: Category[];
    comments: { data: CommentEntry[] };
    attachments: { data: AttachmentEntry[] };
    reviews: { data: ReviewEntry[] };
    maxUploadMb: number;
};

function Field({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div>
            <dt className="text-muted-foreground text-xs">{label}</dt>
            <dd className="mt-0.5 text-sm font-medium">{children}</dd>
        </div>
    );
}

export default function ShowRequest({
    request: wrapped,
    activity,
    assignments,
    assignables,
    categories,
    comments,
    attachments,
    reviews,
    maxUploadMb,
}: Props) {
    const request = wrapped.data;
    const { t } = useT();
    const format = useFormat();

    const history = Array.isArray(assignments) ? [] : assignments.data;
    const isFinished =
        request.status === 'COMPLETED' || request.status === 'CANCELLED';

    return (
        <>
            <Head title={`${request.request_number} · ${request.title}`} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 md:p-6">
                <div>
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="mb-2 -ml-3"
                    >
                        <Link href={index()}>
                            <ArrowLeft />
                            {t('show.back')}
                        </Link>
                    </Button>

                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="min-w-0">
                            <div className="mb-1 flex flex-wrap items-center gap-2">
                                <span className="text-muted-foreground font-mono text-sm">
                                    {request.request_number}
                                </span>
                                <StatusBadge status={request.status} />
                                <PriorityBadge priority={request.priority} />
                                {request.is_overdue && (
                                    <Badge variant="destructive">
                                        <TriangleAlert />
                                        {t('show.overdue')}
                                    </Badge>
                                )}
                            </div>
                            <h1 className="text-2xl font-semibold tracking-tight break-words">
                                {request.title}
                            </h1>
                            <div className="mt-3 max-w-xs">
                                <PriorityBanner priority={request.priority} />
                            </div>
                        </div>

                        {(request.permissions.edit_details ||
                            request.permissions.edit_priority) && (
                            <EditRequestDialog
                                request={request}
                                categories={categories}
                            />
                        )}
                    </div>

                    {request.status === 'BLOCKED' &&
                        request.blocked_from_status && (
                            <Alert className="mt-4">
                                <TriangleAlert />
                                <AlertDescription>
                                    {t('show.blocked_from', {
                                        status: t(
                                            `status.${request.blocked_from_status}`,
                                        ),
                                    })}
                                </AlertDescription>
                            </Alert>
                        )}
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="flex flex-col gap-6 lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {t('show.section.description')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm leading-relaxed break-words whitespace-pre-wrap">
                                    {request.description}
                                </p>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {t('discussion.title')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <DiscussionCard
                                    request={request}
                                    comments={comments.data}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {t('show.section.activity')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <ActivityTimeline entries={activity.data} />
                            </CardContent>
                        </Card>
                    </div>

                    <div className="flex flex-col gap-6">
                        {!isFinished && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        {t('show.section.actions')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <StatusActions request={request} />
                                </CardContent>
                            </Card>
                        )}

                        {(reviews.data.length > 0 ||
                            request.permissions.review_decision) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        {t('review.title')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <ReviewCard
                                        request={request}
                                        reviews={reviews.data}
                                    />
                                </CardContent>
                            </Card>
                        )}

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {t('files.title')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <FilesCard
                                    request={request}
                                    files={attachments.data}
                                    maxMb={maxUploadMb}
                                />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {t('show.section.assignment')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {assignables ? (
                                    <AssignPanel
                                        key={`${request.assigned_team?.id}-${request.assigned_user?.id}`}
                                        request={request}
                                        assignables={assignables}
                                    />
                                ) : (
                                    <dl className="grid gap-3">
                                        <Field label={t('show.field.team')}>
                                            {request.assigned_team?.name ?? (
                                                <span className="text-muted-foreground">
                                                    {t('common.unassigned')}
                                                </span>
                                            )}
                                        </Field>
                                        <Field label={t('show.field.pic')}>
                                            {request.assigned_user?.name ?? (
                                                <span className="text-muted-foreground">
                                                    {t('common.unassigned')}
                                                </span>
                                            )}
                                        </Field>
                                    </dl>
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">
                                    {t('show.section.details')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <dl className="grid gap-3">
                                    <Field label={t('show.field.requester')}>
                                        {request.requester?.name}
                                    </Field>
                                    <Field label={t('show.field.department')}>
                                        {request.department?.name}
                                    </Field>
                                    <Field label={t('show.field.category')}>
                                        {request.category?.name}
                                    </Field>
                                    <Field label={t('show.field.deadline')}>
                                        <span
                                            className={cn(
                                                request.is_overdue &&
                                                    'text-destructive',
                                            )}
                                        >
                                            {format.dateTime(request.deadline)}
                                        </span>
                                    </Field>
                                    <Field label={t('show.field.created')}>
                                        {format.dateTime(request.created_at)}
                                    </Field>
                                    {request.completed_at && (
                                        <Field
                                            label={t('show.field.completed')}
                                        >
                                            {format.dateTime(
                                                request.completed_at,
                                            )}
                                        </Field>
                                    )}
                                    {request.cancelled_at && (
                                        <Field
                                            label={t('show.field.cancelled')}
                                        >
                                            {format.dateTime(
                                                request.cancelled_at,
                                            )}
                                        </Field>
                                    )}
                                </dl>
                            </CardContent>
                        </Card>

                        {history.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">
                                        {t('show.section.history')}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <ol className="space-y-3 text-sm">
                                        {history.map((entry) => (
                                            <li
                                                key={entry.id}
                                                className={cn(
                                                    !entry.is_current &&
                                                        'text-muted-foreground',
                                                )}
                                            >
                                                <div className="font-medium">
                                                    {entry.team?.name ?? '—'}
                                                    {entry.user && (
                                                        <>
                                                            {' '}
                                                            → {entry.user.name}
                                                        </>
                                                    )}
                                                    {entry.is_current && (
                                                        <Badge
                                                            variant="secondary"
                                                            className="ml-2"
                                                        >
                                                            {t(
                                                                'show.history_current',
                                                            )}
                                                        </Badge>
                                                    )}
                                                </div>
                                                <div className="text-xs">
                                                    {format.dateTime(
                                                        entry.assigned_at,
                                                    )}{' '}
                                                    ·{' '}
                                                    {t('show.history_by', {
                                                        name: entry.assigned_by
                                                            .name,
                                                    })}
                                                </div>
                                                {entry.note && (
                                                    <div className="mt-0.5 text-xs italic">
                                                        {entry.note}
                                                    </div>
                                                )}
                                            </li>
                                        ))}
                                    </ol>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
