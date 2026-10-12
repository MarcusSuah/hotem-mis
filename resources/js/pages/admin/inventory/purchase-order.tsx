import { Head, router, useForm } from '@inertiajs/react';
import { PackageCheck, Send, XCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import { FormDialog } from '@/components/admin/form-dialog';
import { FormErrorSummary } from '@/components/admin/form-error-summary';
import { PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { fieldError, notifyValidationFailed } from '@/lib/form';
import { formatDate, formatDateTime } from '@/lib/format';
import admin from '@/routes/admin';
import type { MoneyValue } from '@/types';

type Line = {
    id: number;
    item: string;
    sku: string;
    unit: string;
    tracks_expiry: boolean;
    quantity_ordered: string;
    quantity_received: string;
    unit_cost: MoneyValue;
    line_total: MoneyValue;
};

type Props = {
    order: {
        number: string;
        supplier: {
            name: string;
            contact_person: string | null;
            email: string | null;
            phone: string | null;
        };
        status: string;
        status_label: string;
        total: MoneyValue;
        expected_on: string | null;
        ordered_at: string | null;
        received_at: string | null;
        notes: string | null;
        created_by: string | null;
        lines: Line[];
    };
    can: { order: boolean; receive: boolean; cancel: boolean };
};

export default function PurchaseOrderShow({ order, can }: Props) {
    const [confirming, setConfirming] = useState<'ordered' | 'cancel' | null>(
        null,
    );
    const [receiving, setReceiving] = useState(false);
    const [processing, setProcessing] = useState(false);

    function act() {
        const url =
            confirming === 'ordered'
                ? admin.purchaseOrders.ordered.url(order.number)
                : admin.purchaseOrders.cancel.url(order.number);
        router.post(
            url,
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    setConfirming(null);
                },
            },
        );
    }

    return (
        <>
            <Head title={`Purchase order ${order.number}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={`Purchase order ${order.number}`}
                    description={`${order.supplier.name}${order.created_by ? ` · raised by ${order.created_by}` : ''}`}
                    actions={
                        <>
                            {can.order && (
                                <Button
                                    onClick={() => setConfirming('ordered')}
                                >
                                    <Send />
                                    Mark as sent
                                </Button>
                            )}
                            {can.receive && (
                                <Button onClick={() => setReceiving(true)}>
                                    <PackageCheck />
                                    Receive goods
                                </Button>
                            )}
                            {can.cancel && (
                                <Button
                                    variant="outline"
                                    className="text-destructive"
                                    onClick={() => setConfirming('cancel')}
                                >
                                    <XCircle />
                                    Cancel
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="rounded-xl border bg-card shadow-xs lg:col-span-2">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Item</TableHead>
                                    <TableHead className="text-right">
                                        Ordered
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Received
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Unit cost
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Total
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {order.lines.map((line) => (
                                    <TableRow key={line.id}>
                                        <TableCell>
                                            {line.item}
                                            <span className="block font-mono text-xs text-muted-foreground">
                                                {line.sku}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {Number(line.quantity_ordered)}{' '}
                                            {line.unit}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {Number(line.quantity_received)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {line.unit_cost.formatted}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {line.line_total.formatted}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                            <TableFooter>
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="text-right"
                                    >
                                        Order total
                                    </TableCell>
                                    <TableCell className="text-right font-semibold">
                                        {order.total.formatted}
                                    </TableCell>
                                </TableRow>
                            </TableFooter>
                        </Table>
                    </div>
                    <aside className="space-y-3 rounded-xl border bg-card p-5 text-sm shadow-xs">
                        <Badge variant="secondary">{order.status_label}</Badge>
                        <dl className="space-y-2">
                            <div>
                                <dt className="text-muted-foreground">
                                    Supplier contact
                                </dt>
                                <dd>
                                    {[
                                        order.supplier.contact_person,
                                        order.supplier.phone,
                                        order.supplier.email,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ') || '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Expected
                                </dt>
                                <dd>
                                    {order.expected_on
                                        ? formatDate(order.expected_on)
                                        : '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Sent</dt>
                                <dd>
                                    {order.ordered_at
                                        ? formatDateTime(order.ordered_at)
                                        : '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Fully received
                                </dt>
                                <dd>
                                    {order.received_at
                                        ? formatDateTime(order.received_at)
                                        : '—'}
                                </dd>
                            </div>
                            {order.notes && (
                                <div>
                                    <dt className="text-muted-foreground">
                                        Notes
                                    </dt>
                                    <dd className="whitespace-pre-line">
                                        {order.notes}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </aside>
                </div>
            </div>

            <ConfirmDialog
                open={confirming !== null}
                onOpenChange={(open) => !open && setConfirming(null)}
                title={
                    confirming === 'ordered'
                        ? 'Mark order as sent?'
                        : 'Cancel this purchase order?'
                }
                description={
                    confirming === 'ordered'
                        ? 'The order can no longer be edited once it has been sent to the supplier.'
                        : 'Nothing has been received against this order. Cancelling cannot be undone.'
                }
                confirmLabel={
                    confirming === 'ordered' ? 'Mark as sent' : 'Cancel order'
                }
                destructive={confirming === 'cancel'}
                processing={processing}
                onConfirm={act}
            />
            <ReceiveDialog
                open={receiving}
                onClose={() => setReceiving(false)}
                order={order}
            />
        </>
    );
}

function ReceiveDialog({
    open,
    onClose,
    order,
}: {
    open: boolean;
    onClose: () => void;
    order: Props['order'];
}) {
    const outstanding = order.lines.filter(
        (line) =>
            Number(line.quantity_received) < Number(line.quantity_ordered),
    );
    const initial = Object.fromEntries(
        outstanding.map((line) => [
            String(line.id),
            {
                quantity: String(
                    Number(line.quantity_ordered) -
                        Number(line.quantity_received),
                ),
                expires_on: '',
            },
        ]),
    );
    const form = useForm<{
        received: Record<string, { quantity: string; expires_on: string }>;
    }>({ received: initial });

    useEffect(() => {
        if (open) {
            form.clearErrors();
            form.setData('received', initial);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    function update(
        lineId: number,
        key: 'quantity' | 'expires_on',
        value: string,
    ) {
        form.setData('received', {
            ...form.data.received,
            [lineId]: { ...form.data.received[lineId], [key]: value },
        });
    }

    return (
        <FormDialog
            open={open}
            onOpenChange={(next) => !next && onClose()}
            title="Receive goods"
            description="Enter what actually arrived. Leave a line blank if nothing came. Stock and average cost update immediately."
            submitLabel="Record delivery"
            size="lg"
            processing={form.processing}
            onSubmit={() =>
                form.post(admin.purchaseOrders.receive.url(order.number), {
                    preserveScroll: true,
                    onSuccess: onClose,
                    onError: notifyValidationFailed,
                })
            }
        >
            <FormErrorSummary errors={form.errors} />
            <div className="space-y-3">
                {outstanding.map((line) => (
                    <div
                        key={line.id}
                        className="grid items-start gap-2 sm:grid-cols-[1fr_7rem_10rem]"
                    >
                        <div className="text-sm">
                            {line.item}
                            <span className="block text-xs text-muted-foreground">
                                {Number(line.quantity_received)} of{' '}
                                {Number(line.quantity_ordered)} {line.unit}{' '}
                                received
                            </span>
                        </div>
                        <Input
                            aria-label={`Quantity received for ${line.item}`}
                            inputMode="decimal"
                            value={form.data.received[line.id]?.quantity ?? ''}
                            onChange={(event) =>
                                update(line.id, 'quantity', event.target.value)
                            }
                        />
                        {line.tracks_expiry ? (
                            <Input
                                type="date"
                                aria-label={`Expiry for ${line.item}`}
                                value={
                                    form.data.received[line.id]?.expires_on ??
                                    ''
                                }
                                onChange={(event) =>
                                    update(
                                        line.id,
                                        'expires_on',
                                        event.target.value,
                                    )
                                }
                            />
                        ) : (
                            <span />
                        )}
                        {[
                            fieldError(
                                form.errors,
                                `received.${line.id}.quantity`,
                            ),
                            fieldError(
                                form.errors,
                                `received.${line.id}.expires_on`,
                            ),
                        ]
                            .filter(Boolean)
                            .map((message) => (
                                <p
                                    key={message}
                                    className="text-sm text-destructive sm:col-span-3"
                                >
                                    {message}
                                </p>
                            ))}
                    </div>
                ))}
            </div>
        </FormDialog>
    );
}

PurchaseOrderShow.layout = {
    breadcrumbs: [{ title: 'Inventory', href: admin.inventory.index() }],
};
