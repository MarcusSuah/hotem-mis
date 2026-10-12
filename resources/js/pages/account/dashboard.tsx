import { Head, Link } from '@inertiajs/react';
import {
    Award,
    BedDouble,
    CalendarDays,
    Mail,
    Phone,
    UserRound,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatDate, formatNumber } from '@/lib/format';
import account from '@/routes/account';
import { edit } from '@/routes/profile';
import site from '@/routes/site';
import type { MoneyValue } from '@/types';

type Profile = {
    name: string;
    email: string;
    phone: string | null;
    member_since: string | null;
};

type Upcoming = {
    reference: string;
    status: string;
    status_label: string;
    check_in_date: string;
    check_out_date: string;
    nights: number;
    room_type: string | null;
    total: MoneyValue;
};

export default function AccountDashboard({
    profile,
    upcoming,
    stats,
}: {
    profile: Profile;
    upcoming: Upcoming | null;
    stats: { stays: number; loyalty_points: number | null };
}) {
    return (
        <>
            <Head title="My account" />
            <section className="bg-sage/60 dark:bg-muted/40">
                <div className="mx-auto max-w-5xl px-4 py-12 sm:px-6">
                    <p className="text-xs font-semibold tracking-[0.25em] text-highlight-foreground uppercase dark:text-highlight">
                        My account
                    </p>
                    <h1 className="mt-2 font-display text-4xl font-semibold text-primary dark:text-foreground">
                        Welcome, {profile.name}
                    </h1>
                    <p className="mt-2 text-muted-foreground">
                        Manage your profile and stay details in one place.
                    </p>
                </div>
            </section>

            <div className="mx-auto grid max-w-5xl gap-6 px-4 py-10 sm:px-6 md:grid-cols-3">
                <div className="space-y-6 md:col-span-2">
                    <section className="rounded-xl border bg-card p-6 shadow-xs">
                        <div className="flex items-center justify-between gap-2">
                            <h2 className="font-display text-2xl text-primary dark:text-foreground">
                                Your next stay
                            </h2>
                            <Link
                                href={account.reservations.index()}
                                className="text-sm text-primary hover:underline dark:text-highlight"
                            >
                                All bookings
                            </Link>
                        </div>
                        {upcoming ? (
                            <div className="mt-4 space-y-2 text-sm">
                                <p className="flex items-center gap-2 text-base font-medium">
                                    <BedDouble className="size-4 text-highlight" />{' '}
                                    {upcoming.room_type ?? 'Room'} ·{' '}
                                    {upcoming.status_label}
                                </p>
                                <p>
                                    {formatDate(upcoming.check_in_date)} –{' '}
                                    {formatDate(upcoming.check_out_date)} (
                                    {upcoming.nights} nights) ·{' '}
                                    {upcoming.total.formatted}
                                </p>
                                <Button
                                    asChild
                                    variant="outline"
                                    size="sm"
                                    className="mt-2"
                                >
                                    <Link
                                        href={account.reservations.show(
                                            upcoming.reference,
                                        )}
                                    >
                                        View booking {upcoming.reference}
                                    </Link>
                                </Button>
                            </div>
                        ) : (
                            <div className="mt-4 space-y-3 text-sm text-muted-foreground">
                                <p>You have no upcoming stays.</p>
                                <Button asChild variant="gold" size="sm">
                                    <Link href={site.booking.start()}>
                                        Plan a stay
                                    </Link>
                                </Button>
                            </div>
                        )}
                    </section>

                    <section className="rounded-xl border bg-card p-6 shadow-xs">
                        <h2 className="font-display text-2xl text-primary dark:text-foreground">
                            Profile
                        </h2>
                        <dl className="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                            <div className="flex items-center gap-3">
                                <UserRound className="size-4 text-highlight" />
                                <div>
                                    <dt className="text-muted-foreground">
                                        Name
                                    </dt>
                                    <dd className="font-medium">
                                        {profile.name}
                                    </dd>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <Mail className="size-4 text-highlight" />
                                <div>
                                    <dt className="text-muted-foreground">
                                        Email
                                    </dt>
                                    <dd className="font-medium break-all">
                                        {profile.email}
                                    </dd>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <Phone className="size-4 text-highlight" />
                                <div>
                                    <dt className="text-muted-foreground">
                                        Phone
                                    </dt>
                                    <dd className="font-medium">
                                        {profile.phone ?? 'Not provided'}
                                    </dd>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <CalendarDays className="size-4 text-highlight" />
                                <div>
                                    <dt className="text-muted-foreground">
                                        Member since
                                    </dt>
                                    <dd className="font-medium">
                                        {formatDate(profile.member_since)}
                                    </dd>
                                </div>
                            </div>
                        </dl>
                        <Button asChild variant="outline" className="mt-6">
                            <Link href={edit()}>Edit profile</Link>
                        </Button>
                    </section>
                </div>

                <aside className="space-y-4">
                    <div className="rounded-xl border bg-card p-6 shadow-xs">
                        <p className="text-sm text-muted-foreground">
                            Completed stays
                        </p>
                        <p className="font-display text-4xl font-semibold text-primary dark:text-foreground">
                            {stats.stays}
                        </p>
                    </div>
                    {stats.loyalty_points !== null && (
                        <div className="rounded-xl border border-highlight/50 bg-card p-6 shadow-xs">
                            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                                <Award className="size-4 text-highlight" />{' '}
                                Loyalty points
                            </p>
                            <p className="font-display text-4xl font-semibold text-primary dark:text-foreground">
                                {formatNumber(stats.loyalty_points)}
                            </p>
                        </div>
                    )}
                </aside>
            </div>
        </>
    );
}
