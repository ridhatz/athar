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
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useT } from '@/hooks/use-t';
import { cn } from '@/lib/utils';
import { index, store, update } from '@/routes/admin/teams';
import type { AdminTeam, Paginated } from '@/types/athar';

type Props = {
    teams: Paginated<AdminTeam>;
    filters: { q: string };
};

/** Add or rename one team. The server has the final say on what is allowed. */
function TeamFormDialog({
    open,
    onOpenChange,
    team,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    team: AdminTeam | null;
}) {
    const { t } = useT();
    const editing = team !== null;

    const [name, setName] = useState(team?.name ?? '');
    const [description, setDescription] = useState(team?.description ?? '');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        setProcessing(true);

        const body = { name, description };
        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
            onError: (serverErrors: Record<string, string>) =>
                setErrors(serverErrors),
            onFinish: () => setProcessing(false),
        };

        if (editing) {
            router.patch(update({ team: team.id }).url, body, options);
        } else {
            router.post(store().url, body, options);
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
                                : t('admin.teams.add')}
                        </DialogTitle>
                    </DialogHeader>

                    <div className="space-y-2">
                        <Label htmlFor="team-name">
                            {t('admin.teams.form.name')}
                        </Label>
                        <Input
                            id="team-name"
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            autoFocus
                            required
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="team-description">
                            {t('admin.teams.form.description')}
                        </Label>
                        <Input
                            id="team-description"
                            value={description}
                            onChange={(e) => setDescription(e.target.value)}
                        />
                        <p className="text-muted-foreground text-xs">
                            {t('admin.teams.form.description_hint')}
                        </p>
                        <InputError message={errors.description} />
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

export default function Teams({ teams, filters }: Props) {
    const { t } = useT();

    const [search, setSearch] = useState(filters.q);
    const [form, setForm] = useState<{ open: boolean; team: AdminTeam | null }>(
        { open: false, team: null },
    );
    const [refused, setRefused] = useState<string | null>(null);

    const apply = (q: string) =>
        router.get(index().url, q ? { q } : {}, {
            preserveState: true,
            replace: true,
        });

    const setActive = (team: AdminTeam, active: boolean) =>
        router.patch(
            update({ team: team.id }).url,
            { is_active: active },
            {
                preserveScroll: true,
                // The server is the authority; surface its refusal rather than guessing.
                onError: (serverErrors: Record<string, string>) =>
                    setRefused(serverErrors.is_active ?? null),
            },
        );

    return (
        <>
            <Head title={t('admin.teams.title')} />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('admin.teams.title')}
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {t('admin.teams.subtitle')}
                        </p>
                    </div>
                    <Button onClick={() => setForm({ open: true, team: null })}>
                        <Plus />
                        {t('admin.teams.add')}
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
                            placeholder={t('admin.teams.search')}
                            aria-label={t('admin.teams.search')}
                            className="pl-8"
                        />
                    </div>
                </form>

                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>
                                    {t('admin.teams.col.name')}
                                </TableHead>
                                <TableHead>
                                    {t('admin.teams.col.description')}
                                </TableHead>
                                <TableHead className="w-24">
                                    {t('admin.teams.col.members')}
                                </TableHead>
                                <TableHead className="w-28">
                                    {t('admin.ref.col.status')}
                                </TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {teams.data.map((team) => {
                                const stranded = team.stranded_members ?? [];
                                const locked = stranded.length > 0;

                                return (
                                    <TableRow
                                        key={team.id}
                                        className={cn(
                                            !team.is_active && 'opacity-60',
                                        )}
                                    >
                                        <TableCell className="font-medium">
                                            {team.name}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground text-sm">
                                            {team.description ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {team.members_count}
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                variant="outline"
                                                className={cn(
                                                    team.is_active
                                                        ? 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                                                        : 'text-muted-foreground',
                                                )}
                                            >
                                                {team.is_active
                                                    ? t('admin.ref.active')
                                                    : t('admin.ref.inactive')}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`${t('admin.ref.edit')} ${team.name}`}
                                                    title={t('admin.ref.edit')}
                                                    onClick={() =>
                                                        setForm({
                                                            open: true,
                                                            team,
                                                        })
                                                    }
                                                >
                                                    <Pencil />
                                                </Button>

                                                {team.is_active ? (
                                                    locked ? (
                                                        // Explain the refusal instead of offering it.
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
                                                                            'admin.teams.stranded',
                                                                            {
                                                                                names: stranded.join(
                                                                                    ', ',
                                                                                ),
                                                                            },
                                                                        )}
                                                                    >
                                                                        <PowerOff />
                                                                    </Button>
                                                                </span>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                {t(
                                                                    'admin.teams.stranded',
                                                                    {
                                                                        names: stranded.join(
                                                                            ', ',
                                                                        ),
                                                                    },
                                                                )}
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    ) : (
                                                        <Button
                                                            variant="ghost"
                                                            size="icon"
                                                            aria-label={`${t('admin.ref.deactivate')} ${team.name}`}
                                                            title={t(
                                                                'admin.ref.deactivate',
                                                            )}
                                                            onClick={() =>
                                                                setActive(
                                                                    team,
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
                                                        aria-label={`${t('admin.ref.activate')} ${team.name}`}
                                                        title={t(
                                                            'admin.ref.activate',
                                                        )}
                                                        onClick={() =>
                                                            setActive(
                                                                team,
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
                            {teams.data.length === 0 && (
                                <TableRow className="hover:bg-transparent">
                                    <TableCell
                                        colSpan={5}
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

            {/* Keyed so each open starts from that team's current values. */}
            {form.open && (
                <TeamFormDialog
                    key={form.team?.id ?? 'new'}
                    open
                    onOpenChange={(open) => setForm({ open, team: form.team })}
                    team={form.team}
                />
            )}

            <Dialog
                open={refused !== null}
                onOpenChange={(open) => !open && setRefused(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('admin.teams.title')}</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm">{refused}</p>
                    <DialogFooter>
                        <Button onClick={() => setRefused(null)}>
                            {t('common.cancel')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
