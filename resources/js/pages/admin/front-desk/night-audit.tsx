import { Head, useForm } from '@inertiajs/react';
import { MoonStar, TriangleAlert } from 'lucide-react';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import { PageHeader } from '@/components/admin/page-header';
import { StatCard } from '@/components/admin/stat-card';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate, formatDateTime } from '@/lib/format';
import admin from '@/routes/admin';
import { useState } from 'react';

type Summary = {
    rooms_occupied: number;
    rooms_sellable: number;
    occupancy_percent: number;
    arrivals: number;
    departures: number;
    posted_lines: number;
    no_shows: string[];
    room_revenue_by_currency: Record<string, number>;
    payments: {
        currency: string;
        method: string;
        total_minor: number;
        count: number;
    }[];
};

type Props = {
    preview: {
        business_date: string;
        in_house: number;
        pending_arrivals: {
            reference: string;
            guest: string;
            check_in_date: string;
        }[];
        overdue_departures: string[];
        can_run: boolean;
    };
    history: {
        id: number;
        business_date: string;
        run_by: string | null;
        completed_at: string;
        summary: Summary;
        room_revenue: string[];
        payments: string[];
    }[];
};

export default function NightAudit({ preview, history }: Props) {
    const [confirming, setConfirming] = useState(false);
    const form = useForm({ mark_no_shows: false });

    return (
        <>
            <Head title="Night audit" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Night audit"
                    description="Closes the business day: posts tonight's room, service and tax charges to every in-house folio and freezes the day's figures."
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard
                        label="Business date"
                        value={formatDate(preview.business_date)}
                        icon={MoonStar}
                        tone="gold"
                    />
                    <StatCard
                        label="In-house folios to post"
                        value={preview.in_house}
                    />
                    <StatCard
                        label="Arrivals not checked in"
                        value={preview.pending_arrivals.length}
                    />
                </div>

                <section className="rounded-xl border bg-card p-5 shadow-xs">
                    {preview.pending_arrivals.length > 0 && (
                        <div className="mb-4 space-y-2">
                            <p className="flex items-center gap-2 text-sm font-medium text-warning">
                                <TriangleAlert className="size-4" /> These
                                guests have not arrived:
                            </p>
                            <ul className="ml-6 list-disc text-sm text-muted-foreground">
                                {preview.pending_arrivals.map((arrival) => (
                                    <li key={arrival.reference}>
                                        {arrival.guest} ({arrival.reference},
                                        due {formatDate(arrival.check_in_date)})
                                    </li>
                                ))}
                            </ul>
                            <label
                                htmlFor="no-shows"
                                className="flex items-center gap-2 text-sm"
                            >
                                <Checkbox
                                    id="no-shows"
                                    checked={form.data.mark_no_shows}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'mark_no_shows',
                                            checked === true,
                                        )
                                    }
                                />
                                Mark them as no-shows and release their rooms
                            </label>
                        </div>
                    )}
                    {preview.overdue_departures.length > 0 && (
                        <p className="mb-4 text-sm text-warning">
                            Due to depart but still in house:{' '}
                            {preview.overdue_departures.join(', ')}
                        </p>
                    )}
                    <Button
                        disabled={!preview.can_run || form.processing}
                        onClick={() => setConfirming(true)}
                    >
                        <MoonStar />
                        Close {formatDate(preview.business_date)}
                    </Button>
                    {!preview.can_run && (
                        <p className="mt-2 text-sm text-muted-foreground">
                            This business day has not ended yet.
                        </p>
                    )}
                </section>

                <section className="rounded-xl border bg-card shadow-xs">
                    <h2 className="border-b p-4 font-display text-xl font-semibold">
                        Closed days
                    </h2>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Business date</TableHead>
                                <TableHead>Occupancy</TableHead>
                                <TableHead>Room revenue</TableHead>
                                <TableHead>Payments</TableHead>
                                <TableHead className="hidden md:table-cell">
                                    Closed by
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {history.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={5}
                                        className="text-center text-muted-foreground"
                                    >
                                        No audits yet.
                                    </TableCell>
                                </TableRow>
                            )}
                            {history.map((audit) => (
                                <TableRow key={audit.id}>
                                    <TableCell className="font-medium">
                                        {formatDate(audit.business_date)}
                                    </TableCell>
                                    <TableCell>
                                        {audit.summary.rooms_occupied}/
                                        {audit.summary.rooms_sellable} (
                                        {audit.summary.occupancy_percent}%)
                                    </TableCell>
                                    <TableCell className="whitespace-normal">
                                        {audit.room_revenue.join(' · ') || '—'}
                                    </TableCell>
                                    <TableCell className="whitespace-normal">
                                        {audit.payments.join(' · ') || '—'}
                                    </TableCell>
                                    <TableCell className="hidden text-muted-foreground md:table-cell">
                                        {audit.run_by ?? 'System'} ·{' '}
                                        {formatDateTime(audit.completed_at)}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </section>
            </div>

            <ConfirmDialog
                open={confirming}
                onOpenChange={setConfirming}
                title={`Close ${formatDate(preview.business_date)}?`}
                description="Room charges are posted to every in-house folio. This cannot be undone; individual charges can still be reversed on the folio."
                confirmLabel="Run night audit"
                processing={form.processing}
                onConfirm={() =>
                    form.post(admin.nightAudit.store.url(), {
                        preserveScroll: true,
                        onFinish: () => setConfirming(false),
                    })
                }
            />
        </>
    );
}

NightAudit.layout = {
    breadcrumbs: [{ title: 'Night audit', href: admin.nightAudit.index() }],
};
