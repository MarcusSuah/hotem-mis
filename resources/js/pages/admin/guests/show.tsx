import { Head, Link } from '@inertiajs/react';
import { IdCard, Mail, MapPin, Pencil, Phone, Star } from 'lucide-react';
import { useState } from 'react';
import { GuestFormDialog } from '@/components/admin/guests/guest-form-dialog';
import type { EditableGuest } from '@/components/admin/guests/guest-form-dialog';
import { PageHeader } from '@/components/admin/page-header';
import { StatCard } from '@/components/admin/stat-card';
import { ReservationStatusBadge } from '@/components/admin/status-badges';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate } from '@/lib/format';
import admin from '@/routes/admin';
import type { ReservationSummary } from '../reservations/index';

type Guest = EditableGuest & {
    name: string;
    initials: string;
    identity_number: string | null;
    has_account: boolean;
};

export default function GuestShow({
    guest,
    reservations,
    stats,
    currencies,
    can,
}: {
    guest: Guest;
    reservations: ReservationSummary[];
    stats: {
        completed_stays: number;
        nights: number;
        cancellations: number;
        no_shows: number;
    };
    currencies: string[];
    can: { update: boolean; viewIdentity: boolean };
}) {
    const [editing, setEditing] = useState(false);

    return (
        <>
            <Head title={guest.name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={guest.name}
                    description={
                        guest.has_account
                            ? 'Has an online account'
                            : 'Guest profile'
                    }
                    actions={
                        can.update && (
                            <Button
                                variant="outline"
                                onClick={() => setEditing(true)}
                            >
                                <Pencil />
                                Edit profile
                            </Button>
                        )
                    }
                />
                <div className="flex flex-wrap gap-2">
                    {guest.is_vip && (
                        <Badge variant="gold">
                            <Star />
                            VIP
                        </Badge>
                    )}
                    {stats.completed_stays >= 2 && (
                        <Badge variant="secondary">Repeat guest</Badge>
                    )}
                    <Badge
                        variant={guest.marketing_consent ? 'success' : 'muted'}
                    >
                        {guest.marketing_consent
                            ? 'Marketing opt-in'
                            : 'No marketing'}
                    </Badge>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Completed stays"
                        value={stats.completed_stays}
                    />
                    <StatCard label="Nights stayed" value={stats.nights} />
                    <StatCard
                        label="Cancellations"
                        value={stats.cancellations}
                    />
                    <StatCard label="No-shows" value={stats.no_shows} />
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <section className="space-y-3 rounded-xl border bg-card p-5 text-sm shadow-xs">
                        <h2 className="font-display text-xl font-semibold">
                            Contact
                        </h2>
                        {guest.email && (
                            <p className="flex items-center gap-2">
                                <Mail className="size-4 text-highlight" />{' '}
                                {guest.email}
                            </p>
                        )}
                        {guest.phone && (
                            <p className="flex items-center gap-2">
                                <Phone className="size-4 text-highlight" />{' '}
                                {guest.phone}
                            </p>
                        )}
                        {guest.address && (
                            <p className="flex items-center gap-2">
                                <MapPin className="size-4 text-highlight" />{' '}
                                {guest.address}{' '}
                                {guest.country_code &&
                                    `(${guest.country_code})`}
                            </p>
                        )}
                        {can.viewIdentity && guest.identity_type && (
                            <p className="flex items-center gap-2">
                                <IdCard className="size-4 text-highlight" />{' '}
                                {guest.identity_type.replace('_', ' ')}:{' '}
                                {guest.identity_number}
                            </p>
                        )}
                        {guest.notes && (
                            <p className="rounded-md bg-muted/60 p-2 whitespace-pre-line">
                                {guest.notes}
                            </p>
                        )}
                    </section>

                    <section className="rounded-xl border bg-card shadow-xs lg:col-span-2">
                        <h2 className="border-b p-4 font-display text-xl font-semibold">
                            Reservations
                        </h2>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Reference</TableHead>
                                    <TableHead>Stay</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        Total
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {reservations.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={4}
                                            className="text-center text-muted-foreground"
                                        >
                                            No reservations yet.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {reservations.map((reservation) => (
                                    <TableRow key={reservation.id}>
                                        <TableCell>
                                            <Link
                                                href={admin.reservations.show(
                                                    reservation.reference,
                                                )}
                                                className="font-mono font-medium hover:underline"
                                            >
                                                {reservation.reference}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            {formatDate(
                                                reservation.check_in_date,
                                            )}{' '}
                                            →{' '}
                                            {formatDate(
                                                reservation.check_out_date,
                                            )}
                                            <span className="block text-xs text-muted-foreground">
                                                Room{' '}
                                                {reservation.room?.number ??
                                                    '—'}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <ReservationStatusBadge
                                                status={reservation.status}
                                                label={reservation.status_label}
                                            />
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {reservation.total.formatted}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </section>
                </div>
            </div>
            <GuestFormDialog
                open={editing}
                onOpenChange={setEditing}
                guest={guest}
                currencies={currencies}
                canRecordIdentity={can.viewIdentity}
            />
        </>
    );
}

GuestShow.layout = {
    breadcrumbs: [{ title: 'Guests', href: admin.guests.index() }],
};
