import { Head, Link } from '@inertiajs/react';
import { CalendarDays } from 'lucide-react';
import { EmptyNotice } from '@/components/site/site-ui';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import account from '@/routes/account';
import site from '@/routes/site';
import type { MoneyValue } from '@/types';

export type AccountReservation = {
    reference: string;
    status: string;
    status_label: string;
    check_in_date: string;
    check_out_date: string;
    nights: number;
    adults: number;
    children: number;
    room_type: string | null;
    total: MoneyValue;
};

export const statusVariant: Record<
    string,
    'warning' | 'success' | 'secondary' | 'muted' | 'danger' | 'gold'
> = {
    pending: 'warning',
    confirmed: 'success',
    checked_in: 'gold',
    checked_out: 'secondary',
    cancelled: 'muted',
    no_show: 'danger',
};

export default function AccountReservations({
    reservations,
}: {
    reservations: AccountReservation[];
}) {
    return (
        <>
            <Head title="My bookings" />
            <div className="mx-auto max-w-5xl space-y-6 px-4 py-12 sm:px-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-xs font-semibold tracking-[0.25em] text-highlight-foreground uppercase dark:text-highlight">
                            My account
                        </p>
                        <h1 className="mt-2 font-display text-4xl font-semibold text-primary dark:text-foreground">
                            My bookings
                        </h1>
                    </div>
                    <Button asChild variant="gold">
                        <Link href={site.booking.start()}>Book a new stay</Link>
                    </Button>
                </div>
                {reservations.length === 0 ? (
                    <EmptyNotice>
                        You have no bookings yet. Bookings made with the email
                        on your account appear here.
                    </EmptyNotice>
                ) : (
                    <ul className="space-y-3">
                        {reservations.map((reservation) => (
                            <li key={reservation.reference}>
                                <Link
                                    href={account.reservations.show(
                                        reservation.reference,
                                    )}
                                    className="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-card p-5 shadow-xs transition hover:shadow-md"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {reservation.room_type ?? 'Room'}{' '}
                                            <span className="font-mono text-xs text-muted-foreground">
                                                {reservation.reference}
                                            </span>
                                        </p>
                                        <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
                                            <CalendarDays className="size-4" />
                                            {formatDate(
                                                reservation.check_in_date,
                                            )}{' '}
                                            –{' '}
                                            {formatDate(
                                                reservation.check_out_date,
                                            )}{' '}
                                            · {reservation.nights} nights
                                        </p>
                                    </div>
                                    <div className="flex items-center gap-3">
                                        <Badge
                                            variant={
                                                statusVariant[
                                                    reservation.status
                                                ] ?? 'secondary'
                                            }
                                        >
                                            {reservation.status_label}
                                        </Badge>
                                        <span className="font-medium">
                                            {reservation.total.formatted}
                                        </span>
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}
