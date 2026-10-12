import { Head, Link, usePage } from '@inertiajs/react';
import {
    BedDouble,
    CalendarClock,
    DoorClosed,
    Hotel,
    LogIn,
    LogOut,
    PackageX,
    Sparkles,
    TrendingDown,
    TrendingUp,
    Wrench,
} from 'lucide-react';
import {
    ChartCard,
    ChartSkeleton,
    DonutChart,
    ForecastChart,
    HorizontalBars,
    OccupancyChart,
} from '@/components/admin/charts';
import { PageHeader } from '@/components/admin/page-header';
import { StatCard } from '@/components/admin/stat-card';
import { formatDate, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';
import type { MoneyValue } from '@/types';

type Totals = {
    available: number;
    sold: number;
    occupancy: number;
    revenue: MoneyValue;
    adr: MoneyValue;
    revpar: MoneyValue;
    from: string;
    to: string;
};

type Finance = {
    currency: string;
    summary: { current: Totals; previous: Totals };
    daily: {
        date: string;
        occupancy: number;
        adr: MoneyValue;
        revenue: MoneyValue;
    }[];
    revenue: { category: string; label: string; amount: MoneyValue }[];
    sources: {
        source: string;
        label: string;
        bookings: number;
        value: MoneyValue;
    }[];
    payments: {
        method: string;
        label: string;
        count: number;
        amount: MoneyValue;
    }[];
    forecast: {
        date: string;
        confirmed: number;
        pending: number;
        available: number;
        occupancy: number;
    }[];
};

type Props = {
    today: {
        date: string;
        arrivals: number;
        departures: number;
        in_house: number;
        occupancy: number;
        rooms_sold: number;
        rooms_available: number;
        pending_requests: number;
        rooms_to_clean: number;
        out_of_service: number;
        open_maintenance: number;
        low_stock: number | null;
    };
    finance?: Finance | null;
};

function Change({
    current,
    previous,
    suffix = '',
}: {
    current: number;
    previous: number;
    suffix?: string;
}) {
    if (previous === 0) {
        return (
            <span className="text-xs text-muted-foreground">
                No prior period data
            </span>
        );
    }

    const change = ((current - previous) / previous) * 100;
    const up = change >= 0;
    const Icon = up ? TrendingUp : TrendingDown;

    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 text-xs',
                up
                    ? 'text-emerald-700 dark:text-emerald-400'
                    : 'text-destructive',
            )}
        >
            <Icon className="size-3.5" />
            {up ? '+' : ''}
            {formatNumber(change, 1)}% vs previous 30 days{suffix}
        </span>
    );
}

export default function AdminDashboard({ today, finance }: Props) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={`Welcome back, ${auth.user.name.split(' ')[0]}`}
                    description={`Hotel overview for ${formatDate(today.date)}.`}
                />

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Occupancy tonight"
                        value={`${formatNumber(today.occupancy, 1)}%`}
                        hint={`${today.rooms_sold} of ${today.rooms_available} rooms`}
                        icon={Hotel}
                        tone="gold"
                    />
                    <StatCard
                        label="Arrivals today"
                        value={today.arrivals}
                        icon={LogIn}
                    />
                    <StatCard
                        label="Departures today"
                        value={today.departures}
                        icon={LogOut}
                    />
                    <StatCard
                        label="Guests in house"
                        value={today.in_house}
                        icon={BedDouble}
                    />
                </div>
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    <StatCard
                        label="Pending booking requests"
                        value={today.pending_requests}
                        icon={CalendarClock}
                    />
                    <StatCard
                        label="Rooms to clean"
                        value={today.rooms_to_clean}
                        icon={Sparkles}
                    />
                    <StatCard
                        label="Rooms out of service"
                        value={today.out_of_service}
                        icon={DoorClosed}
                    />
                    <StatCard
                        label="Open maintenance"
                        value={today.open_maintenance}
                        icon={Wrench}
                    />
                    {today.low_stock !== null && (
                        <StatCard
                            label="Items at reorder level"
                            value={today.low_stock}
                            icon={PackageX}
                        />
                    )}
                </div>

                {finance === undefined && (
                    <div
                        className="grid gap-6 lg:grid-cols-2"
                        aria-busy="true"
                        aria-label="Loading performance"
                    >
                        <ChartSkeleton height={120} />
                        <ChartSkeleton height={120} />
                        <ChartSkeleton />
                        <ChartSkeleton />
                    </div>
                )}

                {finance && <FinanceSection finance={finance} />}
            </div>
        </>
    );
}

function FinanceSection({ finance }: { finance: Finance }) {
    const { current, previous } = finance.summary;
    const amount = (money: MoneyValue) => Number(money.amount);

    return (
        <>
            <div className="flex flex-wrap items-end justify-between gap-2">
                <h2 className="font-display text-2xl font-semibold">
                    Last 30 days
                </h2>
                <p className="text-xs text-muted-foreground">
                    All amounts in {finance.currency} (base currency) ·{' '}
                    <Link
                        href={admin.reports.index()}
                        className="text-primary hover:underline dark:text-highlight"
                    >
                        Full reports
                    </Link>
                </p>
            </div>
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="Occupancy"
                    value={`${formatNumber(current.occupancy, 1)}%`}
                    hint={
                        <Change
                            current={current.occupancy}
                            previous={previous.occupancy}
                        />
                    }
                />
                <StatCard
                    label="Room revenue"
                    value={current.revenue.formatted}
                    hint={
                        <Change
                            current={amount(current.revenue)}
                            previous={amount(previous.revenue)}
                        />
                    }
                />
                <StatCard
                    label="ADR"
                    value={current.adr.formatted}
                    hint={
                        <Change
                            current={amount(current.adr)}
                            previous={amount(previous.adr)}
                        />
                    }
                />
                <StatCard
                    label="RevPAR"
                    value={current.revpar.formatted}
                    hint={
                        <Change
                            current={amount(current.revpar)}
                            previous={amount(previous.revpar)}
                        />
                    }
                />
            </div>

            <div className="grid gap-6 xl:grid-cols-3">
                <ChartCard
                    title="Occupancy & ADR"
                    description="Rooms sold each night and the average rate achieved."
                    className="xl:col-span-2"
                >
                    <OccupancyChart
                        currency={finance.currency}
                        data={finance.daily.map((day) => ({
                            date: day.date,
                            occupancy: day.occupancy,
                            adr: amount(day.adr),
                        }))}
                    />
                </ChartCard>
                <ChartCard
                    title="Booking channels"
                    description="Reservations made in the period."
                >
                    {finance.sources.length === 0 ? (
                        <p className="py-16 text-center text-sm text-muted-foreground">
                            No bookings yet.
                        </p>
                    ) : (
                        <DonutChart
                            data={finance.sources.map((row) => ({
                                label: row.label,
                                value: row.bookings,
                            }))}
                        />
                    )}
                </ChartCard>
            </div>

            <div className="grid gap-6 xl:grid-cols-3">
                <ChartCard
                    title="Revenue by category"
                    description="Folio postings by service date."
                >
                    {finance.revenue.length === 0 ? (
                        <p className="py-16 text-center text-sm text-muted-foreground">
                            No postings yet.
                        </p>
                    ) : (
                        <HorizontalBars
                            currency={finance.currency}
                            data={finance.revenue.map((row) => ({
                                label: row.label,
                                value: amount(row.amount),
                            }))}
                        />
                    )}
                </ChartCard>
                <ChartCard
                    title="Next 30 days on the books"
                    description="Rooms already reserved; pending requests are not yet confirmed."
                    className="xl:col-span-2"
                >
                    <ForecastChart data={finance.forecast} />
                </ChartCard>
            </div>

            <ChartCard
                title="Payments received"
                description="Completed payments less refunds."
            >
                {finance.payments.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        No payments in this period.
                    </p>
                ) : (
                    <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        {finance.payments.map((row) => (
                            <li
                                key={row.method}
                                className="rounded-lg bg-muted/50 p-4"
                            >
                                <p className="text-sm text-muted-foreground">
                                    {row.label}
                                </p>
                                <p className="text-xl font-semibold">
                                    {row.amount.formatted}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {row.count} payments
                                </p>
                            </li>
                        ))}
                    </ul>
                )}
            </ChartCard>
        </>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: admin.dashboard() }],
};
