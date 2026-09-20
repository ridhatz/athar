import type { LoadLevel, RequestPriority } from '@/types/athar';

/**
 * Green, yellow, orange, red. Priority is chosen by people (never inferred), so the colour
 * simply shows what they chose. It is never the only signal: the label is always written too.
 */
export const PRIORITY_COLOUR: Record<RequestPriority, string> = {
    LOW: 'bg-emerald-500',
    NORMAL: 'bg-yellow-400',
    HIGH: 'bg-orange-500',
    CRITICAL: 'bg-red-600',
};

/** The same four colours for how loaded a person is. */
export const LEVEL_COLOUR: Record<LoadLevel, string> = {
    green: 'bg-emerald-500',
    yellow: 'bg-yellow-400',
    orange: 'bg-orange-500',
    red: 'bg-red-600',
};

export const LEVEL_TEXT: Record<LoadLevel, string> = {
    green: 'text-emerald-700 dark:text-emerald-400',
    yellow: 'text-yellow-700 dark:text-yellow-400',
    orange: 'text-orange-700 dark:text-orange-400',
    red: 'text-red-700 dark:text-red-400',
};
