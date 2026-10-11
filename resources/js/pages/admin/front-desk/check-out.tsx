import { Head, Link, useForm } from '@inertiajs/react';
import { CalendarPlus, CreditCard, Receipt, TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { RecordPaymentDialog } from '@/components/admin/billing/record-payment-dialog';
import type { PaymentMethodOption } from '@/components/admin/billing/record-payment-dialog';
import { Field } from '@/components/admin/field';
import { FormDialog } from '@/components/admin/form-dialog';
import { FormErrorSummary } from '@/components/admin/form-error-summary';
import { PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { useCan } from '@/hooks/use-can';
import { notifyValidationFailed } from '@/lib/form';
import { formatDate } from '@/lib/format';
import admin from '@/routes/admin';
import type { MoneyValue } from '@/types';
import type { ReservationSummary } from '../reservations/index';

type Props = {
    reservation: ReservationSummary & { amounts: Record<string, MoneyValue> };
    folio: {
        number: string;
        summary: {
            charges: MoneyValue;
            net_paid: MoneyValue;
            balance: MoneyValue;
            by_category: Record<string, MoneyValue>;
        };
    };
    projected: {
        unposted_nights: number;
        unposted_total: MoneyValue;
        balance_after_posting: MoneyValue;
        early_departure: boolean;
    };
    paymentOptions: { methods: PaymentMethodOption[]; currencies: string[] };
    can: { settleLater: boolean; recordPayment: boolean };
};

export default function CheckOut({
    reservation,
    folio,
    projected,
    paymentOptions,
    can,
}: Props) {
    const [paying, setPaying] = useState(false);
    const [extending, setExtending] = useState(false);
    const { can: hasPermission } = useCan();
    const form = useForm({
        late_check_out_fee: '',
        settle_later: false,
        settle_later_reason: '',
    });
    const { data, setData, errors } = form;
    const balance = projected.balance_after_posting;
    const owes = balance.minor > 0;

    return (
        <>
            <Head title={`Check out ${reservation.guest?.name}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={`Check out ${reservation.guest?.name ?? ''}`}
                    description={`${reservation.reference} · Room ${reservation.room?.number ?? '—'} · ${formatDate(reservation.check_in_date)} → ${formatDate(reservation.check_out_date)}`}
                    actions={
                        <>
                            {hasPermission('bookings.update') && (
                                <Button
                                    variant="outline"
                                    onClick={() => setExtending(true)}
                                >
                                    <CalendarPlus />
                                    Extend stay
                                </Button>
                            )}
                            <Button variant="outline" asChild>
                                <Link href={admin.folios.show(folio.number)}>
                                    <Receipt />
                                    Open folio
                                </Link>
                            </Button>
                        </>
                    }
                />

                {projected.early_departure && (
                    <div className="flex items-center gap-2 rounded-lg border border-warning/40 bg-warning/10 p-3 text-sm text-warning">
                        <TriangleAlert className="size-4" />
                        Early departure: nights from today onwards will be
                        released and removed from the bill.
                    </div>
                )}

                <FormErrorSummary errors={errors} />

                <div className="grid gap-6 lg:grid-cols-3">
                    <section className="rounded-xl border bg-card p-5 shadow-xs lg:col-span-2">
                        <h2 className="mb-4 font-display text-xl font-semibold">
                            Folio {folio.number}
                        </h2>
                        <dl className="space-y-2 text-sm">
                            {Object.entries(folio.summary.by_category).map(
                                ([category, amount]) => (
                                    <div
                                        key={category}
                                        className="flex justify-between"
                                    >
                                        <dt className="capitalize">
                                            {category.replace(/_/g, ' ')}
                                        </dt>
                                        <dd>{amount.formatted}</dd>
                                    </div>
                                ),
                            )}
                            {projected.unposted_nights > 0 && (
                                <div className="flex justify-between text-muted-foreground">
                                    <dt>
                                        {projected.unposted_nights} night
                                        {projected.unposted_nights === 1
                                            ? ''
                                            : 's'}{' '}
                                        to post at check-out (room, service,
                                        tax)
                                    </dt>
                                    <dd>
                                        {projected.unposted_total.formatted}
                                    </dd>
                                </div>
                            )}
                            <div className="flex justify-between border-t pt-2">
                                <dt>Payments received</dt>
                                <dd>−{folio.summary.net_paid.formatted}</dd>
                            </div>
                            <div className="flex justify-between border-t pt-2 text-lg font-semibold">
                                <dt>
                                    {owes
                                        ? 'Balance due'
                                        : balance.minor < 0
                                          ? 'Credit to refund'
                                          : 'Settled'}
                                </dt>
                                <dd
                                    className={
                                        owes
                                            ? 'text-destructive'
                                            : 'text-success'
                                    }
                                >
                                    {balance.formatted}
                                </dd>
                            </div>
                        </dl>
                        <p className="mt-3 text-xs text-muted-foreground">
                            Projected for the booked dates; early departures are
                            re-priced when you complete check-out.
                        </p>
                    </section>

                    <aside className="space-y-4 lg:sticky lg:top-4 lg:self-start">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.post(
                                    admin.frontDesk.checkOut.store.url(
                                        reservation.reference,
                                    ),
                                    { onError: notifyValidationFailed },
                                );
                            }}
                            className="space-y-4 rounded-xl border bg-card p-5 shadow-xs"
                        >
                            <Field
                                label="Late check-out fee (optional)"
                                htmlFor="late-fee"
                                error={errors.late_check_out_fee}
                            >
                                <Input
                                    id="late-fee"
                                    inputMode="decimal"
                                    placeholder="0.00"
                                    value={data.late_check_out_fee}
                                    onChange={(event) =>
                                        setData(
                                            'late_check_out_fee',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            {can.recordPayment && (
                                <Button
                                    type="button"
                                    variant="gold"
                                    className="w-full"
                                    onClick={() => setPaying(true)}
                                >
                                    <CreditCard />
                                    Take payment
                                </Button>
                            )}
                            {owes && can.settleLater && (
                                <div className="space-y-2 rounded-lg border border-dashed p-3">
                                    <label
                                        htmlFor="settle-later"
                                        className="flex items-center gap-2 text-sm font-medium"
                                    >
                                        <Checkbox
                                            id="settle-later"
                                            checked={data.settle_later}
                                            onCheckedChange={(checked) =>
                                                setData(
                                                    'settle_later',
                                                    checked === true,
                                                )
                                            }
                                        />
                                        Allow departure with balance (settle
                                        later)
                                    </label>
                                    {data.settle_later && (
                                        <Field
                                            label="How will it be settled?"
                                            htmlFor="settle-reason"
                                            error={errors.settle_later_reason}
                                            required
                                        >
                                            <Textarea
                                                id="settle-reason"
                                                rows={2}
                                                value={data.settle_later_reason}
                                                onChange={(event) =>
                                                    setData(
                                                        'settle_later_reason',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                    )}
                                </div>
                            )}
                            <Button
                                type="submit"
                                size="lg"
                                className="w-full"
                                disabled={
                                    form.processing ||
                                    (owes && !data.settle_later)
                                }
                            >
                                {form.processing && <Spinner />}
                                Complete check-out
                            </Button>
                            {owes && !data.settle_later && (
                                <p className="text-center text-xs text-muted-foreground">
                                    Record the payment to settle the balance
                                    first.
                                </p>
                            )}
                        </form>
                    </aside>
                </div>
            </div>

            <RecordPaymentDialog
                open={paying}
                onOpenChange={setPaying}
                folioNumber={folio.number}
                suggested={owes ? balance : null}
                methods={paymentOptions.methods}
                currencies={paymentOptions.currencies}
            />
            <ExtendDialog
                open={extending}
                onClose={() => setExtending(false)}
                reference={reservation.reference}
                current={reservation.check_out_date}
            />
        </>
    );
}

function ExtendDialog({
    open,
    onClose,
    reference,
    current,
}: {
    open: boolean;
    onClose: () => void;
    reference: string;
    current: string;
}) {
    const form = useForm({ check_out_date: '' });

    return (
        <FormDialog
            open={open}
            onOpenChange={(next) => !next && onClose()}
            title="Extend stay"
            description={`Currently departing ${formatDate(current)}. Extra nights are priced at today's rates in the same room.`}
            submitLabel="Extend"
            processing={form.processing}
            onSubmit={() =>
                form.post(admin.frontDesk.extend.url(reference), {
                    preserveScroll: true,
                    onSuccess: onClose,
                    onError: notifyValidationFailed,
                })
            }
        >
            <Field
                label="New departure date"
                htmlFor="extend-date"
                error={form.errors.check_out_date}
                required
            >
                <Input
                    id="extend-date"
                    type="date"
                    min={current}
                    value={form.data.check_out_date}
                    onChange={(event) =>
                        form.setData('check_out_date', event.target.value)
                    }
                />
            </Field>
        </FormDialog>
    );
}

CheckOut.layout = {
    breadcrumbs: [{ title: 'Front desk', href: admin.frontDesk.index() }],
};
