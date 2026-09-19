import { usePage } from '@inertiajs/react';
import { useMemo } from 'react';

/**
 * Date formatting in the organisation's display zone (Asia/Jakarta by default), in the UI language.
 * The server stores everything in UTC; this is the only place times become human.
 */
export function useFormat() {
    const { locale, timezone, utcOffset } = usePage().props;
    const tag = locale === 'en' ? 'en-GB' : 'id-ID';

    return useMemo(() => {
        const dateTime = new Intl.DateTimeFormat(tag, {
            timeZone: timezone,
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
        const date = new Intl.DateTimeFormat(tag, {
            timeZone: timezone,
            day: 'numeric',
            month: 'short',
            year: 'numeric',
        });
        // en-CA renders YYYY-MM-DD, which is exactly what <input type="date"> wants.
        const isoDay = new Intl.DateTimeFormat('en-CA', { timeZone: timezone });

        return {
            dateTime: (iso: string | null | undefined) =>
                iso ? dateTime.format(new Date(iso)) : '—',
            date: (iso: string | null | undefined) =>
                iso ? date.format(new Date(iso)) : '—',
            /** Today's date (YYYY-MM-DD) in the display zone. */
            today: () => isoDay.format(new Date()),
            /** An ISO timestamp as YYYY-MM-DD in the display zone, for date inputs. */
            toInputDate: (iso: string) => isoDay.format(new Date(iso)),
            /** "2026-10-12" means the END of that day in the display zone, sent to the API as an unambiguous instant. */
            endOfDay: (day: string) =>
                new Date(`${day}T23:59:00${utcOffset}`).toISOString(),
        };
    }, [tag, timezone, utcOffset]);
}
