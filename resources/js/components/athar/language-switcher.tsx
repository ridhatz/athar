import { router } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import { useT } from '@/hooks/use-t';
import { cn } from '@/lib/utils';
import { update } from '@/routes/locale';

const LANGUAGES = [
    { code: 'id', label: 'ID', name: 'Bahasa Indonesia' },
    { code: 'en', label: 'EN', name: 'English' },
] as const;

/** Signed-in users keep the choice on their account; on the login screen it is remembered in a cookie. */
export function LanguageSwitcher({ className }: { className?: string }) {
    const { t, locale } = useT();

    const choose = (code: string) => {
        if (code !== locale) {
            router.post(
                update().url,
                { locale: code },
                { preserveScroll: true, preserveState: true },
            );
        }
    };

    return (
        <div
            role="group"
            aria-label={t('lang.label')}
            className={cn('inline-flex items-center gap-1', className)}
        >
            <Languages className="text-muted-foreground size-4" aria-hidden />
            {LANGUAGES.map((language) => (
                <button
                    key={language.code}
                    type="button"
                    lang={language.code}
                    title={language.name}
                    aria-pressed={locale === language.code}
                    onClick={() => choose(language.code)}
                    className={cn(
                        'rounded px-1.5 py-0.5 text-xs font-medium transition-colors',
                        locale === language.code
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                    )}
                >
                    {language.label}
                </button>
            ))}
        </div>
    );
}
