import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    BedDouble,
    ChefHat,
    CheckCircle2,
    ConciergeBell,
    Minus,
    Plus,
    Receipt,
    Utensils,
    XCircle,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import { EmptyState } from '@/components/admin/empty-state';
import { Field } from '@/components/admin/field';
import { FormDialog } from '@/components/admin/form-dialog';
import { FormErrorSummary } from '@/components/admin/form-error-summary';
import { OptionSelect } from '@/components/admin/option-select';
import type { Option } from '@/components/admin/option-select';
import { PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { notifyValidationFailed } from '@/lib/form';
import { formatDateTime } from '@/lib/format';
import admin from '@/routes/admin';
import type { MoneyValue } from '@/types';

type LinkedReservation = { id: number; reference: string } | null;

type Order = {
    reference: string;
    type: string;
    type_label: string;
    status: string;
    status_label: string;
    table_or_room: string | null;
    customer: string | null;
    reservation: LinkedReservation;
    settlement: string | null;
    items: { name: string; quantity: number; amount: MoneyValue }[];
    subtotal: MoneyValue;
    tax: MoneyValue;
    total: MoneyValue;
    created_at: string | null;
};

type ServiceRow = {
    reference: string;
    service: string;
    customer: string | null;
    reservation: LinkedReservation;
    quantity: number;
    total: MoneyValue;
    status: string;
    status_label: string;
    scheduled_for: string | null;
    settlement: string | null;
};

type Method = { value: string; label: string; requires_reference: boolean };

type Props = {
    orders: Order[];
    serviceOrders: ServiceRow[];
    menu: {
        id: number;
        name: string;
        items: { id: number; name: string; price: MoneyValue }[];
    }[];
    services: { id: number; name: string; unit: string; price: MoneyValue }[];
    inHouse: { id: number; label: string; reference: string }[];
    options: { types: Option[]; methods: Method[] };
};

type SettleTarget = {
    kind: 'order' | 'service';
    reference: string;
    total: MoneyValue;
    linked: boolean;
};

const statusVariant: Record<
    string,
    'secondary' | 'warning' | 'gold' | 'success' | 'muted' | 'danger'
> = {
    open: 'secondary',
    preparing: 'warning',
    served: 'gold',
    settled: 'success',
    requested: 'secondary',
    scheduled: 'warning',
    completed: 'success',
    cancelled: 'muted',
};

const nextStep: Record<string, { status: string; label: string }> = {
    open: { status: 'preparing', label: 'Send to kitchen' },
    preparing: { status: 'served', label: 'Mark served' },
};

export default function OutletsIndex(props: Props) {
    const { orders, serviceOrders, options } = props;
    const [ordering, setOrdering] = useState(false);
    const [booking, setBooking] = useState(false);
    const [settling, setSettling] = useState<SettleTarget | null>(null);
    const [cancellingOrder, setCancellingOrder] = useState<Order | null>(null);
    const [cancellingService, setCancellingService] =
        useState<ServiceRow | null>(null);
    const [processing, setProcessing] = useState(false);
    const activeOrders = orders.filter((order) =>
        ['open', 'preparing', 'served'].includes(order.status),
    );
    const recentOrders = orders.filter(
        (order) => !['open', 'preparing', 'served'].includes(order.status),
    );

    return (
        <>
            <Head title="Restaurant & services" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Restaurant & services"
                    description="Take orders, track the kitchen and settle to the room folio or at the outlet."
                    actions={
                        <>
                            <Button
                                variant="outline"
                                onClick={() => setBooking(true)}
                                disabled={props.services.length === 0}
                            >
                                <ConciergeBell />
                                Book a service
                            </Button>
                            <Button
                                onClick={() => setOrdering(true)}
                                disabled={props.menu.length === 0}
                            >
                                <Plus />
                                New order
                            </Button>
                        </>
                    }
                />

                <Tabs defaultValue="orders" className="gap-4">
                    <TabsList>
                        <TabsTrigger value="orders">
                            Orders ({activeOrders.length})
                        </TabsTrigger>
                        <TabsTrigger value="services">
                            Services (
                            {
                                serviceOrders.filter((row) =>
                                    ['requested', 'scheduled'].includes(
                                        row.status,
                                    ),
                                ).length
                            }
                            )
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="orders" className="space-y-6">
                        {activeOrders.length === 0 ? (
                            <div className="rounded-xl border bg-card shadow-xs">
                                <EmptyState
                                    icon={Utensils}
                                    title="No open orders"
                                    description="New restaurant, bar and room service orders appear here."
                                />
                            </div>
                        ) : (
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                {activeOrders.map((order) => (
                                    <OrderCard
                                        key={order.reference}
                                        order={order}
                                        onAdvance={(status) =>
                                            router.post(
                                                admin.outlets.orders.advance.url(
                                                    order.reference,
                                                ),
                                                { status },
                                                { preserveScroll: true },
                                            )
                                        }
                                        onSettle={() =>
                                            setSettling({
                                                kind: 'order',
                                                reference: order.reference,
                                                total: order.total,
                                                linked:
                                                    order.reservation !== null,
                                            })
                                        }
                                        onCancel={() =>
                                            setCancellingOrder(order)
                                        }
                                    />
                                ))}
                            </div>
                        )}
                        {recentOrders.length > 0 && (
                            <section className="space-y-2">
                                <h2 className="font-display text-lg font-semibold">
                                    Last 24 hours
                                </h2>
                                <ul className="divide-y rounded-xl border bg-card text-sm shadow-xs">
                                    {recentOrders.map((order) => (
                                        <li
                                            key={order.reference}
                                            className="flex flex-wrap items-center justify-between gap-2 px-4 py-2"
                                        >
                                            <span>
                                                <span className="font-mono">
                                                    {order.reference}
                                                </span>{' '}
                                                · {order.type_label} ·{' '}
                                                {order.customer ??
                                                    order.table_or_room ??
                                                    'Walk-in'}
                                            </span>
                                            <span className="flex items-center gap-2">
                                                {order.settlement && (
                                                    <span className="text-muted-foreground">
                                                        {order.settlement}
                                                    </span>
                                                )}
                                                <Badge
                                                    variant={
                                                        statusVariant[
                                                            order.status
                                                        ]
                                                    }
                                                >
                                                    {order.status_label}
                                                </Badge>
                                                {order.total.formatted}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        )}
                    </TabsContent>

                    <TabsContent value="services">
                        <div className="rounded-xl border bg-card shadow-xs">
                            {serviceOrders.length === 0 ? (
                                <EmptyState
                                    icon={ConciergeBell}
                                    title="No service bookings"
                                />
                            ) : (
                                <ul className="divide-y text-sm">
                                    {serviceOrders.map((row) => (
                                        <li
                                            key={row.reference}
                                            className="flex flex-wrap items-center justify-between gap-3 p-4"
                                        >
                                            <div>
                                                <p className="font-medium">
                                                    {row.quantity} ×{' '}
                                                    {row.service}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    <span className="font-mono">
                                                        {row.reference}
                                                    </span>{' '}
                                                    · {row.customer ?? 'Guest'}
                                                    {row.reservation && (
                                                        <>
                                                            {' · '}
                                                            <Link
                                                                href={admin.reservations.show(
                                                                    row
                                                                        .reservation
                                                                        .reference,
                                                                )}
                                                                className="hover:underline"
                                                            >
                                                                {
                                                                    row
                                                                        .reservation
                                                                        .reference
                                                                }
                                                            </Link>
                                                        </>
                                                    )}
                                                    {row.scheduled_for &&
                                                        ` · ${formatDateTime(row.scheduled_for)}`}
                                                </p>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Badge
                                                    variant={
                                                        statusVariant[
                                                            row.status
                                                        ]
                                                    }
                                                >
                                                    {row.status_label}
                                                </Badge>
                                                <span className="font-medium">
                                                    {row.total.formatted}
                                                </span>
                                                {[
                                                    'requested',
                                                    'scheduled',
                                                ].includes(row.status) && (
                                                    <>
                                                        <Button
                                                            size="sm"
                                                            onClick={() =>
                                                                setSettling({
                                                                    kind: 'service',
                                                                    reference:
                                                                        row.reference,
                                                                    total: row.total,
                                                                    linked:
                                                                        row.reservation !==
                                                                        null,
                                                                })
                                                            }
                                                        >
                                                            <CheckCircle2 />
                                                            Complete
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className="text-destructive"
                                                            onClick={() =>
                                                                setCancellingService(
                                                                    row,
                                                                )
                                                            }
                                                        >
                                                            Cancel
                                                        </Button>
                                                    </>
                                                )}
                                            </div>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </TabsContent>
                </Tabs>
            </div>

            <OrderDialog
                open={ordering}
                onClose={() => setOrdering(false)}
                {...props}
            />
            <ServiceDialog
                open={booking}
                onClose={() => setBooking(false)}
                {...props}
            />
            <SettleDialog
                target={settling}
                onClose={() => setSettling(null)}
                methods={options.methods}
            />
            <CancelOrderDialog
                order={cancellingOrder}
                onClose={() => setCancellingOrder(null)}
            />
            <ConfirmDialog
                open={cancellingService !== null}
                onOpenChange={(open) => !open && setCancellingService(null)}
                title="Cancel this service booking?"
                description="Nothing has been charged for it yet."
                confirmLabel="Cancel booking"
                destructive
                processing={processing}
                onConfirm={() =>
                    cancellingService &&
                    router.post(
                        admin.outlets.services.cancel.url(
                            cancellingService.reference,
                        ),
                        {},
                        {
                            preserveScroll: true,
                            onStart: () => setProcessing(true),
                            onFinish: () => {
                                setProcessing(false);
                                setCancellingService(null);
                            },
                        },
                    )
                }
            />
        </>
    );
}

function OrderCard({
    order,
    onAdvance,
    onSettle,
    onCancel,
}: {
    order: Order;
    onAdvance: (status: string) => void;
    onSettle: () => void;
    onCancel: () => void;
}) {
    const step = nextStep[order.status];

    return (
        <article className="flex flex-col rounded-xl border bg-card shadow-xs">
            <header className="flex items-start justify-between gap-2 border-b p-4">
                <div>
                    <p className="font-medium">
                        {order.customer ?? order.table_or_room ?? 'Walk-in'}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        <span className="font-mono">{order.reference}</span> ·{' '}
                        {order.type_label}
                        {order.table_or_room &&
                            order.customer &&
                            ` · ${order.table_or_room}`}{' '}
                        · {formatDateTime(order.created_at)}
                    </p>
                    {order.reservation && (
                        <Link
                            href={admin.reservations.show(
                                order.reservation.reference,
                            )}
                            className="mt-1 inline-flex items-center gap-1 text-xs text-primary hover:underline"
                        >
                            <BedDouble className="size-3" />
                            {order.reservation.reference}
                        </Link>
                    )}
                </div>
                <Badge variant={statusVariant[order.status]}>
                    {order.status_label}
                </Badge>
            </header>
            <ul className="flex-1 space-y-1 p-4 text-sm">
                {order.items.map((item, index) => (
                    <li key={index} className="flex justify-between gap-2">
                        <span>
                            {item.quantity} × {item.name}
                        </span>
                        <span>{item.amount.formatted}</span>
                    </li>
                ))}
                <li className="flex justify-between border-t pt-1 text-muted-foreground">
                    <span>Tax</span>
                    <span>{order.tax.formatted}</span>
                </li>
                <li className="flex justify-between font-semibold">
                    <span>Total</span>
                    <span>{order.total.formatted}</span>
                </li>
            </ul>
            <footer className="flex flex-wrap gap-2 border-t p-3">
                {step && (
                    <Button
                        size="sm"
                        variant="outline"
                        onClick={() => onAdvance(step.status)}
                    >
                        <ChefHat />
                        {step.label}
                    </Button>
                )}
                <Button size="sm" onClick={onSettle}>
                    <Receipt />
                    Settle
                </Button>
                <Button
                    size="sm"
                    variant="ghost"
                    className="ml-auto text-destructive"
                    onClick={onCancel}
                >
                    <XCircle />
                    Cancel
                </Button>
            </footer>
        </article>
    );
}

function newKey(): string {
    return crypto.randomUUID();
}

function OrderDialog({
    open,
    onClose,
    menu,
    inHouse,
    options,
}: Props & { open: boolean; onClose: () => void }) {
    const form = useForm({
        type: 'restaurant',
        reservation_id: '',
        table_or_room: '',
        customer_name: '',
        notes: '',
        lines: [] as { menu_item_id: number; quantity: number }[],
        idempotency_key: '',
    });
    const { data, setData, errors } = form;
    const prices = useMemo(
        () =>
            new Map(
                menu.flatMap((category) =>
                    category.items.map((item) => [item.id, item] as const),
                ),
            ),
        [menu],
    );
    const subtotal = data.lines.reduce(
        (sum, line) =>
            sum +
            (prices.get(line.menu_item_id)?.price.minor ?? 0) * line.quantity,
        0,
    );
    const currency = menu[0]?.items[0]?.price.currency;

    useEffect(() => {
        if (open) {
            form.reset();
            form.clearErrors();
            form.setData('idempotency_key', newKey());
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    function changeQuantity(itemId: number, delta: number) {
        const existing = data.lines.find(
            (line) => line.menu_item_id === itemId,
        );
        const quantity = Math.min(99, (existing?.quantity ?? 0) + delta);

        setData(
            'lines',
            quantity <= 0
                ? data.lines.filter((line) => line.menu_item_id !== itemId)
                : existing
                  ? data.lines.map((line) =>
                        line.menu_item_id === itemId
                            ? { ...line, quantity }
                            : line,
                    )
                  : [...data.lines, { menu_item_id: itemId, quantity }],
        );
    }

    return (
        <FormDialog
            open={open}
            onOpenChange={(next) => !next && onClose()}
            title="New order"
            description="Tax is added at the hotel rate. Prices are taken from the menu when the order is saved."
            submitLabel="Place order"
            size="xl"
            processing={form.processing}
            onSubmit={() =>
                form.post(admin.outlets.orders.store.url(), {
                    preserveScroll: true,
                    onSuccess: onClose,
                    onError: notifyValidationFailed,
                })
            }
        >
            <FormErrorSummary errors={errors} />
            <div className="grid gap-4 sm:grid-cols-3">
                <Field
                    label="Outlet"
                    htmlFor="o-type"
                    error={errors.type}
                    required
                >
                    <OptionSelect
                        id="o-type"
                        value={data.type}
                        options={options.types}
                        onChange={(value) => setData('type', value)}
                    />
                </Field>
                <Field
                    label="In-house guest"
                    htmlFor="o-guest"
                    error={errors.reservation_id}
                >
                    <OptionSelect
                        id="o-guest"
                        value={data.reservation_id}
                        placeholder="Not a resident"
                        options={inHouse.map((stay) => ({
                            value: String(stay.id),
                            label: stay.label,
                        }))}
                        onChange={(value) => setData('reservation_id', value)}
                    />
                </Field>
                <Field
                    label="Table / room"
                    htmlFor="o-table"
                    error={errors.table_or_room}
                >
                    <Input
                        id="o-table"
                        value={data.table_or_room}
                        onChange={(event) =>
                            setData('table_or_room', event.target.value)
                        }
                        placeholder="T4, Bar, 204"
                    />
                </Field>
                {!data.reservation_id && (
                    <Field
                        label="Customer"
                        htmlFor="o-customer"
                        error={errors.customer_name}
                    >
                        <Input
                            id="o-customer"
                            value={data.customer_name}
                            onChange={(event) =>
                                setData('customer_name', event.target.value)
                            }
                        />
                    </Field>
                )}
            </div>
            <div className="max-h-[40vh] space-y-4 overflow-y-auto rounded-lg border p-3">
                {menu.map((category) => (
                    <section key={category.id}>
                        <h3 className="mb-1 text-sm font-semibold text-muted-foreground">
                            {category.name}
                        </h3>
                        <ul className="divide-y">
                            {category.items.map((item) => {
                                const quantity =
                                    data.lines.find(
                                        (line) => line.menu_item_id === item.id,
                                    )?.quantity ?? 0;

                                return (
                                    <li
                                        key={item.id}
                                        className="flex items-center justify-between gap-2 py-1.5 text-sm"
                                    >
                                        <span>
                                            {item.name}{' '}
                                            <span className="text-muted-foreground">
                                                {item.price.formatted}
                                            </span>
                                        </span>
                                        <span className="flex items-center gap-1">
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                className="size-7"
                                                aria-label={`Remove one ${item.name}`}
                                                disabled={quantity === 0}
                                                onClick={() =>
                                                    changeQuantity(item.id, -1)
                                                }
                                            >
                                                <Minus />
                                            </Button>
                                            <span className="w-6 text-center tabular-nums">
                                                {quantity}
                                            </span>
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="outline"
                                                className="size-7"
                                                aria-label={`Add one ${item.name}`}
                                                onClick={() =>
                                                    changeQuantity(item.id, 1)
                                                }
                                            >
                                                <Plus />
                                            </Button>
                                        </span>
                                    </li>
                                );
                            })}
                        </ul>
                    </section>
                ))}
            </div>
            <p className="text-right text-sm">
                Subtotal before tax:{' '}
                <span className="font-semibold">
                    {currency
                        ? new Intl.NumberFormat(undefined, {
                              style: 'currency',
                              currency,
                          }).format(subtotal / 100)
                        : '—'}
                </span>
            </p>
            <Field label="Kitchen notes" htmlFor="o-notes" error={errors.notes}>
                <Textarea
                    id="o-notes"
                    rows={2}
                    value={data.notes}
                    onChange={(event) => setData('notes', event.target.value)}
                />
            </Field>
        </FormDialog>
    );
}

function ServiceDialog({
    open,
    onClose,
    services,
    inHouse,
}: Props & { open: boolean; onClose: () => void }) {
    const form = useForm({
        additional_service_id: '',
        reservation_id: '',
        customer_name: '',
        quantity: '1',
        scheduled_for: '',
        notes: '',
        idempotency_key: '',
    });
    const { data, setData, errors } = form;

    useEffect(() => {
        if (open) {
            form.reset();
            form.clearErrors();
            form.setData('idempotency_key', newKey());
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    return (
        <FormDialog
            open={open}
            onOpenChange={(next) => !next && onClose()}
            title="Book a service"
            submitLabel="Book"
            processing={form.processing}
            onSubmit={() =>
                form.post(admin.outlets.services.store.url(), {
                    preserveScroll: true,
                    onSuccess: onClose,
                    onError: notifyValidationFailed,
                })
            }
        >
            <Field
                label="Service"
                htmlFor="sv-service"
                error={errors.additional_service_id}
                required
            >
                <OptionSelect
                    id="sv-service"
                    value={data.additional_service_id}
                    options={services.map((service) => ({
                        value: String(service.id),
                        label: `${service.name} — ${service.price.formatted} / ${service.unit}`,
                    }))}
                    onChange={(value) =>
                        setData('additional_service_id', value)
                    }
                />
            </Field>
            <Field
                label="In-house guest"
                htmlFor="sv-guest"
                error={errors.reservation_id}
            >
                <OptionSelect
                    id="sv-guest"
                    value={data.reservation_id}
                    placeholder="Not a resident"
                    options={inHouse.map((stay) => ({
                        value: String(stay.id),
                        label: stay.label,
                    }))}
                    onChange={(value) => setData('reservation_id', value)}
                />
            </Field>
            {!data.reservation_id && (
                <Field
                    label="Customer"
                    htmlFor="sv-customer"
                    error={errors.customer_name}
                    required
                >
                    <Input
                        id="sv-customer"
                        value={data.customer_name}
                        onChange={(event) =>
                            setData('customer_name', event.target.value)
                        }
                    />
                </Field>
            )}
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Quantity"
                    htmlFor="sv-qty"
                    error={errors.quantity}
                    required
                >
                    <Input
                        id="sv-qty"
                        type="number"
                        min={1}
                        max={99}
                        value={data.quantity}
                        onChange={(event) =>
                            setData('quantity', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Scheduled for"
                    htmlFor="sv-when"
                    error={errors.scheduled_for}
                >
                    <Input
                        id="sv-when"
                        type="datetime-local"
                        value={data.scheduled_for}
                        onChange={(event) =>
                            setData('scheduled_for', event.target.value)
                        }
                    />
                </Field>
            </div>
            <Field label="Notes" htmlFor="sv-notes" error={errors.notes}>
                <Textarea
                    id="sv-notes"
                    rows={2}
                    value={data.notes}
                    onChange={(event) => setData('notes', event.target.value)}
                />
            </Field>
        </FormDialog>
    );
}

function SettleDialog({
    target,
    onClose,
    methods,
}: {
    target: SettleTarget | null;
    onClose: () => void;
    methods: Method[];
}) {
    const form = useForm({
        settlement: 'paid_at_outlet',
        payment_method: 'cash',
        payment_reference: '',
    });
    const { data, setData, errors } = form;
    const method = methods.find((row) => row.value === data.payment_method);

    useEffect(() => {
        if (target) {
            form.clearErrors();
            form.setData({
                settlement: target.linked ? 'room_charge' : 'paid_at_outlet',
                payment_method: 'cash',
                payment_reference: '',
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [target]);

    function submit() {
        if (!target) {
            return;
        }

        const url =
            target.kind === 'order'
                ? admin.outlets.orders.settle.url(target.reference)
                : admin.outlets.services.complete.url(target.reference);
        form.post(url, {
            preserveScroll: true,
            onSuccess: onClose,
            onError: notifyValidationFailed,
        });
    }

    return (
        <FormDialog
            open={target !== null}
            onOpenChange={(open) => !open && onClose()}
            title={
                target?.kind === 'service' ? 'Complete service' : 'Settle order'
            }
            description={target ? `Total ${target.total.formatted}` : undefined}
            submitLabel="Settle"
            processing={form.processing}
            onSubmit={submit}
        >
            <fieldset className="grid gap-3">
                <legend className="sr-only">Settlement</legend>
                {[
                    {
                        value: 'room_charge',
                        title: 'Charge to room',
                        hint: target?.linked
                            ? 'Posted once to the guest folio.'
                            : 'Only for orders linked to an in-house guest.',
                        disabled: !target?.linked,
                    },
                    {
                        value: 'paid_at_outlet',
                        title: 'Paid at the outlet',
                        hint: 'Cash, card or mobile money taken now.',
                        disabled: false,
                    },
                ].map((choice) => (
                    <label
                        key={choice.value}
                        className={`flex items-start gap-3 rounded-lg border p-3 has-checked:border-primary ${choice.disabled ? 'opacity-60' : 'cursor-pointer'}`}
                    >
                        <input
                            type="radio"
                            name="settlement"
                            value={choice.value}
                            checked={data.settlement === choice.value}
                            disabled={choice.disabled}
                            onChange={() => setData('settlement', choice.value)}
                            className="mt-1 accent-primary"
                        />
                        <span>
                            <span className="font-medium">{choice.title}</span>
                            <span className="block text-sm text-muted-foreground">
                                {choice.hint}
                            </span>
                        </span>
                    </label>
                ))}
            </fieldset>
            {errors.settlement && (
                <p className="text-sm text-destructive">{errors.settlement}</p>
            )}
            {data.settlement === 'paid_at_outlet' && (
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        label="Method"
                        htmlFor="st-method"
                        error={errors.payment_method}
                        required
                    >
                        <OptionSelect
                            id="st-method"
                            value={data.payment_method}
                            options={methods}
                            onChange={(value) =>
                                setData('payment_method', value)
                            }
                        />
                    </Field>
                    {method?.requires_reference && (
                        <Field
                            label="Reference"
                            htmlFor="st-ref"
                            error={errors.payment_reference}
                        >
                            <Input
                                id="st-ref"
                                value={data.payment_reference}
                                onChange={(event) =>
                                    setData(
                                        'payment_reference',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    )}
                </div>
            )}
            {Object.entries(errors)
                .filter(([key]) => key === 'reservation_id')
                .map(([key, message]) => (
                    <p key={key} className="text-sm text-destructive">
                        {message}
                    </p>
                ))}
        </FormDialog>
    );
}

function CancelOrderDialog({
    order,
    onClose,
}: {
    order: Order | null;
    onClose: () => void;
}) {
    const form = useForm({ reason: '' });

    useEffect(() => {
        if (order) {
            form.reset();
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [order]);

    return (
        <FormDialog
            open={order !== null}
            onOpenChange={(open) => !open && onClose()}
            title={`Cancel order ${order?.reference ?? ''}`}
            description="Unsettled orders can be cancelled. The reason is kept on the order."
            submitLabel="Cancel order"
            processing={form.processing}
            onSubmit={() =>
                order &&
                form.post(admin.outlets.orders.cancel.url(order.reference), {
                    preserveScroll: true,
                    onSuccess: onClose,
                    onError: notifyValidationFailed,
                })
            }
        >
            <Field
                label="Reason"
                htmlFor="co-reason"
                error={form.errors.reason}
                required
            >
                <Textarea
                    id="co-reason"
                    rows={2}
                    value={form.data.reason}
                    onChange={(event) =>
                        form.setData('reason', event.target.value)
                    }
                />
            </Field>
        </FormDialog>
    );
}

OutletsIndex.layout = {
    breadcrumbs: [
        { title: 'Restaurant & services', href: admin.outlets.index() },
    ],
};
