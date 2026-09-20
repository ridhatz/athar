import { Head, router, usePage } from '@inertiajs/react';
import {
    Copy,
    KeyRound,
    Pencil,
    Plus,
    Search,
    UserCheck,
    UserX,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import { index, resetPassword, store, update } from '@/routes/admin/users';
import type {
    AdminUser,
    Credentials,
    NamedRef,
    Paginated,
    RoleCodeName,
} from '@/types/athar';

type Props = {
    users: Paginated<AdminUser>;
    filters: { q: string; role: string };
    roles: RoleCodeName[];
    teams: NamedRef[];
    departments: NamedRef[];
};

const NO_DEPARTMENT = 'none';
const ALL = 'all';

/** Add or edit one person. The server has the final say on what combinations are allowed. */
function UserFormDialog({
    open,
    onOpenChange,
    user,
    roles,
    teams,
    departments,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    user: AdminUser | null;
    roles: RoleCodeName[];
    teams: NamedRef[];
    departments: NamedRef[];
}) {
    const { t, locale } = useT();
    const editing = user !== null;

    const [name, setName] = useState(user?.name ?? '');
    const [email, setEmail] = useState(user?.email ?? '');
    const [password, setPassword] = useState('');
    const [chosenRoles, setChosenRoles] = useState<RoleCodeName[]>(
        user?.roles ?? ['REQUESTER'],
    );
    const [teamIds, setTeamIds] = useState<number[]>(
        user?.teams.map((team) => team.id) ?? [],
    );
    const [department, setDepartment] = useState(
        user?.department ? String(user.department.id) : NO_DEPARTMENT,
    );
    const [language, setLanguage] = useState<string>(user?.locale ?? locale);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const toggleRole = (role: RoleCodeName, checked: boolean) => {
        setChosenRoles((current) => {
            if (!checked) return current.filter((r) => r !== role);
            // Management is view-only: it stands alone. (The server enforces this too.)
            if (role === 'MANAGEMENT') return ['MANAGEMENT'];
            return [...current.filter((r) => r !== 'MANAGEMENT'), role];
        });
    };

    const toggleTeam = (id: number, checked: boolean) =>
        setTeamIds((current) =>
            checked ? [...current, id] : current.filter((t) => t !== id),
        );

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        setProcessing(true);

        const body: Record<
            string,
            string | number | null | string[] | number[]
        > = {
            name,
            email,
            roles: chosenRoles,
            team_ids: teamIds,
            department_id:
                department === NO_DEPARTMENT ? null : Number(department),
            locale: language,
        };
        if (!editing && password) body.password = password;

        const options = {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
            onError: (serverErrors: Record<string, string>) =>
                setErrors(serverErrors),
            onFinish: () => setProcessing(false),
        };

        if (editing) {
            router.patch(update({ user: user.id }).url, body, options);
        } else {
            router.post(store().url, body, options);
        }
    };

    // A field error may come back as "roles.0" or "team_ids.1"; show the first for each group.
    const firstError = (prefix: string) =>
        Object.entries(errors).find(
            ([key]) => key === prefix || key.startsWith(`${prefix}.`),
        )?.[1];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>
                            {editing
                                ? t('admin.users.form.edit')
                                : t('admin.users.form.add')}
                        </DialogTitle>
                    </DialogHeader>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="u-name">
                                {t('admin.users.form.name')}
                            </Label>
                            <Input
                                id="u-name"
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                autoFocus
                            />
                            <InputError message={firstError('name')} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="u-email">
                                {t('admin.users.form.email')}
                            </Label>
                            <Input
                                id="u-email"
                                type="email"
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                            />
                            <InputError message={firstError('email')} />
                        </div>
                    </div>

                    {!editing && (
                        <div className="grid gap-2">
                            <Label htmlFor="u-password">
                                {t('admin.users.form.password')}{' '}
                                <span className="text-muted-foreground font-normal">
                                    ({t('common.optional')})
                                </span>
                            </Label>
                            <Input
                                id="u-password"
                                type="password"
                                autoComplete="new-password"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                            />
                            <p className="text-muted-foreground text-xs">
                                {t('admin.users.form.password_hint')}
                            </p>
                            <InputError message={firstError('password')} />
                        </div>
                    )}

                    <fieldset className="grid gap-2">
                        <legend className="text-sm leading-none font-medium">
                            {t('admin.users.form.roles')}
                        </legend>
                        <div className="space-y-2 pt-1">
                            {roles.map((role) => (
                                <label
                                    key={role}
                                    className={cn(
                                        'flex cursor-pointer items-start gap-3 rounded-md border p-2.5',
                                        chosenRoles.includes(role) &&
                                            'border-primary bg-primary/5',
                                    )}
                                >
                                    <Checkbox
                                        checked={chosenRoles.includes(role)}
                                        onCheckedChange={(v) =>
                                            toggleRole(role, v === true)
                                        }
                                        className="mt-0.5"
                                    />
                                    <span>
                                        <span className="block text-sm font-medium">
                                            {t(`role.${role}`)}
                                        </span>
                                        <span className="text-muted-foreground block text-xs">
                                            {t(`role.desc.${role}`)}
                                        </span>
                                    </span>
                                </label>
                            ))}
                        </div>
                        <InputError message={firstError('roles')} />
                    </fieldset>

                    <fieldset className="grid gap-2">
                        <legend className="text-sm leading-none font-medium">
                            {t('admin.users.form.teams')}
                        </legend>
                        <div className="flex flex-wrap gap-x-5 gap-y-2 pt-1">
                            {teams.map((team) => (
                                <label
                                    key={team.id}
                                    className="flex cursor-pointer items-center gap-2 text-sm"
                                >
                                    <Checkbox
                                        checked={teamIds.includes(team.id)}
                                        onCheckedChange={(v) =>
                                            toggleTeam(team.id, v === true)
                                        }
                                    />
                                    {team.name}
                                </label>
                            ))}
                        </div>
                        <p className="text-muted-foreground text-xs">
                            {t('admin.users.form.teams_hint')}
                        </p>
                        <InputError message={firstError('team_ids')} />
                    </fieldset>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="u-department">
                                {t('admin.users.form.department')}
                            </Label>
                            <Select
                                value={department}
                                onValueChange={setDepartment}
                            >
                                <SelectTrigger id="u-department">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NO_DEPARTMENT}>
                                        —
                                    </SelectItem>
                                    {departments.map((d) => (
                                        <SelectItem
                                            key={d.id}
                                            value={String(d.id)}
                                        >
                                            {d.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={firstError('department_id')} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="u-language">
                                {t('admin.users.form.language')}
                            </Label>
                            <Select
                                value={language}
                                onValueChange={setLanguage}
                            >
                                <SelectTrigger id="u-language">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="id">
                                        Bahasa Indonesia
                                    </SelectItem>
                                    <SelectItem value="en">English</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <InputError message={firstError('is_active')} />

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
                            {editing
                                ? t('admin.users.form.submit_edit')
                                : t('admin.users.form.submit_add')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/** The one-time sign-in details. The password exists in the browser only until this is closed. */
function CredentialsDialog({
    credentials,
    onClose,
}: {
    credentials: Credentials | null;
    onClose: () => void;
}) {
    const { t } = useT();
    const [copied, setCopied] = useState(false);

    return (
        <Dialog
            open={credentials !== null}
            onOpenChange={(o) => !o && onClose()}
        >
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {t('admin.users.credentials.title')}
                    </DialogTitle>
                    <DialogDescription>
                        {t('admin.users.credentials.hint', {
                            name: credentials?.name ?? '',
                        })}
                    </DialogDescription>
                </DialogHeader>
                {credentials?.password && (
                    <dl className="bg-muted space-y-2 rounded-md p-4 text-sm">
                        <div>
                            <dt className="text-muted-foreground text-xs">
                                {t('admin.users.credentials.email')}
                            </dt>
                            <dd className="font-mono break-all">
                                {credentials.email}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground text-xs">
                                {t('admin.users.credentials.password')}
                            </dt>
                            <dd className="font-mono text-base font-semibold select-all">
                                {credentials.password}
                            </dd>
                        </div>
                    </dl>
                )}
                <DialogFooter>
                    {credentials?.password && (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={async () => {
                                await navigator.clipboard?.writeText(
                                    `${credentials.email}\n${credentials.password}`,
                                );
                                setCopied(true);
                            }}
                        >
                            <Copy />
                            {copied
                                ? t('admin.users.credentials.copied')
                                : t('admin.users.credentials.copy')}
                        </Button>
                    )}
                    <Button type="button" onClick={onClose}>
                        {t('admin.users.credentials.done')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function Users({
    users,
    filters,
    roles,
    teams,
    departments,
}: Props) {
    const { t } = useT();
    const page = usePage();
    const me = page.props.auth.user.id;

    const [search, setSearch] = useState(filters.q);
    const [form, setForm] = useState<{ open: boolean; user: AdminUser | null }>(
        {
            open: false,
            user: null,
        },
    );
    const [resetting, setResetting] = useState<AdminUser | null>(null);
    const [credentials, setCredentials] = useState<Credentials | null>(null);

    // The server hands the generated password over once, as flash data on the response.
    const flashed = (
        page as unknown as { flash?: { credentials?: Credentials } }
    ).flash?.credentials;
    useEffect(() => {
        if (flashed) setCredentials(flashed);
    }, [flashed]);

    const apply = (next: Partial<{ q: string; role: string }>) => {
        const merged = { ...filters, ...next };
        const query: Record<string, string> = {};
        if (merged.q) query.q = merged.q;
        if (merged.role) query.role = merged.role;
        router.get(index().url, query, { preserveState: true, replace: true });
    };

    const setActive = (user: AdminUser, active: boolean) =>
        router.patch(
            update({ user: user.id }).url,
            { is_active: active },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title={t('admin.users.title')} />

            <div className="mx-auto flex w-full max-w-6xl flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('admin.users.title')}
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {t('admin.users.subtitle')}
                        </p>
                    </div>
                    <Button onClick={() => setForm({ open: true, user: null })}>
                        <Plus />
                        {t('admin.users.add')}
                    </Button>
                </div>

                <form
                    className="flex flex-wrap items-center gap-2"
                    onSubmit={(event) => {
                        event.preventDefault();
                        apply({ q: search });
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
                            placeholder={t('admin.users.search')}
                            aria-label={t('admin.users.search')}
                            className="pl-8"
                        />
                    </div>
                    <Select
                        value={filters.role || ALL}
                        onValueChange={(v) =>
                            apply({ role: v === ALL ? '' : v })
                        }
                    >
                        <SelectTrigger
                            className="w-48"
                            aria-label={t('admin.users.col.roles')}
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>
                                {t('admin.users.all_roles')}
                            </SelectItem>
                            {roles.map((role) => (
                                <SelectItem key={role} value={role}>
                                    {t(`role.${role}`)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </form>

                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>
                                    {t('admin.users.col.name')}
                                </TableHead>
                                <TableHead>
                                    {t('admin.users.col.roles')}
                                </TableHead>
                                <TableHead>
                                    {t('admin.users.col.teams')}
                                </TableHead>
                                <TableHead>
                                    {t('admin.users.col.department')}
                                </TableHead>
                                <TableHead>
                                    {t('admin.users.col.status')}
                                </TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.data.map((user) => (
                                <TableRow
                                    key={user.id}
                                    className={cn(
                                        !user.is_active && 'opacity-60',
                                    )}
                                >
                                    <TableCell>
                                        <div className="font-medium">
                                            {user.name}
                                        </div>
                                        <div className="text-muted-foreground text-xs">
                                            {user.email}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-wrap gap-1">
                                            {user.roles.map((role) => (
                                                <Badge
                                                    key={role}
                                                    variant={
                                                        role === 'ADMIN'
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {t(`role.${role}`)}
                                                </Badge>
                                            ))}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        {user.teams
                                            .map((team) => team.name)
                                            .join(', ') || '—'}
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        {user.department?.name ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        <Badge
                                            variant="outline"
                                            className={cn(
                                                user.is_active
                                                    ? 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                                                    : 'text-muted-foreground',
                                            )}
                                        >
                                            {user.is_active
                                                ? t('admin.users.active')
                                                : t('admin.users.inactive')}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`${t('admin.users.edit')} ${user.name}`}
                                                title={t('admin.users.edit')}
                                                onClick={() =>
                                                    setForm({
                                                        open: true,
                                                        user,
                                                    })
                                                }
                                            >
                                                <Pencil />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`${t('admin.users.reset')} ${user.name}`}
                                                title={t('admin.users.reset')}
                                                onClick={() =>
                                                    setResetting(user)
                                                }
                                            >
                                                <KeyRound />
                                            </Button>
                                            {user.id !== me &&
                                                (user.is_active ? (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`${t('admin.users.deactivate')} ${user.name}`}
                                                        title={t(
                                                            'admin.users.deactivate',
                                                        )}
                                                        onClick={() =>
                                                            setActive(
                                                                user,
                                                                false,
                                                            )
                                                        }
                                                    >
                                                        <UserX />
                                                    </Button>
                                                ) : (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label={`${t('admin.users.activate')} ${user.name}`}
                                                        title={t(
                                                            'admin.users.activate',
                                                        )}
                                                        onClick={() =>
                                                            setActive(
                                                                user,
                                                                true,
                                                            )
                                                        }
                                                    >
                                                        <UserCheck />
                                                    </Button>
                                                ))}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {users.data.length === 0 && (
                                <TableRow className="hover:bg-transparent">
                                    <TableCell
                                        colSpan={6}
                                        className="text-muted-foreground py-12 text-center"
                                    >
                                        {t('admin.users.empty')}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>

            {/* Keyed so each open starts from that person's current values. */}
            {form.open && (
                <UserFormDialog
                    key={form.user?.id ?? 'new'}
                    open
                    onOpenChange={(open) => setForm({ open, user: form.user })}
                    user={form.user}
                    roles={roles}
                    teams={teams}
                    departments={departments}
                />
            )}

            <Dialog
                open={resetting !== null}
                onOpenChange={(open) => !open && setResetting(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('admin.users.reset_title', {
                                name: resetting?.name ?? '',
                            })}
                        </DialogTitle>
                        <DialogDescription>
                            {t('admin.users.reset_hint')}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="ghost"
                            onClick={() => setResetting(null)}
                        >
                            {t('common.cancel')}
                        </Button>
                        <Button
                            onClick={() => {
                                if (!resetting) return;
                                router.post(
                                    resetPassword({ user: resetting.id }).url,
                                    {},
                                    {
                                        preserveScroll: true,
                                        onSuccess: () => setResetting(null),
                                    },
                                );
                            }}
                        >
                            {t('admin.users.reset')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <CredentialsDialog
                credentials={credentials}
                onClose={() => setCredentials(null)}
            />
        </>
    );
}
