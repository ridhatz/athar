import { Plus, Trash2 } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useT } from '@/hooks/use-t';
import { cn } from '@/lib/utils';
import type { KpiDirectionName, KpiFrequencyName } from '@/types/athar';

/** One KPI being written: everything except the achievement, which only exists once a month runs. */
export type KpiLine = {
    name: string;
    target_value: string;
    direction: KpiDirectionName;
    unit: string;
    weight: string;
    frequency: KpiFrequencyName;
};

export function emptyLine(): KpiLine {
    return {
        name: '',
        target_value: '',
        direction: 'HIGHER_BETTER',
        unit: '',
        weight: '',
        frequency: 'MONTHLY',
    };
}

const DIRECTIONS: KpiDirectionName[] = ['HIGHER_BETTER', 'LOWER_BETTER'];
const FREQUENCIES: KpiFrequencyName[] = ['MONTHLY', 'QUARTERLY', 'YEARLY'];

/**
 * The shared editor for a list of KPIs, used both when opening one person's sheet and when
 * writing a team's template — the two are the same shape, so they get the same editor.
 *
 * The weight total is shown rather than enforced: 100 is the convention the screens assume, but
 * an administrator mid-edit should not be blocked by a number that is briefly 90.
 */
export function KpiLines({
    lines,
    onChange,
    errors = {},
}: {
    lines: KpiLine[];
    onChange: (lines: KpiLine[]) => void;
    errors?: Record<string, string>;
}) {
    const { t } = useT();

    const total = lines.reduce(
        (sum, line) => sum + (Number(line.weight) || 0),
        0,
    );

    const update = (index: number, patch: Partial<KpiLine>) =>
        onChange(
            lines.map((line, i) =>
                i === index ? { ...line, ...patch } : line,
            ),
        );

    const remove = (index: number) =>
        onChange(lines.filter((_, i) => i !== index));

    // An error on items.2.weight should surface on the third row's weight box.
    const lineError = (index: number, field: string) =>
        errors[`items.${index}.${field}`];

    return (
        <div className="space-y-3">
            {lines.map((line, index) => (
                <div key={index} className="space-y-3 rounded-lg border p-3">
                    <div className="flex items-start gap-2">
                        <div className="flex-1 space-y-2">
                            <Label htmlFor={`kpi-line-${index}-name`}>
                                {t('kpi.item.name')}
                            </Label>
                            <Input
                                id={`kpi-line-${index}-name`}
                                value={line.name}
                                onChange={(e) =>
                                    update(index, { name: e.target.value })
                                }
                                required
                            />
                            <InputError message={lineError(index, 'name')} />
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="mt-7"
                            aria-label={`${t('kpi.item.remove')} ${index + 1}`}
                            title={t('kpi.item.remove')}
                            disabled={lines.length === 1}
                            onClick={() => remove(index)}
                        >
                            <Trash2 />
                        </Button>
                    </div>

                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                        <div className="space-y-2">
                            <Label htmlFor={`kpi-line-${index}-target`}>
                                {t('kpi.item.target')}
                            </Label>
                            <Input
                                id={`kpi-line-${index}-target`}
                                type="number"
                                step="any"
                                value={line.target_value}
                                onChange={(e) =>
                                    update(index, {
                                        target_value: e.target.value,
                                    })
                                }
                                required
                            />
                            <InputError
                                message={lineError(index, 'target_value')}
                            />
                        </div>

                        <div className="space-y-2 lg:col-span-2">
                            <Label htmlFor={`kpi-line-${index}-direction`}>
                                {t('kpi.item.direction')}
                            </Label>
                            <Select
                                value={line.direction}
                                onValueChange={(v) =>
                                    update(index, {
                                        direction: v as KpiDirectionName,
                                    })
                                }
                            >
                                <SelectTrigger
                                    id={`kpi-line-${index}-direction`}
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {DIRECTIONS.map((d) => (
                                        <SelectItem key={d} value={d}>
                                            {t(`kpi.direction.${d}`)}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor={`kpi-line-${index}-unit`}>
                                {t('kpi.item.unit')}
                            </Label>
                            <Input
                                id={`kpi-line-${index}-unit`}
                                value={line.unit}
                                // Not "%" — a placeholder that looks like a real unit reads as
                                // one, and an empty box would seem to say "measured in percent".
                                placeholder={t('common.optional')}
                                onChange={(e) =>
                                    update(index, { unit: e.target.value })
                                }
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor={`kpi-line-${index}-weight`}>
                                {t('kpi.item.weight')}
                            </Label>
                            <Input
                                id={`kpi-line-${index}-weight`}
                                type="number"
                                step="any"
                                min={0.01}
                                max={100}
                                value={line.weight}
                                onChange={(e) =>
                                    update(index, { weight: e.target.value })
                                }
                                required
                            />
                            <InputError message={lineError(index, 'weight')} />
                        </div>
                    </div>

                    <div className="space-y-2 sm:max-w-48">
                        <Label htmlFor={`kpi-line-${index}-frequency`}>
                            {t('kpi.item.assessment')}
                        </Label>
                        <Select
                            value={line.frequency}
                            onValueChange={(v) =>
                                update(index, {
                                    frequency: v as KpiFrequencyName,
                                })
                            }
                        >
                            <SelectTrigger id={`kpi-line-${index}-frequency`}>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {FREQUENCIES.map((f) => (
                                    <SelectItem key={f} value={f}>
                                        {t(`kpi.frequency.${f}`)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>
            ))}

            <div className="flex flex-wrap items-center justify-between gap-2">
                <Button
                    type="button"
                    variant="outline"
                    onClick={() => onChange([...lines, emptyLine()])}
                >
                    <Plus />
                    {t('kpi.item.add')}
                </Button>

                <div className="text-right">
                    <span
                        className={cn(
                            'text-sm font-medium',
                            total === 100
                                ? 'text-emerald-700 dark:text-emerald-300'
                                : 'text-amber-700 dark:text-amber-300',
                        )}
                    >
                        {t('kpi.weight_total', {
                            total: String(Math.round(total * 100) / 100),
                        })}
                    </span>
                    <p className="text-muted-foreground text-xs">
                        {t('kpi.weight_hint')}
                    </p>
                </div>
            </div>

            <InputError message={errors.items} />
        </div>
    );
}
