import { Head, Link, router } from '@inertiajs/react';
import { RotateCw, Webhook } from 'lucide-react';
import { useState } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { OptionSelect } from '@/components/admin/option-select';
import type { Option } from '@/components/admin/option-select';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
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
import { useFilters } from '@/hooks/use-filters';
import { formatDateTime } from '@/lib/format';
import admin from '@/routes/admin';
import type { Paginated } from '@/types';

type GatewayEvent = {
    id: number;
    gateway: string;
    event_id: string;
    type: string;
    reservation_reference: string | null;
    transaction_reference: string | null;
    attempts: number;
    error: string | null;
    status: string;
    status_label: string;
    payment: string | null;
    amount: string | null;
    received_at: string | null;
    processed_at: string | null;
};

type Props = {
    events: Paginated<GatewayEvent>;
    filters: { status: string };
    statuses: Option[];
    configured: string[];
    can: { retry: boolean };
};

const statusVariant: Record<
    string,
    'success' | 'muted' | 'danger' | 'secondary'
> = {
    processed: 'success',
    ignored: 'muted',
    failed: 'danger',
    received: 'secondary',
};

export default function GatewayEvents(props: Props) {
    const { events, statuses, configured, can } = props;
    const { filters, setFilter } = useFilters<{ status: string }>(
        admin.gatewayEvents.index.url(),
        props.filters,
    );
    const [retrying, setRetrying] = useState<number | null>(null);

    return (
        <>
            <Head title="Online payments" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Online payments"
                    description={
                        configured.length > 0
                            ? `Notifications received from: ${configured.join(', ')}. Each verified payment is recorded on the guest folio once.`
                            : 'No payment provider is connected yet. See docs/PAYMENT-WEBHOOKS.md to enable one.'
                    }
                />
                <div className="rounded-xl border bg-card shadow-xs">
                    <div className="flex justify-end border-b p-4">
                        <OptionSelect
                            ariaLabel="Status"
                            className="w-48"
                            value={filters.status}
                            placeholder="All statuses"
                            options={statuses}
                            onChange={(value) =>
                                setFilter('status', value, true)
                            }
                        />
                    </div>
                    {events.data.length === 0 ? (
                        <EmptyState
                            icon={Webhook}
                            title="No notifications"
                            description="Payment provider notifications appear here as they arrive."
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Received</TableHead>
                                    <TableHead>Event</TableHead>
                                    <TableHead>Booking</TableHead>
                                    <TableHead className="text-right">
                                        Amount
                                    </TableHead>
                                    <TableHead>Outcome</TableHead>
                                    <TableHead />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {events.data.map((event) => (
                                    <TableRow key={event.id}>
                                        <TableCell className="whitespace-nowrap">
                                            {formatDateTime(event.received_at)}
                                        </TableCell>
                                        <TableCell>
                                            {event.type}
                                            <span className="block font-mono text-xs text-muted-foreground">
                                                {event.gateway} ·{' '}
                                                {event.event_id}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            {event.reservation_reference ? (
                                                <Link
                                                    href={admin.reservations.show(
                                                        event.reservation_reference,
                                                    )}
                                                    className="font-mono hover:underline"
                                                >
                                                    {
                                                        event.reservation_reference
                                                    }
                                                </Link>
                                            ) : (
                                                '—'
                                            )}
                                            {event.transaction_reference && (
                                                <span className="block text-xs text-muted-foreground">
                                                    Txn{' '}
                                                    {
                                                        event.transaction_reference
                                                    }
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right whitespace-nowrap">
                                            {event.amount || '—'}
                                        </TableCell>
                                        <TableCell className="max-w-xs whitespace-normal">
                                            <Badge
                                                variant={
                                                    statusVariant[
                                                        event.status
                                                    ] ?? 'secondary'
                                                }
                                            >
                                                {event.status_label}
                                            </Badge>
                                            {event.payment && (
                                                <span className="block font-mono text-xs text-muted-foreground">
                                                    {event.payment}
                                                </span>
                                            )}
                                            {event.error && (
                                                <span className="block text-xs text-destructive">
                                                    {event.error}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {can.retry &&
                                                event.status === 'failed' && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        disabled={
                                                            retrying ===
                                                            event.id
                                                        }
                                                        onClick={() =>
                                                            router.post(
                                                                admin.gatewayEvents.retry.url(
                                                                    event.id,
                                                                ),
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                    onStart:
                                                                        () =>
                                                                            setRetrying(
                                                                                event.id,
                                                                            ),
                                                                    onFinish:
                                                                        () =>
                                                                            setRetrying(
                                                                                null,
                                                                            ),
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <RotateCw />
                                                        Retry
                                                    </Button>
                                                )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                    <div className="border-t p-4">
                        <Pagination paginator={events} />
                    </div>
                </div>
            </div>
        </>
    );
}

GatewayEvents.layout = {
    breadcrumbs: [
        { title: 'Online payments', href: admin.gatewayEvents.index() },
    ],
};
