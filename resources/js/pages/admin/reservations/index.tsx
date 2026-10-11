import { Head, Link } from '@inertiajs/react';
import { ArrowDownUp, CalendarDays, Plus, Search, Star } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import {
    PaymentStatusBadge,
    ReservationStatusBadge,
} from '@/components/admin/status-badges';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useFilters } from '@/hooks/use-filters';
import { formatDate } from '@/lib/format';
import admin from '@/routes/admin';
import type { MoneyValue, Paginated } from '@/types';

export type ReservationSummary = {
    id: number;
    reference: string;
    guest?: {
        id: number;
        name: string;
        email: string | null;
        phone: string | null;
        is_vip: boolean;
    };
    status: string;
    status_label: string;
    payment_status: string;
    payment_status_label: string;
    source: string;
    source_label: string;
    check_in_date: string;
    check_out_date: string;
    nights: number;
    adults: number;
    children: number;
    room: { id: number; number: string | null; type: string | null } | null;
    total: MoneyValue;
    balance_due: MoneyValue;
    is_complimentary: boolean;
    created_at: string | null;
};

type Option = { value: string; label: string };

type Filters = {
    search: string;
    status: string;
    payment_status: string;
    source: string;
    from: string;
    to: string;
    sort: string;
};

export default function ReservationsIndex({
    reservations,
    filters: initialFilters,
    options,
    can,
}: {
    reservations: Paginated<ReservationSummary>;
    filters: Filters;
    options: {
        statuses: Option[];
        paymentStatuses: Option[];
        sources: Option[];
    };
    can: { create: boolean };
}) {
    const { filters, setFilter, reset } = useFilters<Filters>(
        admin.reservations.index.url(),
        initialFilters,
    );
    const hasFilters = Object.entries(filters).some(
        ([key, value]) => key !== 'sort' && value !== '',
    );

    function toggleSort(column: string) {
        setFilter(
            'sort',
            filters.sort === `-${column}` ? column : `-${column}`,
            true,
        );
    }

    return (
        <>
            <Head title="Reservations" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Reservations"
                    description="Every booking across all channels. Search by reference, guest name, email or phone."
                    actions={
                        <>
                            <Button variant="outline" asChild>
                                <Link href={admin.availability.index()}>
                                    <CalendarDays />
                                    Availability
                                </Link>
                            </Button>
                            {can.create && (
                                <Button asChild>
                                    <Link href={admin.reservations.create()}>
                                        <Plus />
                                        New reservation
                                    </Link>
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="rounded-xl border bg-card shadow-xs">
                    <div className="grid gap-3 border-b p-4 sm:grid-cols-2 lg:grid-cols-6">
                        <div className="relative sm:col-span-2">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                aria-label="Search reservations"
                                placeholder="Reference, guest, email or phone"
                                className="pl-9"
                                value={filters.search}
                                onChange={(event) =>
                                    setFilter('search', event.target.value)
                                }
                            />
                        </div>
                        <OptionSelect
                            label="Status"
                            value={filters.status}
                            options={options.statuses}
                            onChange={(value) =>
                                setFilter('status', value, true)
                            }
                        />
                        <OptionSelect
                            label="Payment"
                            value={filters.payment_status}
                            options={options.paymentStatuses}
                            onChange={(value) =>
                                setFilter('payment_status', value, true)
                            }
                        />
                        <OptionSelect
                            label="Channel"
                            value={filters.source}
                            options={options.sources}
                            onChange={(value) =>
                                setFilter('source', value, true)
                            }
                        />
                        <div className="flex gap-2">
                            <Input
                                type="date"
                                aria-label="Staying from"
                                value={filters.from}
                                onChange={(event) =>
                                    setFilter('from', event.target.value, true)
                                }
                            />
                            <Input
                                type="date"
                                aria-label="Staying until"
                                value={filters.to}
                                onChange={(event) =>
                                    setFilter('to', event.target.value, true)
                                }
                            />
                        </div>
                    </div>
                    {hasFilters && (
                        <div className="flex justify-end border-b px-4 py-2">
                            <Button variant="ghost" size="sm" onClick={reset}>
                                Clear filters
                            </Button>
                        </div>
                    )}

                    {reservations.data.length === 0 ? (
                        <EmptyState
                            icon={CalendarDays}
                            title="No reservations found"
                            description={
                                hasFilters
                                    ? 'Try adjusting the filters.'
                                    : 'New bookings will appear here.'
                            }
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Reference</TableHead>
                                    <TableHead>Guest</TableHead>
                                    <TableHead>
                                        <button
                                            type="button"
                                            className="inline-flex items-center gap-1 uppercase"
                                            onClick={() =>
                                                toggleSort('check_in_date')
                                            }
                                        >
                                            Stay{' '}
                                            <ArrowDownUp className="size-3" />
                                        </button>
                                    </TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Room
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="hidden lg:table-cell">
                                        Channel
                                    </TableHead>
                                    <TableHead className="text-right">
                                        <button
                                            type="button"
                                            className="inline-flex items-center gap-1 uppercase"
                                            onClick={() =>
                                                toggleSort('total_minor')
                                            }
                                        >
                                            Total{' '}
                                            <ArrowDownUp className="size-3" />
                                        </button>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {reservations.data.map((reservation) => (
                                    <TableRow key={reservation.id}>
                                        <TableCell>
                                            <Link
                                                href={admin.reservations.show(
                                                    reservation.reference,
                                                )}
                                                className="font-mono font-semibold text-primary underline-offset-4 hover:underline dark:text-highlight"
                                            >
                                                {reservation.reference}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            <span className="flex items-center gap-1 font-medium">
                                                {reservation.guest?.name}
                                                {reservation.guest?.is_vip && (
                                                    <Star
                                                        className="size-3.5 fill-highlight text-highlight"
                                                        aria-label="VIP"
                                                    />
                                                )}
                                            </span>
                                            <span className="block text-xs text-muted-foreground">
                                                {reservation.adults} adult
                                                {reservation.adults === 1
                                                    ? ''
                                                    : 's'}
                                                {reservation.children > 0 &&
                                                    `, ${reservation.children} child${reservation.children === 1 ? '' : 'ren'}`}
                                            </span>
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
                                                {reservation.nights} night
                                                {reservation.nights === 1
                                                    ? ''
                                                    : 's'}
                                            </span>
                                        </TableCell>
                                        <TableCell className="hidden md:table-cell">
                                            {reservation.room?.number ?? '—'}
                                            <span className="block text-xs text-muted-foreground">
                                                {reservation.room?.type}
                                            </span>
                                        </TableCell>
                                        <TableCell className="space-y-1">
                                            <ReservationStatusBadge
                                                status={reservation.status}
                                                label={reservation.status_label}
                                            />
                                            <div>
                                                <PaymentStatusBadge
                                                    status={
                                                        reservation.payment_status
                                                    }
                                                    label={
                                                        reservation.payment_status_label
                                                    }
                                                />
                                            </div>
                                        </TableCell>
                                        <TableCell className="hidden lg:table-cell">
                                            {reservation.source_label}
                                        </TableCell>
                                        <TableCell className="text-right font-medium">
                                            {reservation.is_complimentary
                                                ? 'Complimentary'
                                                : reservation.total.formatted}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                    <div className="border-t p-4">
                        <Pagination paginator={reservations} />
                    </div>
                </div>
            </div>
        </>
    );
}

function OptionSelect({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value: string;
    options: Option[];
    onChange: (value: string) => void;
}) {
    return (
        <Select
            value={value || 'all'}
            onValueChange={(next) => onChange(next === 'all' ? '' : next)}
        >
            <SelectTrigger aria-label={label} className="w-full">
                <SelectValue placeholder={label} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">Any {label.toLowerCase()}</SelectItem>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

ReservationsIndex.layout = {
    breadcrumbs: [{ title: 'Reservations', href: admin.reservations.index() }],
};
