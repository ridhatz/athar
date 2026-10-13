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
import { useT } from '@/hooks/use-t';
import { cn } from '@/lib/utils';
import { index, store, update } from '@/routes/admin/departments';
import type { AdminDepartment, Paginated } from '@/types/athar';

type Props = {
    departments: Paginated<AdminDepartment>;
    filters: { q: string };
};

/** Add or rename one department. */
function DepartmentFormDialog({
    open,
    onOpenChange,
    department,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    department: AdminDepartment | null;
}) {
    const { t } = useT();
    const editing = department !== null;

    const [name, setName] = useState(department?.name ?? '');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        setProcessing(true);

        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
            onError: (serverErrors: Record<string, string>) =>
                setErrors(serverErrors),
            onFinish: () => setProcessing(false),
        };

        if (editing) {
            router.patch(
                update({ department: department.id }).url,
                { name },
                options,
            );
        } else {
            router.post(store().url, { name }, options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>
                            {editing
                                ? t('admin.ref.edit')
                                : t('admin.departments.add')}
                        </DialogTitle>
                    </DialogHeader>

                    <div className="space-y-2">
                        <Label htmlFor="department-name">
                            {t('admin.departments.form.name')}
                        </Label>
                        <Input
                            id="department-name"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            autoFocus
                            required
                        />
                        <InputError message={errors.name} />
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

export default function Departments({ departments, filters }: Props) {
    const { t } = useT();

    const [search, setSearch] = useState(filters.q);
    const [form, setForm] = useState<{
        open: boolean;
        department: AdminDepartment | null;
    }>({ open: false, department: null });

    const apply = (q: string) =>
        router.get(index().url, q ? { q } : {}, {
            preserveState: true,
            replace: true,
        });

    const setActive = (department: AdminDepartment, active: boolean) =>
        router.patch(
            update({ department: department.id }).url,
            { is_active: active },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title={t('admin.departments.title')} />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('admin.departments.title')}
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {t('admin.departments.subtitle')}
                        </p>
                    </div>
                    <Button
                        onClick={() =>
                            setForm({ open: true, department: null })
                        }
                    >
                        <Plus />
                        {t('admin.departments.add')}
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
                            placeholder={t('admin.departments.search')}
                            aria-label={t('admin.departments.search')}
                            className="pl-8"
                        />
                    </div>
                </form>

                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>
                                    {t('admin.departments.col.name')}
                                </TableHead>
                                <TableHead className="w-24">
                                    {t('admin.departments.col.users')}
                                </TableHead>
                                <TableHead className="w-28">
                                    {t('admin.ref.col.status')}
                                </TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {departments.data.map((department) => (
                                <TableRow
                                    key={department.id}
                                    className={cn(
                                        !department.is_active && 'opacity-60',
                                    )}
                                >
                                    <TableCell className="font-medium">
                                        {department.name}
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        {department.users_count}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant="outline"
                                            className={cn(
                                                department.is_active
                                                    ? 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                                                    : 'text-muted-foreground',
                                            )}
                                        >
                                            {department.is_active
                                                ? t('admin.ref.active')
                                                : t('admin.ref.inactive')}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`${t('admin.ref.edit')} ${department.name}`}
                                                title={t('admin.ref.edit')}
                                                onClick={() =>
                                                    setForm({
                                                        open: true,
                                                        department,
                                                    })
                                                }
                                            >
                                                <Pencil />
                                            </Button>
                                            {department.is_active ? (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`${t('admin.ref.deactivate')} ${department.name}`}
                                                    title={t(
                                                        'admin.ref.deactivate',
                                                    )}
                                                    onClick={() =>
                                                        setActive(
                                                            department,
                                                            false,
                                                        )
                                                    }
                                                >
                                                    <PowerOff />
                                                </Button>
                                            ) : (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`${t('admin.ref.activate')} ${department.name}`}
                                                    title={t(
                                                        'admin.ref.activate',
                                                    )}
                                                    onClick={() =>
                                                        setActive(
                                                            department,
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
                            ))}
                            {departments.data.length === 0 && (
                                <TableRow className="hover:bg-transparent">
                                    <TableCell
                                        colSpan={4}
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
                <DepartmentFormDialog
                    key={form.department?.id ?? 'new'}
                    open
                    onOpenChange={(open) =>
                        setForm({ open, department: form.department })
                    }
                    department={form.department}
                />
            )}
        </>
    );
}
