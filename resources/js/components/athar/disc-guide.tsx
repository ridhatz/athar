import { ChevronDown, ChevronUp } from 'lucide-react';
import { useState } from 'react';
import { useT } from '@/hooks/use-t';
import { cn } from '@/lib/utils';

type Dimension = 'd' | 'i' | 's' | 'c';

const DIMENSIONS: Dimension[] = ['d', 'i', 's', 'c'];

/**
 * The classic DISC colour mapping (red/yellow/green/blue for D/I/S/C) — not an Athar choice,
 * it's how Everything DiSC and most other publishers already colour their own materials, so
 * reusing it is what makes the legend recognisable rather than a new scheme to learn.
 */
const DIMENSION_TONE: Record<Dimension, string> = {
    d: 'border-rose-300 bg-rose-50 dark:border-rose-900 dark:bg-rose-950',
    i: 'border-amber-300 bg-amber-50 dark:border-amber-900 dark:bg-amber-950',
    s: 'border-emerald-300 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950',
    c: 'border-blue-300 bg-blue-50 dark:border-blue-900 dark:bg-blue-950',
};

const DIMENSION_LABEL_TONE: Record<Dimension, string> = {
    d: 'text-rose-800 dark:text-rose-200',
    i: 'text-amber-800 dark:text-amber-200',
    s: 'text-emerald-800 dark:text-emerald-200',
    c: 'text-blue-800 dark:text-blue-200',
};

/**
 * A plain-language reference for reading the D/I/S/C columns in the roster below: what each
 * dimension tends to look like at its best, where it tends to trip up, and one concrete thing to
 * work on — not just a letter and a number. Collapsible because it's long, but defaults open
 * since the point is for an admin to actually read it, not hunt for it.
 *
 * Content is synthesised from several 2020–2026 sources (see the list below), not any one of
 * them verbatim — DISC itself has no single canonical phrasing, and vendors disagree on detail.
 */
export function DiscGuide() {
    const { t } = useT();
    const [open, setOpen] = useState(true);

    return (
        <div className="space-y-4 rounded-lg border p-4">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h2 className="text-lg font-semibold">
                        {t('assessment.guide.title')}
                    </h2>
                    <p className="text-muted-foreground text-sm">
                        {t('assessment.guide.intro')}
                    </p>
                </div>
                <button
                    type="button"
                    onClick={() => setOpen((v) => !v)}
                    className="text-muted-foreground hover:text-foreground inline-flex shrink-0 items-center gap-1 text-sm"
                >
                    {open ? <ChevronUp /> : <ChevronDown />}
                    {t(
                        open
                            ? 'assessment.guide.toggle_hide'
                            : 'assessment.guide.toggle_show',
                    )}
                </button>
            </div>

            {open && (
                <>
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {DIMENSIONS.map((dim) => (
                            <div
                                key={dim}
                                className={cn(
                                    'space-y-2 rounded-lg border p-3 text-sm',
                                    DIMENSION_TONE[dim],
                                )}
                            >
                                <div>
                                    <h3
                                        className={cn(
                                            'font-semibold',
                                            DIMENSION_LABEL_TONE[dim],
                                        )}
                                    >
                                        {t(`assessment.guide.${dim}_name`)}
                                    </h3>
                                    <p className="text-muted-foreground text-xs">
                                        {t(`assessment.guide.${dim}_summary`)}
                                    </p>
                                </div>

                                <div>
                                    <p className="text-xs font-medium">
                                        {t('assessment.guide.pros')}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        {t(`assessment.guide.${dim}_pros`)}
                                    </p>
                                </div>

                                <div>
                                    <p className="text-xs font-medium">
                                        {t('assessment.guide.cons')}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        {t(`assessment.guide.${dim}_cons`)}
                                    </p>
                                </div>

                                <div>
                                    <p className="text-xs font-medium">
                                        {t('assessment.guide.growth')}
                                    </p>
                                    <p className="text-muted-foreground text-xs">
                                        {t(`assessment.guide.${dim}_growth`)}
                                    </p>
                                </div>
                            </div>
                        ))}
                    </div>

                    <p className="text-muted-foreground text-xs">
                        {t('assessment.guide.role_fit_note')}
                    </p>

                    <div className="space-y-1 border-t pt-3">
                        <p className="text-muted-foreground text-xs">
                            {t('assessment.guide.disclaimer')}
                        </p>
                        <p className="text-muted-foreground text-xs">
                            {t('assessment.guide.sources_label')}{' '}
                            <a
                                href="https://www.discprofile.com/disc-styles"
                                target="_blank"
                                rel="noreferrer"
                                className="underline underline-offset-2"
                            >
                                Everything DiSC (Wiley)
                            </a>
                            {', '}
                            <a
                                href="https://blog.ttisi.com/professional-development-for-each-behavioral-style"
                                target="_blank"
                                rel="noreferrer"
                                className="underline underline-offset-2"
                            >
                                TTI Success Insights (2025)
                            </a>
                            {', '}
                            <a
                                href="https://www.thomas.co/resources/type/hr-blog/complete-guide-disc-personality-testing"
                                target="_blank"
                                rel="noreferrer"
                                className="underline underline-offset-2"
                            >
                                Thomas.co
                            </a>
                        </p>
                    </div>
                </>
            )}
        </div>
    );
}
