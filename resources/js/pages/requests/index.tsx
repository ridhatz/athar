import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { PriorityBadge, StatusBadge } from '@/components/athar/status-badge';
import { PriorityStripe } from '@/components/athar/urgency-bar';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import { create, index, show } from '@/routes/requests';
import type {
    Category,
    Paginated,
    RequestFilters,
    RequestListItem,
} from '@/types/athar';

type Props = {
    requests: Paginated<RequestListItem>;
    filters: RequestFilters;
    categories: Category[];
    statuses: string[];
    priorities: string[];
    canCreate: boolean;
};

const ALL = 'all';

export default function RequestsIndex({
    requests,
    filters,
    categories,
    statuses,
    priorities,
    canCreate,
}: Props) {
    const { t } = useT();
    const format = useFormat();
    const [search, setSearch] = useState(filters.q);
    const firstRender = useRef(true);

    /** Send the filters to the server. Empty and false values are left out of the URL. */
    const apply = (next: Partial<RequestFilters>) => {
        const merged = { ...filters, ...next };
        const query: Record<string, string | number> = {};

        if (merged.q) query.q = merged.q;
        if (merged.status) query.status = merged.status;
        if (merged.priority) query.priority = merged.priority;
        if (merged.category_id) query.category_id = merged.category_id;
        // Laravel's "boolean" rule accepts 1/0, not the string "true".
        if (merged.mine) query.mine = 1;
        if (merged.assigned_to_me) query.assigned_to_me = 1;
        if (merged.overdue) query.overdue = 1;
        if (merged.open) query.open = 1;
        if (merged.my_teams) query.my_teams = 1;
        if (merged.due_today) query.due_today = 1;
        if (merged.unrouted) query.unrouted = 1;

        router.get(index().url, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    // Search as you type, but wait for a pause so we do not query on every keystroke.
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }
        const timer = setTimeout(() => apply({ q: search }), 300);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const hasFilters = Boolean(
        filters.q ||
        filters.status ||
        filters.priority ||
        filters.category_id ||
        filters.mine ||
        filters.assigned_to_me ||
        filters.overdue ||
        filters.open ||
        filters.my_teams ||
        filters.due_today ||
        filters.unrouted,
    );

    const clear = () => {
        setSearch('');
        router.get(index().url, {}, { preserveState: true, replace: true });
    };

    const toggle = (
        key:
            | 'mine'
            | 'assigned_to_me'
            | 'overdue'
            | 'open'
            | 'my_teams'
            | 'due_today'
            | 'unrouted',
        label: string,
    ) => (
        <Button
            type="button"
            size="sm"
            variant={filters[key] ? 'default' : 'outline'}
            aria-pressed={filters[key]}
            onClick={() => apply({ [key]: !filters[key] })}
        >
            {label}
        </Button>
    );

    return (
        <>
            <Head title={t('requests.title')} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('requests.title')}
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {t('requests.subtitle')}
                        </p>
                    </div>
                    {canCreate && (
                        <Button asChild>
                            <Link href={create()}>
                                <Plus />
                                {t('requests.new')}
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative min-w-56 flex-1 sm:max-w-xs">
                        <Search
                            className="text-muted-foreground pointer-events-none absolute top-2.5 left-2.5 size-4"
                            aria-hidden
                        />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder={t('requests.search_placeholder')}
                            aria-label={t('requests.search_placeholder')}
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
                            className="w-40"
                            aria-label={t('requests.filter.status')}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>
                                {t('requests.filter.status')}: {t('common.all')}
                            </SelectItem>
                            {statuses.map((value) => (
                                <SelectItem key={value} value={value}>
                                    {t(`status.${value}`)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.priority || ALL}
                        onValueChange={(v) =>
                            apply({ priority: v === ALL ? '' : v })
                        }
                    >
                        <SelectTrigger
                            className="w-44"
                            aria-label={t('requests.filter.priority')}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>
                                {t('requests.filter.priority')}:{' '}
                                {t('common.all')}
                            </SelectItem>
                            {priorities.map((value) => (
                                <SelectItem key={value} value={value}>
                                    {t(`priority.${value}`)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <Select
                        value={filters.category_id || ALL}
                        onValueChange={(v) =>
                            apply({ category_id: v === ALL ? '' : v })
                        }
                    >
                        <SelectTrigger
                            className="w-48"
                            aria-label={t('requests.filter.category')}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>
                                {t('requests.filter.category')}:{' '}
                                {t('common.all')}
                            </SelectItem>
                            {categories.map((category) => (
                                <SelectItem
                                    key={category.id}
                                    value={String(category.id)}
                                >
                                    {category.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {toggle('mine', t('requests.filter.mine'))}
                    {toggle(
                        'assigned_to_me',
                        t('requests.filter.assigned_to_me'),
                    )}
                    {toggle('overdue', t('requests.filter.overdue'))}
                    {toggle('my_teams', t('requests.filter.my_teams'))}
                    {toggle('due_today', t('requests.filter.due_today'))}
                    {toggle('open', t('requests.filter.open'))}
                    {filters.unrouted &&
                        toggle('unrouted', t('requests.filter.unrouted'))}
                    {hasFilters && (
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={clear}
                        >
                            <X />
                            {t('common.clear')}
                        </Button>
                    )}
                </div>

                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>
                                    {t('requests.col.number')}
                                </TableHead>
                                <TableHead>{t('requests.col.title')}</TableHead>
                                <TableHead>
                                    {t('requests.col.status')}
                                </TableHead>
                                <TableHead>
                                    {t('requests.col.priority')}
                                </TableHead>
                                <TableHead>
                                    {t('requests.col.deadline')}
                                </TableHead>
                                <TableHead>
                                    {t('requests.col.assignee')}
                                </TableHead>
                                <TableHead>
                                    {t('requests.col.requester')}
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {requests.data.map((item) => (
                                <TableRow key={item.id}>
                                    <TableCell className="relative pl-5 font-mono text-xs whitespace-nowrap">
                                        <PriorityStripe
                                            priority={item.priority}
                                        />
                                        <Link
                                            href={show({
                                                serviceRequest:
                                                    item.request_number,
                                            })}
                                            className="text-primary font-medium underline-offset-4 hover:underline"
                                        >
                                            {item.request_number}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="max-w-80">
                                        <Link
                                            href={show({
                                                serviceRequest:
                                                    item.request_number,
                                            })}
                                            className="line-clamp-2 font-medium hover:underline"
                                        >
                                            {item.title}
                                        </Link>
                                        <span className="text-muted-foreground text-xs">
                                            {item.category?.name}
                                        </span>
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge status={item.status} />
                                    </TableCell>
                                    <TableCell>
                                        <PriorityBadge
                                            priority={item.priority}
                                        />
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap">
                                        <span
                                            className={cn(
                                                item.is_overdue &&
                                                    'text-destructive font-medium',
                                            )}
                                        >
                                            {format.date(item.deadline)}
                                        </span>
                                        {item.is_overdue && (
                                            <span className="text-destructive block text-xs">
                                                {t('show.overdue')}
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        {item.assigned_team ||
                                        item.assigned_user ? (
                                            <>
                                                <span className="block">
                                                    {item.assigned_user?.name ??
                                                        '—'}
                                                </span>
                                                <span className="text-muted-foreground text-xs">
                                                    {item.assigned_team?.name}
                                                </span>
                                            </>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                {t('common.unassigned')}
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        {item.requester?.name}
                                    </TableCell>
                                </TableRow>
                            ))}
                            {requests.data.length === 0 && (
                                <TableRow className="hover:bg-transparent">
                                    <TableCell
                                        colSpan={7}
                                        className="py-16 text-center"
                                    >
                                        <p className="font-medium">
                                            {hasFilters
                                                ? t('requests.empty.filtered')
                                                : t('requests.empty.title')}
                                        </p>
                                        {!hasFilters && (
                                            <p className="text-muted-foreground mt-1 text-sm">
                                                {t('requests.empty.hint')}
                                            </p>
                                        )}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                {requests.meta.last_page > 1 && (
                    <nav
                        className="flex items-center justify-between text-sm"
                        aria-label="pagination"
                    >
                        <span className="text-muted-foreground">
                            {t('common.page_of', {
                                current: requests.meta.current_page,
                                last: requests.meta.last_page,
                            })}
                            {' · '}
                            {t('common.total', { count: requests.meta.total })}
                        </span>
                        <div className="flex gap-2">
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                disabled={!requests.links.prev}
                            >
                                <Link
                                    href={requests.links.prev ?? '#'}
                                    preserveScroll
                                    aria-disabled={!requests.links.prev}
                                >
                                    {t('common.previous')}
                                </Link>
                            </Button>
                            <Button
                                asChild
                                variant="outline"
                                size="sm"
                                disabled={!requests.links.next}
                            >
                                <Link
                                    href={requests.links.next ?? '#'}
                                    preserveScroll
                                    aria-disabled={!requests.links.next}
                                >
                                    {t('common.next')}
                                </Link>
                            </Button>
                        </div>
                    </nav>
                )}
            </div>
        </>
    );
}
