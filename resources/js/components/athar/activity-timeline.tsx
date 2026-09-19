import { Badge } from '@/components/ui/badge';
import { useFormat } from '@/hooks/use-format';
import { useT } from '@/hooks/use-t';
import type { ActivityEntry } from '@/types/athar';

const MAX_TEXT = 80;

function clip(value: string): string {
    return value.length > MAX_TEXT ? `${value.slice(0, MAX_TEXT)}…` : value;
}

/** The immutable history of a request, oldest first, rendered as readable sentences. */
export function ActivityTimeline({ entries }: { entries: ActivityEntry[] }) {
    const { t } = useT();
    const format = useFormat();

    const show = (entry: ActivityEntry, value: string | null): string => {
        if (value === null || value === '') {
            return '—';
        }

        switch (entry.action) {
            case 'STATUS_CHANGED':
                return t(`status.${value}`);
            case 'PRIORITY_CHANGED':
                return t(`priority.${value}`);
            case 'DEADLINE_CHANGED':
                return format.dateTime(value);
            default:
                return clip(value);
        }
    };

    const describe = (entry: ActivityEntry): string =>
        t(`activity.${entry.action}`, {
            actor: entry.actor?.name ?? t('common.system'),
            old: show(entry, entry.old_value),
            new: show(entry, entry.new_value),
            field: entry.field ? t(`activity.field.${entry.field}`) : '',
        });

    if (entries.length === 0) {
        return (
            <p className="text-muted-foreground text-sm">
                {t('activity.empty')}
            </p>
        );
    }

    return (
        <ol className="relative ml-2 space-y-5 border-l pl-6">
            {entries.map((entry) => (
                <li key={entry.id} className="relative">
                    <span
                        aria-hidden
                        className="bg-border ring-background absolute top-1.5 -left-[31px] size-2.5 rounded-full ring-4"
                    />
                    <p className="text-sm leading-relaxed">{describe(entry)}</p>
                    {entry.note && (
                        <blockquote className="text-muted-foreground mt-1 border-l-2 pl-3 text-sm italic">
                            {entry.note}
                        </blockquote>
                    )}
                    <div className="text-muted-foreground mt-1 flex items-center gap-2 text-xs">
                        <time dateTime={entry.created_at}>
                            {format.dateTime(entry.created_at)}
                        </time>
                        {entry.is_internal && (
                            <Badge variant="secondary">
                                {t('activity.internal')}
                            </Badge>
                        )}
                    </div>
                </li>
            ))}
        </ol>
    );
}
