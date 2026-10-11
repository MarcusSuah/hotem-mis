import { Head, Link, router } from '@inertiajs/react';
import {
    BedDouble,
    LogIn,
    LogOut,
    MoonStar,
    Search,
    Star,
    TriangleAlert,
} from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { PageHeader } from '@/components/admin/page-header';
import { StatCard } from '@/components/admin/stat-card';
import {
    HousekeepingBadge,
    ReservationStatusBadge,
} from '@/components/admin/status-badges';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useCan } from '@/hooks/use-can';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';
import type { MoneyValue } from '@/types';

type Row = {
    reference: string;
    status: string;
    status_label: string;
    guest: string;
    is_vip: boolean;
    phone: string | null;
    check_in_date: string;
    check_out_date: string;
    nights: number;
    party: number;
    room: string | null;
    room_type: string | null;
    room_ready: boolean | null;
    housekeeping_status: string | null;
    balance: MoneyValue;
    deposit_outstanding: MoneyValue;
    special_requests: string | null;
    departing_today?: boolean;
};

type Props = {
    today: string;
    businessDate: string;
    search: string;
    arrivals: Row[];
    inHouse: Row[];
    can: { checkIn: boolean; checkOut: boolean };
};

export default function FrontDesk({
    today,
    businessDate,
    search: initialSearch,
    arrivals,
    inHouse,
    can,
}: Props) {
    const [search, setSearch] = useState(initialSearch);
    const { can: hasPermission } = useCan();
    const departures = inHouse.filter((row) => row.departing_today);

    function submitSearch(event: React.FormEvent) {
        event.preventDefault();
        router.get(admin.frontDesk.index.url(), search ? { search } : {}, {
            preserveState: true,
            replace: true,
        });
    }

    return (
        <>
            <Head title="Front desk" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Front desk"
                    description={`Today is ${formatDate(today)}. Business date ${formatDate(businessDate)}.`}
                    actions={
                        hasPermission('night-audit.run') && (
                            <Button variant="outline" asChild>
                                <Link href={admin.nightAudit.index()}>
                                    <MoonStar />
                                    Night audit
                                </Link>
                            </Button>
                        )
                    }
                />

                {businessDate < today && (
                    <div className="flex items-center gap-2 rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm text-warning">
                        <TriangleAlert className="size-4" />
                        The night audit for {formatDate(businessDate)} has not
                        been run yet.
                    </div>
                )}

                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard
                        label="Arrivals pending"
                        value={arrivals.length}
                        icon={LogIn}
                    />
                    <StatCard
                        label="In house"
                        value={inHouse.length}
                        icon={BedDouble}
                    />
                    <StatCard
                        label="Departures due"
                        value={departures.length}
                        icon={LogOut}
                        tone="gold"
                    />
                </div>

                <form onSubmit={submitSearch} className="relative max-w-xl">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        aria-label="Find a reservation"
                        placeholder="Booking reference, guest name, email or phone — press Enter"
                        className="pl-9"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                    />
                </form>

                <Tabs defaultValue="arrivals" className="gap-4">
                    <TabsList>
                        <TabsTrigger value="arrivals">
                            Arrivals ({arrivals.length})
                        </TabsTrigger>
                        <TabsTrigger value="in-house">
                            In house ({inHouse.length})
                        </TabsTrigger>
                        <TabsTrigger value="departures">
                            Departures ({departures.length})
                        </TabsTrigger>
                    </TabsList>
                    <TabsContent value="arrivals">
                        <RowList
                            rows={arrivals}
                            empty="No arrivals waiting."
                            action={can.checkIn ? 'check-in' : null}
                        />
                    </TabsContent>
                    <TabsContent value="in-house">
                        <RowList
                            rows={inHouse}
                            empty="No guests in house."
                            action={can.checkOut ? 'check-out' : null}
                        />
                    </TabsContent>
                    <TabsContent value="departures">
                        <RowList
                            rows={departures}
                            empty="No departures due today."
                            action={can.checkOut ? 'check-out' : null}
                        />
                    </TabsContent>
                </Tabs>
            </div>
        </>
    );
}

function RowList({
    rows,
    empty,
    action,
}: {
    rows: Row[];
    empty: string;
    action: 'check-in' | 'check-out' | null;
}) {
    if (rows.length === 0) {
        return (
            <div className="rounded-xl border bg-card">
                <EmptyState icon={BedDouble} title={empty} />
            </div>
        );
    }

    return (
        <div className="grid gap-3 lg:grid-cols-2">
            {rows.map((row) => (
                <article
                    key={row.reference}
                    className={cn(
                        'flex flex-col gap-3 rounded-xl border bg-card p-4 shadow-xs',
                        row.departing_today && 'border-l-4 border-l-highlight',
                    )}
                >
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <p className="flex items-center gap-1.5 text-lg font-semibold">
                                {row.guest}
                                {row.is_vip && (
                                    <Star
                                        className="size-4 fill-highlight text-highlight"
                                        aria-label="VIP"
                                    />
                                )}
                            </p>
                            <Link
                                href={admin.reservations.show(row.reference)}
                                className="font-mono text-xs text-muted-foreground hover:underline"
                            >
                                {row.reference}
                            </Link>
                        </div>
                        <div className="text-right">
                            <p className="font-display text-2xl font-semibold">
                                {row.room ?? '—'}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {row.room_type}
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-1.5 text-sm">
                        <ReservationStatusBadge
                            status={row.status}
                            label={row.status_label}
                        />
                        {row.housekeeping_status && (
                            <HousekeepingBadge
                                status={row.housekeeping_status}
                            />
                        )}
                        {row.deposit_outstanding.minor > 0 &&
                            row.status !== 'checked_in' && (
                                <Badge variant="warning">
                                    Deposit due{' '}
                                    {row.deposit_outstanding.formatted}
                                </Badge>
                            )}
                        {row.status === 'checked_in' &&
                            row.balance.minor > 0 && (
                                <Badge variant="gold">
                                    Balance {row.balance.formatted}
                                </Badge>
                            )}
                    </div>
                    <p className="text-sm text-muted-foreground">
                        {formatDate(row.check_in_date)} →{' '}
                        {formatDate(row.check_out_date)} · {row.nights} night
                        {row.nights === 1 ? '' : 's'} · {row.party} guest
                        {row.party === 1 ? '' : 's'}
                    </p>
                    {row.special_requests && (
                        <p className="rounded-md bg-muted/60 p-2 text-xs">
                            “{row.special_requests}”
                        </p>
                    )}
                    {action && (
                        <div className="flex justify-end">
                            {action === 'check-in' ? (
                                <Button asChild size="sm">
                                    <Link
                                        href={admin.frontDesk.checkIn(
                                            row.reference,
                                        )}
                                    >
                                        <LogIn />
                                        Check in
                                    </Link>
                                </Button>
                            ) : (
                                <Button
                                    asChild
                                    size="sm"
                                    variant={
                                        row.departing_today
                                            ? 'default'
                                            : 'outline'
                                    }
                                >
                                    <Link
                                        href={admin.frontDesk.checkOut(
                                            row.reference,
                                        )}
                                    >
                                        <LogOut />
                                        Check out
                                    </Link>
                                </Button>
                            )}
                        </div>
                    )}
                </article>
            ))}
        </div>
    );
}

FrontDesk.layout = {
    breadcrumbs: [{ title: 'Front desk', href: admin.frontDesk.index() }],
};
