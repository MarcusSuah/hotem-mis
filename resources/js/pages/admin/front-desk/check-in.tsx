import { Head, Link, useForm } from '@inertiajs/react';
import {
    CheckCircle2,
    CircleAlert,
    CreditCard,
    FileText,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { RecordPaymentDialog } from '@/components/admin/billing/record-payment-dialog';
import type { PaymentMethodOption } from '@/components/admin/billing/record-payment-dialog';
import { Field } from '@/components/admin/field';
import { FormErrorSummary } from '@/components/admin/form-error-summary';
import { PageHeader } from '@/components/admin/page-header';
import { HousekeepingBadge } from '@/components/admin/status-badges';
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
import { Spinner } from '@/components/ui/spinner';
import { notifyValidationFailed } from '@/lib/form';
import { formatDate } from '@/lib/format';
import admin from '@/routes/admin';
import type { ReservationSummary } from '../reservations/index';
import type { MoneyValue } from '@/types';

type Reservation = ReservationSummary & {
    contact_email: string | null;
    contact_phone: string | null;
    special_requests: string | null;
    amounts: Record<string, MoneyValue>;
};

type Props = {
    reservation: Reservation;
    room: {
        number: string;
        housekeeping_status: string;
        housekeeping_label: string;
    } | null;
    guestDetails: {
        country_code: string | null;
        address: string | null;
        identity_type: string | null;
        has_identity: boolean;
    };
    blockers: Record<string, string>;
    folio: string | null;
    depositOutstanding: MoneyValue;
    paymentOptions: { methods: PaymentMethodOption[]; currencies: string[] };
    can: {
        waiveDeposit: boolean;
        recordIdentity: boolean;
        recordPayment: boolean;
        assignRoom: boolean;
    };
};

type CheckInForm = {
    identity_type: string;
    identity_number: string;
    country_code: string;
    address: string;
    additional_guests: { name: string; age_category: string }[];
    waive_deposit: boolean;
    early_check_in_fee: string;
};

export default function CheckIn({
    reservation,
    room,
    guestDetails,
    blockers,
    folio,
    depositOutstanding,
    paymentOptions,
    can,
}: Props) {
    const [paying, setPaying] = useState(false);
    const form = useForm<CheckInForm>({
        identity_type: guestDetails.identity_type ?? '',
        identity_number: '',
        country_code: guestDetails.country_code ?? '',
        address: guestDetails.address ?? '',
        additional_guests: [],
        waive_deposit: false,
        early_check_in_fee: '',
    });
    const { data, setData, errors } = form;
    const errorMap = errors as Record<string, string | undefined>;

    const depositBlocked = Boolean(blockers.deposit) && !data.waive_deposit;
    const hardBlockers = Object.entries(blockers).filter(
        ([key]) => key !== 'deposit',
    );
    const ready = hardBlockers.length === 0 && !depositBlocked;

    function submit(event: React.FormEvent) {
        event.preventDefault();
        form.post(admin.frontDesk.checkIn.store.url(reservation.reference), {
            onError: notifyValidationFailed,
        });
    }

    return (
        <>
            <Head title={`Check in ${reservation.guest?.name}`} />
            <form
                onSubmit={submit}
                className="flex flex-1 flex-col gap-6 p-4 md:p-6"
            >
                <PageHeader
                    title={`Check in ${reservation.guest?.name ?? ''}`}
                    description={`${reservation.reference} · ${formatDate(reservation.check_in_date)} → ${formatDate(reservation.check_out_date)} · ${reservation.nights} night${reservation.nights === 1 ? '' : 's'}`}
                    actions={
                        <Button variant="outline" asChild>
                            <Link
                                href={admin.reservations.show(
                                    reservation.reference,
                                )}
                            >
                                View reservation
                            </Link>
                        </Button>
                    }
                />

                <FormErrorSummary errors={errors} />

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <section className="rounded-xl border bg-card p-5 shadow-xs">
                            <h2 className="mb-4 font-display text-xl font-semibold">
                                Readiness
                            </h2>
                            <ul className="space-y-3 text-sm">
                                <Check
                                    ok={
                                        !blockers.status &&
                                        !blockers.check_in_date &&
                                        !blockers.check_out_date
                                    }
                                    label="Reservation and dates"
                                    detail={
                                        blockers.status ??
                                        blockers.check_in_date ??
                                        blockers.check_out_date
                                    }
                                />
                                <Check
                                    ok={!blockers.room_id}
                                    label={
                                        room ? `Room ${room.number}` : 'Room'
                                    }
                                    detail={blockers.room_id}
                                    extra={
                                        room && (
                                            <HousekeepingBadge
                                                status={
                                                    room.housekeeping_status
                                                }
                                            />
                                        )
                                    }
                                    action={
                                        blockers.room_id && can.assignRoom ? (
                                            <Button
                                                asChild
                                                variant="outline"
                                                size="sm"
                                            >
                                                <Link
                                                    href={admin.reservations.show(
                                                        reservation.reference,
                                                    )}
                                                >
                                                    Move room
                                                </Link>
                                            </Button>
                                        ) : null
                                    }
                                />
                                <Check
                                    ok={!blockers.deposit || data.waive_deposit}
                                    label="Deposit"
                                    detail={
                                        blockers.deposit ??
                                        (depositOutstanding.minor <= 0
                                            ? `Paid ${reservation.amounts.amount_paid.formatted}`
                                            : undefined)
                                    }
                                    action={
                                        blockers.deposit &&
                                        can.recordPayment &&
                                        folio ? (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                onClick={() => setPaying(true)}
                                            >
                                                <CreditCard />
                                                Take payment
                                            </Button>
                                        ) : null
                                    }
                                />
                            </ul>
                            {blockers.deposit && can.waiveDeposit && (
                                <label
                                    htmlFor="waive"
                                    className="mt-4 flex items-center gap-2 text-sm"
                                >
                                    <Checkbox
                                        id="waive"
                                        checked={data.waive_deposit}
                                        onCheckedChange={(checked) =>
                                            setData(
                                                'waive_deposit',
                                                checked === true,
                                            )
                                        }
                                    />
                                    Waive the deposit for this guest (recorded
                                    in the audit log)
                                </label>
                            )}
                        </section>

                        <section className="rounded-xl border bg-card p-5 shadow-xs">
                            <h2 className="mb-4 font-display text-xl font-semibold">
                                Guest verification
                            </h2>
                            <div className="grid gap-4 sm:grid-cols-2">
                                {can.recordIdentity ? (
                                    <>
                                        <Field
                                            label="ID document"
                                            htmlFor="id-type"
                                            error={errors.identity_type}
                                        >
                                            <Select
                                                value={data.identity_type}
                                                onValueChange={(value) =>
                                                    setData(
                                                        'identity_type',
                                                        value,
                                                    )
                                                }
                                            >
                                                <SelectTrigger id="id-type">
                                                    <SelectValue placeholder="Not recorded" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="passport">
                                                        Passport
                                                    </SelectItem>
                                                    <SelectItem value="national_id">
                                                        National ID
                                                    </SelectItem>
                                                    <SelectItem value="drivers_license">
                                                        Driver's licence
                                                    </SelectItem>
                                                    <SelectItem value="other">
                                                        Other
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </Field>
                                        <Field
                                            label="Document number"
                                            htmlFor="id-number"
                                            error={errors.identity_number}
                                            hint={
                                                guestDetails.has_identity
                                                    ? 'A number is on file; enter a new one only to replace it.'
                                                    : 'Stored encrypted; visible only to authorised staff.'
                                            }
                                        >
                                            <Input
                                                id="id-number"
                                                autoComplete="off"
                                                value={data.identity_number}
                                                onChange={(event) =>
                                                    setData(
                                                        'identity_number',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                    </>
                                ) : (
                                    <p className="text-sm text-muted-foreground sm:col-span-2">
                                        Identity documents are recorded by a
                                        supervisor.
                                    </p>
                                )}
                                <Field
                                    label="Country (ISO code)"
                                    htmlFor="country"
                                    error={errors.country_code}
                                >
                                    <Input
                                        id="country"
                                        maxLength={2}
                                        className="uppercase"
                                        value={data.country_code}
                                        onChange={(event) =>
                                            setData(
                                                'country_code',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="LR"
                                    />
                                </Field>
                                <Field
                                    label="Home address"
                                    htmlFor="address"
                                    error={errors.address}
                                >
                                    <Input
                                        id="address"
                                        value={data.address}
                                        onChange={(event) =>
                                            setData(
                                                'address',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                        </section>

                        <section className="rounded-xl border bg-card p-5 shadow-xs">
                            <div className="mb-4 flex items-center justify-between">
                                <h2 className="font-display text-xl font-semibold">
                                    Accompanying guests
                                </h2>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        setData('additional_guests', [
                                            ...data.additional_guests,
                                            { name: '', age_category: 'adult' },
                                        ])
                                    }
                                >
                                    <Plus />
                                    Add guest
                                </Button>
                            </div>
                            {data.additional_guests.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    Party of{' '}
                                    {reservation.adults + reservation.children}.
                                    Add names for everyone staying in the room.
                                </p>
                            ) : (
                                <div className="space-y-3">
                                    {data.additional_guests.map(
                                        (guest, index) => (
                                            <div
                                                key={index}
                                                className="grid items-start gap-2 sm:grid-cols-[1fr_140px_auto]"
                                            >
                                                <Field
                                                    label={`Guest ${index + 2} name`}
                                                    htmlFor={`guest-${index}`}
                                                    error={
                                                        errorMap[
                                                            `additional_guests.${index}.name`
                                                        ]
                                                    }
                                                >
                                                    <Input
                                                        id={`guest-${index}`}
                                                        value={guest.name}
                                                        onChange={(event) =>
                                                            setData(
                                                                'additional_guests',
                                                                data.additional_guests.map(
                                                                    (
                                                                        item,
                                                                        position,
                                                                    ) =>
                                                                        position ===
                                                                        index
                                                                            ? {
                                                                                  ...item,
                                                                                  name: event
                                                                                      .target
                                                                                      .value,
                                                                              }
                                                                            : item,
                                                                ),
                                                            )
                                                        }
                                                    />
                                                </Field>
                                                <Field
                                                    label="Age"
                                                    htmlFor={`age-${index}`}
                                                >
                                                    <Select
                                                        value={
                                                            guest.age_category
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            setData(
                                                                'additional_guests',
                                                                data.additional_guests.map(
                                                                    (
                                                                        item,
                                                                        position,
                                                                    ) =>
                                                                        position ===
                                                                        index
                                                                            ? {
                                                                                  ...item,
                                                                                  age_category:
                                                                                      value,
                                                                              }
                                                                            : item,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger
                                                            id={`age-${index}`}
                                                        >
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="adult">
                                                                Adult
                                                            </SelectItem>
                                                            <SelectItem value="child">
                                                                Child
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </Field>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="mt-6 text-destructive"
                                                    aria-label={`Remove guest ${index + 2}`}
                                                    onClick={() =>
                                                        setData(
                                                            'additional_guests',
                                                            data.additional_guests.filter(
                                                                (_, position) =>
                                                                    position !==
                                                                    index,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <Trash2 />
                                                </Button>
                                            </div>
                                        ),
                                    )}
                                </div>
                            )}
                        </section>
                    </div>

                    <aside className="space-y-4 lg:sticky lg:top-4 lg:self-start">
                        <div className="rounded-xl border bg-card p-5 shadow-xs">
                            <dl className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <dt>Stay total</dt>
                                    <dd className="font-medium">
                                        {reservation.amounts.total.formatted}
                                    </dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt>Paid</dt>
                                    <dd>
                                        {
                                            reservation.amounts.amount_paid
                                                .formatted
                                        }
                                    </dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt>Deposit required</dt>
                                    <dd>
                                        {
                                            reservation.amounts.deposit_due
                                                .formatted
                                        }
                                    </dd>
                                </div>
                            </dl>
                            <Field
                                label="Early check-in fee (optional)"
                                htmlFor="early-fee"
                                error={errors.early_check_in_fee}
                                className="mt-4"
                            >
                                <Input
                                    id="early-fee"
                                    inputMode="decimal"
                                    placeholder="0.00"
                                    value={data.early_check_in_fee}
                                    onChange={(event) =>
                                        setData(
                                            'early_check_in_fee',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            {reservation.special_requests && (
                                <p className="mt-4 rounded-md bg-muted/60 p-2 text-xs">
                                    Request: {reservation.special_requests}
                                </p>
                            )}
                            <Button
                                type="submit"
                                size="lg"
                                className="mt-5 w-full"
                                disabled={!ready || form.processing}
                            >
                                {form.processing && <Spinner />}
                                Complete check-in
                            </Button>
                            {!ready && (
                                <p className="mt-2 text-center text-xs text-muted-foreground">
                                    Resolve the readiness items first.
                                </p>
                            )}
                        </div>
                        <Button variant="outline" className="w-full" asChild>
                            <a
                                href={admin.frontDesk.registrationCard.url(
                                    reservation.reference,
                                )}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <FileText />
                                Print registration card
                            </a>
                        </Button>
                    </aside>
                </div>
            </form>

            {folio && (
                <RecordPaymentDialog
                    open={paying}
                    onOpenChange={setPaying}
                    folioNumber={folio}
                    suggested={
                        depositOutstanding.minor > 0 ? depositOutstanding : null
                    }
                    methods={paymentOptions.methods}
                    currencies={paymentOptions.currencies}
                />
            )}
        </>
    );
}

function Check({
    ok,
    label,
    detail,
    extra,
    action,
}: {
    ok: boolean;
    label: string;
    detail?: string;
    extra?: React.ReactNode;
    action?: React.ReactNode;
}) {
    return (
        <li className="flex items-start gap-3">
            {ok ? (
                <CheckCircle2 className="mt-0.5 size-5 shrink-0 text-success" />
            ) : (
                <CircleAlert className="mt-0.5 size-5 shrink-0 text-warning" />
            )}
            <div className="flex-1">
                <p className="flex items-center gap-2 font-medium">
                    {label} {extra}
                </p>
                {detail && (
                    <p
                        className={
                            ok ? 'text-muted-foreground' : 'text-warning'
                        }
                    >
                        {detail}
                    </p>
                )}
            </div>
            {action}
        </li>
    );
}

CheckIn.layout = {
    breadcrumbs: [{ title: 'Front desk', href: admin.frontDesk.index() }],
};
