import type { NotificationEntry } from '@/types/athar';
import type { Auth } from '@/types/auth';

declare module 'react' {
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            can: { create_request: boolean; manage_users: boolean };
            notifications: {
                unread: number;
                recent: NotificationEntry[];
            } | null;
            sidebarOpen: boolean;
            locale: 'id' | 'en';
            translations: Record<string, string>;
            timezone: string;
            utcOffset: string;
            [key: string]: unknown;
        };
    }
}
