import { Head, router, useForm } from '@inertiajs/react';
import {
    ArrowLeftRight,
    BadgeCheck,
    CalendarClock,
    CalendarX,
    Mail,
    Pencil,
    Phone,
    Star,
    UserX,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import { Field } from '@/components/admin/field';
import { FormDialog } from '@/components/admin/form-dialog';
import { PageHeader } from '@/components/admin/page-header';
import {
    PaymentStatusBadge,
    ReservationStatusBadge,
} from '@/components/admin/status-badges';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/hooks/use-can';
import { notifyValidationFailed } from '@/lib/form';
import { formatDate, formatDateTime } from '@/lib/format';
import admin from '@/routes/admin';
import type { MoneyValue } from '@/types';
import type { ReservationSummary } from './index';

type Reservation = ReservationSummary & {
    contact_name: string;
    contact_email: string | null;
    contact_phone: string | null;
    special_requests: string | null;
    rate_plan: { id: number; name: string; code: string } | null;
    currency: string;
    base_currency: string;
    exchange_rate: string | null;
    amounts: Record<
        | 'room_total'
        | 'extras_total'
        | 'discount'
        | 'service_charge'
        | 'tax'
        | 'total'
        | 'base_total'
        | 'deposit_due'
        | 'amount_paid'
        | 'balance_due'
        | 'cancellation_fee',
        MoneyValue
    >;
    cancellation_policy: {
        name?: string;
        description?: string | null;
        free_cancellation_hours?: number;
        penalty_type?: string;
        penalty_percent?: string;
    };
    cancellation_reason: string | null;
    booked_by: string | null;
    timestamps: Record<string, string | null>;
    allocations: {
        id: number;
        room_number: string | null;
        room_type: string | null;
        start_date: string;
        end_date: string;
        released_at: string | null;
        release_reason: string | null;
    }[];
    night_records: {
        date: string;
        room_number: string | null;
        amount: MoneyValue;
    }[];
    history: {
        id: number;
        from: string | null;
        to: string;
        by: string | null;
        note: string | null;
        at: string;
    }[];
};

type RoomOption = {
    id: number;
    number: string;
    floor: number;
    room_type: string;
    same_type: boolean;
};

type Props = {
    reservation: Reservation;
    roomOptions: RoomOption[];
    can: {
        update: boolean;
        confirm: boolean;
        cancel: boolean;
        noShow: boolean;
        assignRoom: boolean;
    };
};

export default function ShowReservation({
    reservation,
    roomOptions,
    can,
}: Props) {
    const [dialog, setDialog] = useState<
        'modify' | 'cancel' | 'room' | 'no-show' | 'confirm' | null
    >(null);
    const [processing, setProcessing] = useState(false);
    const close = () => setDialog(null);
    const { amounts } = reservation;

    function post(url: string) {
        router.post(
            url,
            {},
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => {
                    setProcessing(false);
                    close();
                },
            },
        );
    }

    return (
        <>
            <Head title={`Reservation ${reservation.reference}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={reservation.reference}
                    description={`${reservation.source_label} booking${reservation.booked_by ? ` by ${reservation.booked_by}` : ''} · created ${formatDateTime(reservation.timestamps.created_at)}`}
                    actions={
                        <>
                            {can.confirm && (
                                <Button onClick={() => setDialog('confirm')}>
                                    <BadgeCheck />
                                    Confirm
                                </Button>
                            )}
                            {can.update && (
                                <Button
                                    variant="outline"
                                    onClick={() => setDialog('modify')}
                                >
                                    <Pencil />
                                    Modify stay
                                </Button>
                            )}
                            {can.assignRoom && (
                                <Button
                                    variant="outline"
                                    onClick={() => setDialog('room')}
                                >
                                    <ArrowLeftRight />
                                    Move room
                                </Button>
                            )}
                            {can.noShow && (
                                <Button
                                    variant="outline"
                                    onClick={() => setDialog('no-show')}
                                >
                                    <UserX />
                                    No-show
                                </Button>
                            )}
                            {can.cancel && (
                                <Button
                                    variant="outline"
                                    className="text-destructive"
                                    onClick={() => setDialog('cancel')}
                                >
                                    <CalendarX />
                                    Cancel
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="flex flex-wrap gap-2">
                    <ReservationStatusBadge
                        status={reservation.status}
                        label={reservation.status_label}
                    />
                    <PaymentStatusBadge
                        status={reservation.payment_status}
                        label={reservation.payment_status_label}
                    />
                    {reservation.is_complimentary && (
                        <Badge variant="gold">Complimentary</Badge>
                    )}
                    {reservation.timestamps.expires_at &&
                        reservation.status === 'pending' && (
                            <Badge variant="warning">
                                <CalendarClock />
                                Hold expires{' '}
                                {formatDateTime(
                                    reservation.timestamps.expires_at,
                                )}
                            </Badge>
                        )}
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <Card title="Stay">
                            <dl className="grid gap-4 text-sm sm:grid-cols-4">
                                <Item
                                    label="Check-in"
                                    value={formatDate(
                                        reservation.check_in_date,
                                    )}
                                />
                                <Item
                                    label="Check-out"
                                    value={formatDate(
                                        reservation.check_out_date,
                                    )}
                                />
                                <Item
                                    label="Nights"
                                    value={reservation.nights}
                                />
                                <Item
                                    label="Guests"
                                    value={`${reservation.adults} adult${reservation.adults === 1 ? '' : 's'}${reservation.children ? `, ${reservation.children} children` : ''}`}
                                />
                                <Item
                                    label="Room"
                                    value={
                                        reservation.room
                                            ? `${reservation.room.number ?? '—'} · ${reservation.room.type ?? ''}`
                                            : 'Unassigned'
                                    }
                                />
                                <Item
                                    label="Rate plan"
                                    value={
                                        reservation.rate_plan?.name ??
                                        'Standard'
                                    }
                                />
                            </dl>
                            {reservation.special_requests && (
                                <div className="mt-4 rounded-lg bg-muted/60 p-3 text-sm">
                                    <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                                        Special requests
                                    </p>
                                    <p className="mt-1 whitespace-pre-line">
                                        {reservation.special_requests}
                                    </p>
                                </div>
                            )}
                            {reservation.cancellation_reason && (
                                <div className="mt-4 rounded-lg border border-destructive/30 bg-destructive/5 p-3 text-sm">
                                    <p className="font-medium text-destructive">
                                        Cancellation reason
                                    </p>
                                    <p>{reservation.cancellation_reason}</p>
                                </div>
                            )}
                        </Card>

                        <Card title="Room allocation">
                            <ul className="divide-y text-sm">
                                {reservation.allocations.map((allocation) => (
                                    <li
                                        key={allocation.id}
                                        className="flex flex-wrap items-center justify-between gap-2 py-2"
                                    >
                                        <span>
                                            <span className="font-medium">
                                                Room {allocation.room_number}
                                            </span>{' '}
                                            <span className="text-muted-foreground">
                                                {allocation.room_type}
                                            </span>
                                        </span>
                                        <span className="text-muted-foreground">
                                            {formatDate(allocation.start_date)}{' '}
                                            → {formatDate(allocation.end_date)}
                                        </span>
                                        {allocation.released_at ? (
                                            <Badge variant="muted">
                                                Released ·{' '}
                                                {allocation.release_reason}
                                            </Badge>
                                        ) : (
                                            <Badge variant="success">
                                                Active
                                            </Badge>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        </Card>

                        <Card title="History">
                            <ol className="space-y-3 text-sm">
                                {reservation.history.map((entry) => (
                                    <li key={entry.id} className="flex gap-3">
                                        <span className="mt-1.5 size-2 shrink-0 rounded-full bg-highlight" />
                                        <div>
                                            <p>
                                                <span className="font-medium">
                                                    {entry.from
                                                        ? `${entry.from} → ${entry.to}`
                                                        : entry.to}
                                                </span>
                                                {entry.by && (
                                                    <span className="text-muted-foreground">
                                                        {' '}
                                                        by {entry.by}
                                                    </span>
                                                )}
                                            </p>
                                            {entry.note && (
                                                <p className="text-muted-foreground">
                                                    {entry.note}
                                                </p>
                                            )}
                                            <p className="text-xs text-muted-foreground">
                                                {formatDateTime(entry.at)}
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        </Card>
                    </div>

                    <div className="space-y-6">
                        <Card title="Guest">
                            <p className="flex items-center gap-1 font-display text-xl font-semibold">
                                {reservation.guest?.name}
                                {reservation.guest?.is_vip && (
                                    <Star className="size-4 fill-highlight text-highlight" />
                                )}
                            </p>
                            <div className="mt-2 space-y-1 text-sm text-muted-foreground">
                                {reservation.contact_email && (
                                    <a
                                        href={`mailto:${reservation.contact_email}`}
                                        className="flex items-center gap-2 hover:text-foreground"
                                    >
                                        <Mail className="size-4" />{' '}
                                        {reservation.contact_email}
                                    </a>
                                )}
                                {reservation.contact_phone && (
                                    <a
                                        href={`tel:${reservation.contact_phone}`}
                                        className="flex items-center gap-2 hover:text-foreground"
                                    >
                                        <Phone className="size-4" />{' '}
                                        {reservation.contact_phone}
                                    </a>
                                )}
                            </div>
                        </Card>

                        <Card title="Charges">
                            <dl className="space-y-1.5 text-sm">
                                <Line
                                    label={`Room (${reservation.nights} nights)`}
                                    value={amounts.room_total}
                                />
                                {amounts.extras_total.minor !== 0 && (
                                    <Line
                                        label="Extras"
                                        value={amounts.extras_total}
                                    />
                                )}
                                {amounts.discount.minor !== 0 && (
                                    <Line
                                        label="Discount"
                                        value={amounts.discount}
                                        negative
                                    />
                                )}
                                <Line
                                    label="Service charge"
                                    value={amounts.service_charge}
                                />
                                <Line label="Tax" value={amounts.tax} />
                                <div className="flex justify-between border-t pt-2 text-base font-semibold">
                                    <dt>Total</dt>
                                    <dd>{amounts.total.formatted}</dd>
                                </div>
                                <Line
                                    label="Paid"
                                    value={amounts.amount_paid}
                                />
                                <div className="flex justify-between font-medium">
                                    <dt>Balance</dt>
                                    <dd>{amounts.balance_due.formatted}</dd>
                                </div>
                                <Line
                                    label="Deposit required"
                                    value={amounts.deposit_due}
                                    muted
                                />
                                {amounts.cancellation_fee.minor > 0 && (
                                    <Line
                                        label="Cancellation / no-show fee"
                                        value={amounts.cancellation_fee}
                                    />
                                )}
                            </dl>
                            {reservation.exchange_rate && (
                                <p className="mt-3 text-xs text-muted-foreground">
                                    Charged in {reservation.currency} at the
                                    booking rate 1 {reservation.base_currency} ={' '}
                                    {Number(
                                        reservation.exchange_rate,
                                    ).toLocaleString()}{' '}
                                    {reservation.currency} (
                                    {amounts.base_total.formatted}).
                                </p>
                            )}
                        </Card>

                        <Card title="Cancellation terms">
                            <p className="text-sm font-medium">
                                {reservation.cancellation_policy.name ??
                                    'Standard'}
                            </p>
                            {reservation.cancellation_policy.description && (
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {
                                        reservation.cancellation_policy
                                            .description
                                    }
                                </p>
                            )}
                        </Card>
                    </div>
                </div>
            </div>

            <ModifyDialog
                open={dialog === 'modify'}
                onClose={close}
                reservation={reservation}
            />
            <RoomDialog
                open={dialog === 'room'}
                onClose={close}
                reservation={reservation}
                options={roomOptions}
            />
            <CancelDialog
                open={dialog === 'cancel'}
                onClose={close}
                reservation={reservation}
            />
            <ConfirmDialog
                open={dialog === 'confirm'}
                onOpenChange={(open) => !open && close()}
                title="Confirm this reservation?"
                description="The hold expiry is removed and the guest's room is guaranteed."
                confirmLabel="Confirm reservation"
                processing={processing}
                onConfirm={() =>
                    post(admin.reservations.confirm.url(reservation.reference))
                }
            />
            <ConfirmDialog
                open={dialog === 'no-show'}
                onOpenChange={(open) => !open && close()}
                title="Mark as no-show?"
                description="The room is released for resale and the first night is recorded as a no-show fee."
                confirmLabel="Mark no-show"
                destructive
                processing={processing}
                onConfirm={() =>
                    post(admin.reservations.noShow.url(reservation.reference))
                }
            />
        </>
    );
}

function Card({
    title,
    children,
}: {
    title: string;
    children: React.ReactNode;
}) {
    return (
        <section className="rounded-xl border bg-card p-5 shadow-xs">
            <h2 className="mb-3 font-display text-xl font-semibold">{title}</h2>
            {children}
        </section>
    );
}

function Item({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div>
            <dt className="text-xs tracking-wide text-muted-foreground uppercase">
                {label}
            </dt>
            <dd className="mt-0.5 font-medium">{value}</dd>
        </div>
    );
}

function Line({
    label,
    value,
    negative = false,
    muted = false,
}: {
    label: string;
    value: MoneyValue;
    negative?: boolean;
    muted?: boolean;
}) {
    return (
        <div
            className={
                muted
                    ? 'flex justify-between text-xs text-muted-foreground'
                    : 'flex justify-between'
            }
        >
            <dt>{label}</dt>
            <dd>
                {negative ? '−' : ''}
                {value.formatted}
            </dd>
        </div>
    );
}

function ModifyDialog({
    open,
    onClose,
    reservation,
}: {
    open: boolean;
    onClose: () => void;
    reservation: Reservation;
}) {
    const form = useForm({
        check_in_date: reservation.check_in_date,
        check_out_date: reservation.check_out_date,
        adults: String(reservation.adults),
        children: String(reservation.children),
    });
    const { data, setData, errors } = form;

    useEffect(() => {
        if (open) {
            form.clearErrors();
            form.setData({
                check_in_date: reservation.check_in_date,
                check_out_date: reservation.check_out_date,
                adults: String(reservation.adults),
                children: String(reservation.children),
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    return (
        <FormDialog
            open={open}
            onOpenChange={(next) => !next && onClose()}
            title="Modify stay"
            description="Availability is re-checked. The current room is kept when free; otherwise another room of the same type is assigned. The stay is re-priced at the exchange rate agreed at booking."
            submitLabel="Update reservation"
            processing={form.processing}
            onSubmit={() =>
                form.put(admin.reservations.update.url(reservation.reference), {
                    preserveScroll: true,
                    onSuccess: onClose,
                    onError: notifyValidationFailed,
                })
            }
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Check-in"
                    htmlFor="modify-in"
                    error={errors.check_in_date}
                    required
                >
                    <Input
                        id="modify-in"
                        type="date"
                        value={data.check_in_date}
                        onChange={(event) =>
                            setData('check_in_date', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Check-out"
                    htmlFor="modify-out"
                    error={errors.check_out_date}
                    required
                >
                    <Input
                        id="modify-out"
                        type="date"
                        min={data.check_in_date}
                        value={data.check_out_date}
                        onChange={(event) =>
                            setData('check_out_date', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Adults"
                    htmlFor="modify-adults"
                    error={errors.adults}
                    required
                >
                    <Input
                        id="modify-adults"
                        type="number"
                        min={1}
                        value={data.adults}
                        onChange={(event) =>
                            setData('adults', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Children"
                    htmlFor="modify-children"
                    error={errors.children}
                    required
                >
                    <Input
                        id="modify-children"
                        type="number"
                        min={0}
                        value={data.children}
                        onChange={(event) =>
                            setData('children', event.target.value)
                        }
                    />
                </Field>
            </div>
            {(errors as Record<string, string | undefined>).status && (
                <p className="text-sm text-destructive">
                    {(errors as Record<string, string>).status}
                </p>
            )}
        </FormDialog>
    );
}

function RoomDialog({
    open,
    onClose,
    reservation,
    options,
}: {
    open: boolean;
    onClose: () => void;
    reservation: Reservation;
    options: RoomOption[];
}) {
    const form = useForm({ room_id: '', reason: '' });
    const { data, setData, errors } = form;

    useEffect(() => {
        if (open) {
            form.reset();
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    return (
        <FormDialog
            open={open}
            onOpenChange={(next) => !next && onClose()}
            title="Move to another room"
            description={
                reservation.status === 'checked_in'
                    ? 'In-house guest: the move applies from tonight; earlier nights stay on the original room.'
                    : 'The whole stay moves to the selected room. The agreed price does not change.'
            }
            submitLabel="Move guest"
            processing={form.processing}
            onSubmit={() =>
                form.put(
                    admin.reservations.room.update.url(reservation.reference),
                    {
                        preserveScroll: true,
                        onSuccess: onClose,
                        onError: notifyValidationFailed,
                    },
                )
            }
        >
            {options.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No other suitable rooms are free for the remaining nights.
                </p>
            ) : (
                <Field
                    label="New room"
                    htmlFor="new-room"
                    error={errors.room_id}
                    required
                >
                    <Select
                        value={data.room_id}
                        onValueChange={(value) => setData('room_id', value)}
                    >
                        <SelectTrigger id="new-room">
                            <SelectValue placeholder="Choose a room" />
                        </SelectTrigger>
                        <SelectContent>
                            {options.map((room) => (
                                <SelectItem
                                    key={room.id}
                                    value={String(room.id)}
                                >
                                    Room {room.number} · {room.room_type}
                                    {!room.same_type && ' (different type)'}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>
            )}
            <Field label="Reason" htmlFor="move-reason" error={errors.reason}>
                <Input
                    id="move-reason"
                    value={data.reason}
                    onChange={(event) => setData('reason', event.target.value)}
                    placeholder="Upgrade, maintenance, guest request…"
                />
            </Field>
        </FormDialog>
    );
}

function CancelDialog({
    open,
    onClose,
    reservation,
}: {
    open: boolean;
    onClose: () => void;
    reservation: Reservation;
}) {
    const { can } = useCan();
    const form = useForm({ reason: '', waive_fee: false });
    const { data, setData, errors } = form;
    const policy = reservation.cancellation_policy;

    useEffect(() => {
        if (open) {
            form.reset();
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    return (
        <FormDialog
            open={open}
            onOpenChange={(next) => !next && onClose()}
            title={`Cancel ${reservation.reference}?`}
            description="The room is released immediately. Any fee is calculated from the policy the guest booked under."
            submitLabel="Cancel reservation"
            processing={form.processing}
            onSubmit={() =>
                form.post(
                    admin.reservations.cancel.url(reservation.reference),
                    {
                        preserveScroll: true,
                        onSuccess: onClose,
                        onError: notifyValidationFailed,
                    },
                )
            }
        >
            <div className="rounded-lg bg-muted/60 p-3 text-sm">
                <p className="font-medium">
                    {policy.name ?? 'Cancellation policy'}
                </p>
                <p className="text-muted-foreground">
                    {policy.description ?? 'See the booking terms.'}
                </p>
            </div>
            <Field
                label="Reason"
                htmlFor="cancel-reason"
                error={errors.reason}
                required
            >
                <Textarea
                    id="cancel-reason"
                    rows={3}
                    value={data.reason}
                    onChange={(event) => setData('reason', event.target.value)}
                />
            </Field>
            {can('payments.refund') && (
                <label
                    htmlFor="waive-fee"
                    className="flex items-center gap-2 text-sm"
                >
                    <Checkbox
                        id="waive-fee"
                        checked={data.waive_fee}
                        onCheckedChange={(checked) =>
                            setData('waive_fee', checked === true)
                        }
                    />
                    Waive the cancellation fee
                </label>
            )}
            {errors.waive_fee && (
                <p className="text-sm text-destructive">{errors.waive_fee}</p>
            )}
        </FormDialog>
    );
}

ShowReservation.layout = {
    breadcrumbs: [{ title: 'Reservations', href: admin.reservations.index() }],
};
