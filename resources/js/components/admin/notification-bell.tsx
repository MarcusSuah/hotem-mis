import { Link, router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { formatDateTime } from '@/lib/format';
import admin from '@/routes/admin';

/**
 * Header bell showing the signed-in staff member's unread notifications.
 */
export function NotificationBell() {
    const { notifications } = usePage().props;

    if (!notifications) {
        return null;
    }

    return (
        <Popover>
            <PopoverTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative"
                    aria-label={`Notifications, ${notifications.unread} unread`}
                >
                    <Bell />
                    {notifications.unread > 0 && (
                        <span className="absolute top-1 right-1 flex min-w-4 items-center justify-center rounded-full bg-highlight px-1 text-[10px] leading-4 font-semibold text-teal">
                            {notifications.unread > 9
                                ? '9+'
                                : notifications.unread}
                        </span>
                    )}
                </Button>
            </PopoverTrigger>
            <PopoverContent align="end" className="w-80 p-0">
                <div className="flex items-center justify-between border-b px-4 py-2">
                    <p className="text-sm font-semibold">Notifications</p>
                    {notifications.unread > 0 && (
                        <button
                            type="button"
                            className="text-xs text-primary hover:underline"
                            onClick={() =>
                                router.post(
                                    admin.notifications.readAll.url(),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Mark all read
                        </button>
                    )}
                </div>
                {notifications.recent.length === 0 ? (
                    <p className="p-4 text-sm text-muted-foreground">
                        You're all caught up.
                    </p>
                ) : (
                    <ul className="max-h-80 divide-y overflow-y-auto">
                        {notifications.recent.map((notification) => (
                            <li key={notification.id}>
                                <button
                                    type="button"
                                    className="w-full px-4 py-2 text-left text-sm hover:bg-muted"
                                    onClick={() =>
                                        router.post(
                                            admin.notifications.read.url(
                                                notification.id,
                                            ),
                                        )
                                    }
                                >
                                    <span className="font-medium">
                                        {notification.title}
                                    </span>
                                    <span className="block text-muted-foreground">
                                        {notification.message}
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        {formatDateTime(
                                            notification.created_at,
                                        )}
                                    </span>
                                </button>
                            </li>
                        ))}
                    </ul>
                )}
                <Link
                    href={admin.notifications.index()}
                    className="block border-t px-4 py-2 text-center text-sm text-primary hover:underline"
                >
                    View all
                </Link>
            </PopoverContent>
        </Popover>
    );
}
