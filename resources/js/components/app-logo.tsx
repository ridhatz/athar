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
                <span className="text-muted-foreground truncate text-[11px] leading-tight">
                    {t('app.tagline')}
                </span>
            </div>
        </>
    );
}
