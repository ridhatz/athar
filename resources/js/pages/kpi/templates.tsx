import { Head, router } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
import { useState } from 'react';
import {
    emptyLine,
    KpiLines,
    type KpiLine,
} from '@/components/athar/kpi-lines';
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
import { store, update } from '@/routes/kpi/templates';
import type { KpiTemplate, NamedRef } from '@/types/athar';

type Props = {
    templates: KpiTemplate[];
    teams: NamedRef[];
};

const NO_TEAM = 'none';

function TemplateDialog({
    template,
    teams,
    onClose,
}: {
    template: KpiTemplate | null;
    teams: NamedRef[];
    onClose: () => void;
}) {
    const { t } = useT();
    const editing = template !== null;

    const [name, setName] = useState(template?.name ?? '');
    const [team, setTeam] = useState(
        template?.team ? String(template.team.id) : NO_TEAM,
    );
    const [lines, setLines] = useState<KpiLine[]>(() =>
        template && template.items.length > 0
            ? template.items.map((item) => ({
                  name: item.name,
                  target_value: String(item.target_value),
                  direction: item.direction,
                  unit: item.unit ?? '',
                  weight: String(item.weight),
                  frequency: item.frequency,
              }))
            : [emptyLine()],
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        setProcessing(true);

        const body = {
            name,
            team_id: team === NO_TEAM ? null : Number(team),
            items: lines.map((line) => ({
                name: line.name,
                target_value: Number(line.target_value),
                direction: line.direction,
                unit: line.unit === '' ? null : line.unit,
                weight: Number(line.weight),
                frequency: line.frequency,
            })),
        };

        const options = {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: setErrors,
            onFinish: () => setProcessing(false),
        };

        if (editing) {
            router.patch(update({ template: template.id }).url, body, options);
        } else {
            router.post(store().url, body, options);
        }
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <form onSubmit={submit} className="space-y-4">
                    <DialogHeader>
                        <DialogTitle>
                            {editing
                                ? t('admin.ref.edit')
                                : t('kpi.templates.add')}
                        </DialogTitle>
                    </DialogHeader>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="template-name">
                                {t('kpi.templates.form.name')}
                            </Label>
                            <Input
                                id="template-name"
                                value={name}
                                onChange={(e) => setName(e.target.value)}
                                autoFocus
                                required
                            />
                            <InputError message={errors.name} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="template-team">
                                {t('kpi.templates.form.team')}
                            </Label>
                            <Select value={team} onValueChange={setTeam}>
                                <SelectTrigger id="template-team">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={NO_TEAM}>
                                        {t('kpi.templates.fallback')}
                                    </SelectItem>
                                    {teams.map((team) => (
                                        <SelectItem
                                            key={team.id}
                                            value={String(team.id)}
                                        >
                                            {team.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.team_id} />
                        </div>
                    </div>

                    <KpiLines
                        lines={lines}
                        onChange={setLines}
                        errors={errors}
                    />

                    <p className="text-muted-foreground text-xs">
                        {t('kpi.templates.replace_warning')}
                    </p>

                    <DialogFooter>
                        <Button type="button" variant="ghost" onClick={onClose}>
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

export default function KpiTemplates({ templates, teams }: Props) {
    const { t } = useT();

    const [form, setForm] = useState<{
        open: boolean;
        template: KpiTemplate | null;
    }>({ open: false, template: null });

    return (
        <>
            <Head title={t('kpi.templates.title')} />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-5 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {t('kpi.templates.title')}
                        </h1>
                        <p className="text-muted-foreground text-sm">
                            {t('kpi.templates.subtitle')}
                        </p>
                    </div>
                    <Button
                        onClick={() => setForm({ open: true, template: null })}
                    >
                        <Plus />
                        {t('kpi.templates.add')}
                    </Button>
                </div>

                <div className="rounded-lg border">
                    <Table>
                        <TableHeader>
                            <TableRow className="hover:bg-transparent">
                                <TableHead>
                                    {t('kpi.templates.col.name')}
                                </TableHead>
                                <TableHead>
                                    {t('kpi.templates.col.team')}
                                </TableHead>
                                <TableHead className="w-24">
                                    {t('kpi.templates.col.items')}
                                </TableHead>
                                <TableHead className="w-32">
                                    {t('kpi.templates.col.weight')}
                                </TableHead>
                                <TableHead />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {templates.map((template) => (
                                <TableRow key={template.id}>
                                    <TableCell className="font-medium">
                                        {template.name}
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        {template.team?.name ?? (
                                            <span className="text-muted-foreground">
                                                {t('kpi.templates.fallback')}
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-sm">
                                        {template.items.length}
                                    </TableCell>
                                    <TableCell>
                                        {/* 100 is the convention; anything else is worth noticing. */}
                                        <Badge
                                            variant="outline"
                                            className={cn(
                                                template.total_weight === 100
                                                    ? 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                                                    : 'border-amber-300 bg-amber-50 text-amber-800 dark:bg-amber-950 dark:text-amber-200',
                                            )}
                                        >
                                            {template.total_weight}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end">
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                aria-label={`${t('admin.ref.edit')} ${template.name}`}
                                                title={t('admin.ref.edit')}
                                                onClick={() =>
                                                    setForm({
                                                        open: true,
                                                        template,
                                                    })
                                                }
                                            >
                                                <Pencil />
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                            {templates.length === 0 && (
                                <TableRow className="hover:bg-transparent">
                                    <TableCell
                                        colSpan={5}
                                        className="text-muted-foreground py-12 text-center"
                                    >
                                        {t('kpi.templates.empty')}
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </div>

            {form.open && (
                <TemplateDialog
                    key={form.template?.id ?? 'new'}
                    template={form.template}
                    teams={teams}
                    onClose={() =>
                        setForm({ open: false, template: form.template })
                    }
                />
            )}
        </>
    );
}
