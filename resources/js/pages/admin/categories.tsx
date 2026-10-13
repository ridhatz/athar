import { Head, router } from '@inertiajs/react';
import { Pencil, Plus, Power, PowerOff, Search } from 'lucide-react';
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
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useT } from '@/hooks/use-t';
import { cn } from '@/lib/utils';
import { index, store, update } from '@/routes/admin/categories';
import type { AdminCategory, Paginated } from '@/types/athar';

type Props = {
    categories: Paginated<AdminCategory>;
    filters: { q: string };
    activeCount: number;
};

/**
 * Add or edit one category. Both languages are edited side by side, because the admin maintains
 * the pair rather than whichever language the UI happens to be in. The slug is shown but never
 * sent: the server fixed it at creation and ignores any attempt to change it.
 */
function CategoryFormDialog({
    open,
    onOpenChange,
    category,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    category: AdminCategory | null;
}) {
    const { t } = useT();
    const editing = category !== null;

    const [nameId, setNameId] = useState(category?.name_id ?? '');
    const [nameEn, setNameEn] = useState(category?.name_en ?? '');
    const [guidanceId, setGuidanceId] = useState(category?.guidance_id ?? '');
    const [guidanceEn, setGuidanceEn] = useState(category?.guidance_en ?? '');
    const [sortOrder, setSortOrder] = useState(
        category ? String(category.sort_order) : '',
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        setProcessing(true);

        const body: Record<string, string | number | null> = {
            name_id: nameId,
            name_en: nameEn,
            guidance_id: guidanceId,
            guidance_en: guidanceEn,
        };
        // Left blank on a new category, the server puts it last.
        if (sortOrder !== '') body.sort_order = Number(sortOrder);

        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
            onError: (serverErrors: Record<string, string>) =>
                setErrors(serverErrors),
            onFinish: () => setProcessing(false),
        };

        if (editing) {
            router.patch(update({ category: category.id }).url, body, options);
        } else {
            router.post(store().url, body, options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>
                            {editing
                                ? t('admin.ref.edit')
                                : t('admin.categories.add')}
                        </DialogTitle>
                    </DialogHeader>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="category-name-id">
                                {t('admin.categories.form.name_id')}
                            </Label>
                            <Input
                                id="category-name-id"
                                value={nameId}
                                onChange={(e) => setNameId(e.target.value)}
                                autoFocus
                                required
                            />
                            <InputError message={errors.name_id} />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="category-name-en">
                                {t('admin.categories.form.name_en')}
                            </Label>
                            <Input
                                id="category-name-en"
                                value={nameEn}
                                onChange={(e) => setNameEn(e.target.value)}
                                required
                            />
                            <InputError message={errors.name_en} />
                        </div>
                    </div>

                    {editing && (
                        <div className="space-y-2">
                            <Label htmlFor="category-slug">
                                {t('admin.categories.col.slug')}
                            </Label>
                            <Input
                                id="category-slug"
                                value={category.slug}
                                readOnly
                                disabled
                                className="font-mono text-xs"
                            />
                            <p className="text-muted-foreground text-xs">
                                {t('admin.categories.slug_locked')}
                            </p>
                        </div>
                    )}

                    <div className="space-y-2">
                        <Label htmlFor="category-guidance-id">
                            {t('admin.categories.form.guidance_id')}
                        </Label>
                        <Textarea
                            id="category-guidance-id"
                            value={guidanceId}
                            onChange={(e) => setGuidanceId(e.target.value)}
                            rows={2}
                        />
                        <InputError message={errors.guidance_id} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="category-guidance-en">
                            {t('admin.categories.form.guidance_en')}
                        </Label>
                        <Textarea
                            id="category-guidance-en"
                            value={guidanceEn}
                            onChange={(e) => setGuidanceEn(e.target.value)}
                            rows={2}
                        />
                        <p className="text-muted-foreground text-xs">
                            {t('admin.categories.form.guidance_hint')}
                        </p>
                        <InputError message={errors.guidance_en} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="category-sort-order">
                            {t('admin.categories.form.sort_order')}
                        </Label>
                        <Input
                            id="category-sort-order"
                            type="number"
                            min={0}
                            max={65535}
                            value={sortOrder}
                            onChange={(e) => setSortOrder(e.target.value)}
                            className="w-28"
                        />
                        <p className="text-muted-foreground text-xs">
                            {t('admin.categories.form.sort_order_hint')}
                        </p>
                        <InputError message={errors.sort_order} />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="ghost"
                            onClick={() => onOpenChange(false)}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {t('common.save')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export default function Categories({
    categories,
    filters,
    activeCount,
}: Props) {
    const { t, locale } = useT();

    const [search, setSearch] = useState(filters.q);
    const [form, setForm] = useState<{
        open: boolean;
        category: AdminCategory | null;
    }>({ open: false, category: null });

    const apply = (q: string) =>
        router.get(index().url, q ? { q } : {}, {
            preserveState: true,
            replace: true,
        });

    const setActive = (category: AdminCategory, active: boolean) =>
        router.patch(
            update({ category: category.id }).url,
            { is_active: active },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title={t('admin.categories.title')} />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('admin.categories.title')}
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {t('admin.categories.subtitle')}
                        </p>
                    </div>
                    <Button
                        onClick={() => setForm({ open: true, category: null })}
                    >
                        <Plus />
                        {t('admin.categories.add')}
                    </Button>
                </div>

                <form
                    className="flex flex-wrap items-center gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        apply(search);
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
                            placeholder={t('admin.categories.search')}
                            aria-label={t('admin.categories.search')}
                            className="pl-8"
                        />
                    </div>
                </form>

                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead className="w-20">
                                    {t('admin.categories.col.order')}
                                </TableHead>
                                <TableHead>
                                    {t('admin.categories.col.name')}
                                </TableHead>
                                <TableHead>
                                    {t('admin.categories.col.slug')}
                                </TableHead>
                                <TableHead className="w-28">
                                    {t('admin.categories.col.requests')}
                                </TableHead>
                                <TableHead className="w-28">
                                    {t('admin.ref.col.status')}
                                </TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {categories.data.map((category) => {
                                // Switching the last active one off would leave nobody able to file.
                                const locked =
                                    category.is_active && activeCount <= 1;

                                return (
                                    <TableRow
                                        key={category.id}
                                        className={cn(
                                            !category.is_active && 'opacity-60',
                                        )}
                                    >
                                        <TableCell className="text-muted-foreground text-sm">
                                            {category.sort_order}
                                        </TableCell>
                                        <TableCell>
                                            <div className="font-medium">
                                                {locale === 'en'
                                                    ? category.name_en
                                                    : category.name_id}
                                            </div>
                                            <div className="text-muted-foreground text-xs">
                                                {locale === 'en'
                                                    ? category.name_id
                                                    : category.name_en}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground font-mono text-xs">
                                            {category.slug}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {category.requests_count}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={cn(
                                                    category.is_active
                                                        ? 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                                                        : 'text-muted-foreground',
                                                )}
                                            >
                                                {category.is_active
                                                    ? t('admin.ref.active')
                                                    : t('admin.ref.inactive')}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`${t('admin.ref.edit')} ${category.name_id}`}
                                                    title={t('admin.ref.edit')}
                                                    onClick={() =>
                                                        setForm({
                                                            open: true,
                                                            category,
                                                        })
                                                    }
                                                >
                                                    <Pencil />
                                                </Button>

                                                {category.is_active ? (
                                                    locked ? (
                                                        <Tooltip>
                                                            <TooltipTrigger
                                                                asChild
                                                            >
                                                                <span>
                                                                    <Button
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        disabled
                                                                        aria-label={t(
                                                                            'admin.categories.last_active',
                                                                        )}
                                                                    >
                                                                        <PowerOff />
                                                                    </Button>
                                                                </span>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                {t(
                                                                    'admin.categories.last_active',
                                                                )}
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    ) : (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={`${t('admin.ref.deactivate')} ${category.name_id}`}
                                                            title={t(
                                                                'admin.ref.deactivate',
                                                            )}
                                                            onClick={() =>
                                                                setActive(
                                                                    category,
                                                                    false,
                                                                )
                                                            }
                                                        >
                                                            <PowerOff />
                                                        </Button>
                                                    )
                                                ) : (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`${t('admin.ref.activate')} ${category.name_id}`}
                                                        title={t(
                                                            'admin.ref.activate',
                                                        )}
                                                        onClick={() =>
                                                            setActive(
                                                                category,
                                                                true,
                                                            )
                                                        }
                                                    >
                                                        <Power />
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                );
                            })}
                            {categories.data.length === 0 && (
                                <TableRow className="hover:bg-transparent">
                                    <TableCell
                                        colSpan={6}
                                        className="text-muted-foreground py-12 text-center"
                                    >
                                        {t('admin.ref.empty')}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <p className="text-muted-foreground text-xs">
                    {t('admin.ref.no_delete')}
                </p>
            </div>

            {form.open && (
                <CategoryFormDialog
                    key={form.category?.id ?? 'new'}
                    open
                    onOpenChange={(open) =>
                        setForm({ open, category: form.category })
                    }
                    category={form.category}
                />
            )}
        </>
    );
}
