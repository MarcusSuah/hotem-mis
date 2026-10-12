import { Head, router } from '@inertiajs/react';
import { Bell, CheckCheck } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';
import type { Paginated } from '@/types';

type Notification = {
    id: string;
    title: string;
    message: string;
    url: string | null;
    level: string;
    read: boolean;
    created_at: string | null;
};

export default function Notifications({
    notifications,
}: {
    notifications: Paginated<Notification>;
}) {
    const hasUnread = notifications.data.some(
        (notification) => !notification.read,
    );

    return (
        <>
            <Head title="Notifications" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Notifications"
                    description="Assignments, stock alerts and other updates for you."
                    actions={
                        hasUnread && (
                            <Button
                                variant="outline"
                                onClick={() =>
                                    router.post(
                                        admin.notifications.readAll.url(),
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <CheckCheck />
                                Mark all read
                            </Button>
                        )
                    }
                />
                <div className="rounded-xl border bg-card shadow-xs">
                    {notifications.data.length === 0 ? (
                        <EmptyState icon={Bell} title="No notifications" />
                    ) : (
                        <ul className="divide-y">
                            {notifications.data.map((notification) => (
                                <li key={notification.id}>
                                    <button
                                        type="button"
                                        className={cn(
                                            'flex w-full items-start gap-3 p-4 text-left text-sm hover:bg-muted/60',
                                            notification.read &&
                                                'text-muted-foreground',
                                        )}
                                        onClick={() =>
                                            router.post(
                                                admin.notifications.read.url(
                                                    notification.id,
                                                ),
                                            )
                                        }
                                    >
                                        <span
                                            className={cn(
                                                'mt-1.5 size-2 shrink-0 rounded-full',
                                                notification.read
                                                    ? 'bg-transparent'
                                                    : notification.level ===
                                                        'warning'
                                                      ? 'bg-highlight'
                                                      : 'bg-primary',
                                            )}
                                        />
                                        <span className="flex-1">
                                            <span className="font-medium text-foreground">
                                                {notification.title}
                                            </span>
                                            <span className="block">
                                                {notification.message}
                                            </span>
                                        </span>
                                        <span className="text-xs whitespace-nowrap">
                                            {formatDateTime(
                                                notification.created_at,
                                            )}
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                    <div className="border-t p-4">
                        <Pagination paginator={notifications} />
                    </div>
                </div>
            </div>
        </>
    );
}

Notifications.layout = {
    breadcrumbs: [
        { title: 'Notifications', href: admin.notifications.index() },
    ],
};
