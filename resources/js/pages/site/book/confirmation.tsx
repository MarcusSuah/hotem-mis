import { Link, usePage } from '@inertiajs/react';
import { CalendarCheck, Clock, Mail } from 'lucide-react';
import { Seo } from '@/components/site/seo';
import { Button } from '@/components/ui/button';
import { formatDate, formatDateTime } from '@/lib/format';
import { home, register } from '@/routes';
import account from '@/routes/account';
import type { MoneyValue } from '@/types';

type Props = {
    reservation: {
        reference: string;
        status: string;
        status_label: string;
        contact_name: string;
        contact_email: string | null;
        check_in_date: string;
        check_out_date: string;
        nights: number;
        adults: number;
        children: number;
        room_type: string | null;
        total: MoneyValue;
        deposit_due: MoneyValue;
        expires_at: string | null;
    };
    checkInTime: string;
    checkOutTime: string;
    note: string | null;
};

export default function BookingConfirmation({
    reservation,
    checkInTime,
    checkOutTime,
    note,
}: Props) {
    const { auth, hotel } = usePage().props;
    const pending = reservation.status === 'pending';
    const cancelled = reservation.status === 'cancelled';

    return (
        <>
            <Seo title={`Booking ${reservation.reference}`} />
            <div className="mx-auto max-w-3xl space-y-8 px-4 py-14 sm:px-6">
                <div className="space-y-3 text-center">
                    <CalendarCheck className="mx-auto size-10 text-highlight" />
                    <p className="text-xs font-semibold tracking-[0.25em] text-highlight-foreground uppercase dark:text-highlight">
                        {reservation.status_label}
                    </p>
                    <h1 className="font-display text-4xl font-semibold text-primary dark:text-foreground">
                        {cancelled
                            ? 'This booking was cancelled'
                            : pending
                              ? `Thank you, ${reservation.contact_name}`
                              : `You’re booked, ${reservation.contact_name}`}
                    </h1>
                    <p className="text-muted-foreground">
                        Booking reference{' '}
                        <span className="font-mono font-semibold text-foreground">
                            {reservation.reference}
                        </span>
                    </p>
                </div>

                {pending && (
                    <div className="space-y-2 rounded-xl border border-highlight/60 bg-highlight/10 p-5 text-sm">
                        <p className="flex items-center gap-2 font-medium">
                            <Clock className="size-4" /> What happens next
                        </p>
                        <p>
                            {note ??
                                'Our team will confirm your booking shortly.'}
                        </p>
                        {reservation.expires_at && (
                            <p className="text-muted-foreground">
                                Your room is held until{' '}
                                {formatDateTime(reservation.expires_at)}. To
                                confirm, a deposit of{' '}
                                {reservation.deposit_due.formatted} is needed —
                                we’ll contact you to arrange it.
                            </p>
                        )}
                    </div>
                )}

                <dl className="grid gap-4 rounded-xl border bg-card p-6 text-sm shadow-xs sm:grid-cols-2">
                    <div>
                        <dt className="text-muted-foreground">Arrival</dt>
                        <dd className="font-medium">
                            {formatDate(reservation.check_in_date)} from{' '}
                            {checkInTime}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Departure</dt>
                        <dd className="font-medium">
                            {formatDate(reservation.check_out_date)} by{' '}
                            {checkOutTime}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Room</dt>
                        <dd className="font-medium">
                            {reservation.room_type ?? '—'}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">Guests</dt>
                        <dd className="font-medium">
                            {reservation.adults} adults
                            {reservation.children > 0 &&
                                `, ${reservation.children} children`}{' '}
                            · {reservation.nights} nights
                        </dd>
                    </div>
                    <div className="border-t pt-4 sm:col-span-2">
                        <dt className="text-muted-foreground">
                            Total for the stay
                        </dt>
                        <dd className="text-2xl font-semibold text-primary dark:text-foreground">
                            {reservation.total.formatted}
                        </dd>
                    </div>
                </dl>

                {reservation.contact_email && (
                    <p className="flex items-center justify-center gap-2 text-sm text-muted-foreground">
                        <Mail className="size-4" /> A copy has been sent to{' '}
                        {reservation.contact_email}.
                    </p>
                )}

                <div className="flex flex-wrap justify-center gap-3">
                    {auth.user && !auth.user.is_staff ? (
                        <Button asChild variant="gold">
                            <Link href={account.reservations.index()}>
                                View my bookings
                            </Link>
                        </Button>
                    ) : !auth.user ? (
                        <Button asChild variant="gold">
                            <Link href={register()}>
                                Create an account to manage bookings
                            </Link>
                        </Button>
                    ) : null}
                    <Button asChild variant="outline">
                        <Link href={home()}>Back to {hotel.name}</Link>
                    </Button>
                </div>
            </div>
        </>
    );
}
