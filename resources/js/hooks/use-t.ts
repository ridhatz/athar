import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';
import { makeTranslator } from '@/lib/i18n';
import type { Translate } from '@/lib/i18n';

/** Translation function and the current language, from the props the server shares on every page. */
export function useT(): { t: Translate; locale: 'id' | 'en' } {
    const { translations, locale } = usePage().props;
    const t = useMemo(() => makeTranslator(translations ?? {}), [translations]);

    return { t, locale };
}
