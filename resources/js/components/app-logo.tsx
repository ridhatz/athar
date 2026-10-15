import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';
import { useT } from '@/hooks/use-t';

export default function AppLogo() {
    const { name } = usePage().props;
    const { t } = useT();

    return (
        <>
            <div className="bg-sidebar-primary text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center rounded-md">
                <AppLogoIcon className="size-6 fill-current" />
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm">
                <span className="truncate leading-tight font-semibold">
                    {name}
                </span>
                {/* A different line than the login page's app.tagline: this one names what the
                    product is ("Management Operational System"), not the brand motto, so this
                    key is sidebar-only and app.tagline elsewhere is untouched. */}
                <span className="text-sidebar-foreground/70 truncate text-[11px] leading-tight">
                    {t('app.sidebar_subtitle')}
                </span>
            </div>
        </>
    );
}
