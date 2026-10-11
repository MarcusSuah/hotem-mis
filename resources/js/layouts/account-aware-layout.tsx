import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLayout from '@/layouts/app-layout';
import SiteLayout from '@/layouts/site-layout';
import type { BreadcrumbItem } from '@/types';

/**
 * Shared pages (such as profile settings) render inside the back office for
 * staff and inside the public site shell for guests.
 */
export default function AccountAwareLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: ReactNode;
}) {
    const { auth } = usePage().props;

    if (auth.user?.is_staff) {
        return <AppLayout breadcrumbs={breadcrumbs}>{children}</AppLayout>;
    }

    return (
        <SiteLayout>
            <div className="mx-auto w-full max-w-5xl px-4 py-10 sm:px-6">
                {children}
            </div>
        </SiteLayout>
    );
}
