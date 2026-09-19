import AuthLayoutTemplate from '@/layouts/auth/auth-simple-layout';
import { useT } from '@/hooks/use-t';

/**
 * `title` and `description` may be translation keys (login.title) or plain text: an unknown key is
 * returned unchanged, so pages that still use English literals keep working.
 */
export default function AuthLayout({
    title = '',
    description = '',
    children,
}: {
    title?: string;
    description?: string;
    children: React.ReactNode;
}) {
    const { t } = useT();

    return (
        <AuthLayoutTemplate
            title={title && t(title)}
            description={description && t(description)}
        >
            {children}
        </AuthLayoutTemplate>
    );
}
