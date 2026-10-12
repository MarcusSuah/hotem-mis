import { DocumentPreview } from '@/components/admin/document-preview';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    CreditCard,
    FileText,
    Plus,
    Printer,
    Receipt,
    RotateCcw,
    Undo2,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { RecordPaymentDialog } from '@/components/admin/billing/record-payment-dialog';
import type { PaymentMethodOption } from '@/components/admin/billing/record-payment-dialog';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import { Field } from '@/components/admin/field';
import { FormDialog } from '@/components/admin/form-dialog';
import { PageHeader } from '@/components/admin/page-header';
import { StatCard } from '@/components/admin/stat-card';
import { ReservationStatusBadge } from '@/components/admin/status-badges';
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
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { notifyValidationFailed } from '@/lib/form';
import { formatDate, formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';
import type { MoneyValue } from '@/types';

type Charge = {
    id: number;
    category: string;
    category_label: string;
    description: string;
    quantity: number;
    unit_amount: MoneyValue;
    amount: MoneyValue;
    service_date: string;
    posted_by: string | null;
    note: string | null;
    is_reversal: boolean;
    is_reversed: boolean;
};

type Payment = {
    reference: string;
    method_label: string;
    status: string;
    amount: MoneyValue;
    applied: MoneyValue;
    exchange_rate: string | null;
    external_reference: string | null;
    received_by: string | null;
    received_at: string;
    refundable: MoneyValue;
};

type Props = {
    folio: {
        number: string;
        status: string;
        currency: string;
        guest: { id: number; name: string; email: string | null };
        reservation: {
            reference: string;
            status: string;
            status_label: string;
            check_in_date: string;
            check_out_date: string;
            total: MoneyValue;
            amount_due: MoneyValue;
        } | null;
        summary: {
            charges: MoneyValue;
            payments: MoneyValue;
            refunds: MoneyValue;
            net_paid: MoneyValue;
            balance: MoneyValue;
        };
        charges: Charge[];
        payments: Payment[];
        refunds: {
            reference: string;
            payment: string;
            amount: MoneyValue;
            applied: MoneyValue;
            reason: string;
            processed_by: string | null;
            processed_at: string;
        }[];
        invoices: {
            number: string;
            status: string;
            total: MoneyValue;
            balance: MoneyValue;
            issued_at: string;
        }[];
    };
    options: {
        categories: { value: string; label: string }[];
        methods: PaymentMethodOption[];
        currencies: string[];
    };
    can: {
        postCharge: boolean;
        reverse: boolean;
        recordPayment: boolean;
        refund: boolean;
        invoice: boolean;
    };
};

export default function Folio({ folio, options, can }: Props) {
    const [dialog, setDialog] = useState<
        'charge' | 'payment' | 'invoice' | null
    >(null);
    const [reversing, setReversing] = useState<Charge | null>(null);
    const [refunding, setRefunding] = useState<Payment | null>(null);
    const [voiding, setVoiding] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const { summary } = folio;

    return (
        <>
            <Head title={`Folio ${folio.number}`} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={`Folio ${folio.number}`}
                    description={`${folio.guest.name}${folio.reservation ? ` · ${formatDate(folio.reservation.check_in_date)} → ${formatDate(folio.reservation.check_out_date)}` : ''} · amounts in ${folio.currency}`}
                    actions={
                        <>
                            {folio.reservation && (
                                <Button variant="outline" asChild>
                                    <Link
                                        href={admin.reservations.show(
                                            folio.reservation.reference,
                                        )}
                                    >
                                        Reservation{' '}
                                        {folio.reservation.reference}
                                    </Link>
                                </Button>
                            )}
                            {can.postCharge && (
                                <Button
                                    variant="outline"
                                    onClick={() => setDialog('charge')}
                                >
                                    <Plus />
                                    Post charge
                                </Button>
                            )}
                            {can.invoice && folio.charges.length > 0 && (
                                <Button
                                    variant="outline"
                                    onClick={() => setDialog('invoice')}
                                >
                                    <FileText />
                                    Issue invoice
                                </Button>
                            )}
                            {can.recordPayment && (
                                <Button onClick={() => setDialog('payment')}>
                                    <CreditCard />
                                    Record payment
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="flex flex-wrap gap-2">
                    <Badge
                        variant={folio.status === 'open' ? 'success' : 'muted'}
                    >
                        {folio.status === 'open' ? 'Open' : 'Closed'}
                    </Badge>
                    {folio.reservation && (
                        <ReservationStatusBadge
                            status={folio.reservation.status}
                            label={folio.reservation.status_label}
                        />
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Charges"
                        value={summary.charges.formatted}
                    />
                    <StatCard
                        label="Paid (net of refunds)"
                        value={summary.net_paid.formatted}
                    />
                    {folio.reservation && (
                        <StatCard
                            label="Expected for stay"
                            value={folio.reservation.amount_due.formatted}
                            hint="Agreed total until the stay is complete"
                        />
                    )}
                    <StatCard
                        label={
                            summary.balance.minor < 0
                                ? 'Credit owed to guest'
                                : 'Balance'
                        }
                        value={summary.balance.formatted}
                        tone="gold"
                        hint={
                            summary.balance.minor < 0
                                ? 'Refund the credit from a payment below.'
                                : undefined
                        }
                    />
                </div>

                <section className="rounded-xl border bg-card shadow-xs">
                    <h2 className="border-b p-4 font-display text-xl font-semibold">
                        Charges
                    </h2>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Date</TableHead>
                                <TableHead>Description</TableHead>
                                <TableHead className="hidden md:table-cell">
                                    Category
                                </TableHead>
                                <TableHead className="text-right">
                                    Qty
                                </TableHead>
                                <TableHead className="text-right">
                                    Amount
                                </TableHead>
                                <TableHead className="w-10" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {folio.charges.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={6}
                                        className="text-center text-muted-foreground"
                                    >
                                        No charges yet. Room nights post at the
                                        night audit or at check-out.
                                    </TableCell>
                                </TableRow>
                            )}
                            {folio.charges.map((charge) => (
                                <TableRow
                                    key={charge.id}
                                    className={cn(
                                        (charge.is_reversal ||
                                            charge.is_reversed) &&
                                            'text-muted-foreground',
                                    )}
                                >
                                    <TableCell>
                                        {formatDate(charge.service_date)}
                                    </TableCell>
                                    <TableCell className="whitespace-normal">
                                        <span
                                            className={cn(
                                                charge.is_reversed &&
                                                    'line-through',
                                            )}
                                        >
                                            {charge.description}
                                        </span>
                                        {charge.note && (
                                            <span className="block text-xs">
                                                {charge.note}
                                            </span>
                                        )}
                                        <span className="block text-xs text-muted-foreground">
                                            {charge.posted_by ?? 'System'}
                                        </span>
                                    </TableCell>
                                    <TableCell className="hidden md:table-cell">
                                        {charge.category_label}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        {charge.quantity}
                                    </TableCell>
                                    <TableCell className="text-right font-medium">
                                        {charge.amount.formatted}
                                    </TableCell>
                                    <TableCell>
                                        {can.reverse &&
                                            !charge.is_reversal &&
                                            !charge.is_reversed && (
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label={`Reverse ${charge.description}`}
                                                    onClick={() =>
                                                        setReversing(charge)
                                                    }
                                                >
                                                    <Undo2 />
                                                </Button>
                                            )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </section>

                <div className="grid gap-6 xl:grid-cols-2">
                    <section className="rounded-xl border bg-card shadow-xs">
                        <h2 className="border-b p-4 font-display text-xl font-semibold">
                            Payments
                        </h2>
                        <ul className="divide-y">
                            {folio.payments.length === 0 && (
                                <li className="p-4 text-sm text-muted-foreground">
                                    No payments recorded.
                                </li>
                            )}
                            {folio.payments.map((payment) => (
                                <li
                                    key={payment.reference}
                                    className="flex flex-wrap items-center justify-between gap-3 p-4 text-sm"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {payment.amount.formatted}{' '}
                                            <span className="text-muted-foreground">
                                                · {payment.method_label}
                                            </span>
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {payment.reference} ·{' '}
                                            {formatDateTime(
                                                payment.received_at,
                                            )}{' '}
                                            · {payment.received_by ?? 'System'}
                                            {payment.external_reference &&
                                                ` · ref ${payment.external_reference}`}
                                        </p>
                                        {payment.exchange_rate && (
                                            <p className="text-xs text-muted-foreground">
                                                Applied as{' '}
                                                {payment.applied.formatted}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex gap-1">
                                        <DocumentPreview
                                            url={admin.payments.receipt.url(
                                                payment.reference,
                                            )}
                                            title={`Receipt ${payment.reference}`}
                                            filename={`receipt-${payment.reference}.pdf`}
                                            trigger={(open) => (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={open}
                                                >
                                                    <Receipt />
                                                    Receipt
                                                </Button>
                                            )}
                                        />
                                        {can.refund &&
                                            payment.refundable.minor > 0 && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        setRefunding(payment)
                                                    }
                                                >
                                                    <RotateCcw />
                                                    Refund
                                                </Button>
                                            )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                        {folio.refunds.length > 0 && (
                            <div className="border-t p-4">
                                <h3 className="mb-2 text-sm font-semibold">
                                    Refunds
                                </h3>
                                <ul className="space-y-1 text-sm">
                                    {folio.refunds.map((refund) => (
                                        <li
                                            key={refund.reference}
                                            className="flex justify-between gap-2"
                                        >
                                            <span>
                                                {refund.reference} from{' '}
                                                {refund.payment} —{' '}
                                                {refund.reason}
                                                <span className="block text-xs text-muted-foreground">
                                                    {formatDateTime(
                                                        refund.processed_at,
                                                    )}{' '}
                                                    · {refund.processed_by}
                                                </span>
                                            </span>
                                            <span className="font-medium">
                                                −{refund.amount.formatted}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}
                    </section>

                    <section className="rounded-xl border bg-card shadow-xs">
                        <h2 className="border-b p-4 font-display text-xl font-semibold">
                            Invoices
                        </h2>
                        <ul className="divide-y">
                            {folio.invoices.length === 0 && (
                                <li className="p-4 text-sm text-muted-foreground">
                                    No invoices issued.
                                </li>
                            )}
                            {folio.invoices.map((invoice) => (
                                <li
                                    key={invoice.number}
                                    className="flex flex-wrap items-center justify-between gap-3 p-4 text-sm"
                                >
                                    <div>
                                        <p className="font-mono font-medium">
                                            {invoice.number}{' '}
                                            {invoice.status === 'void' && (
                                                <Badge variant="danger">
                                                    Void
                                                </Badge>
                                            )}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {formatDateTime(invoice.issued_at)}{' '}
                                            · total {invoice.total.formatted} ·
                                            balance {invoice.balance.formatted}
                                        </p>
                                    </div>
                                    <div className="flex gap-1">
                                        <DocumentPreview
                                            url={admin.invoices.show.url(
                                                invoice.number,
                                            )}
                                            title={`Invoice ${invoice.number}`}
                                            filename={`${invoice.number}.pdf`}
                                            trigger={(open) => (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={open}
                                                >
                                                    <Printer />
                                                    PDF
                                                </Button>
                                            )}
                                        />
                                        {can.invoice &&
                                            invoice.status === 'issued' && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-destructive"
                                                    onClick={() =>
                                                        setVoiding(
                                                            invoice.number,
                                                        )
                                                    }
                                                >
                                                    Void
                                                </Button>
                                            )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </section>
                </div>
            </div>

            <RecordPaymentDialog
                open={dialog === 'payment'}
                onOpenChange={(open) => setDialog(open ? 'payment' : null)}
                folioNumber={folio.number}
                suggested={summary.balance.minor > 0 ? summary.balance : null}
                methods={options.methods}
                currencies={options.currencies}
            />
            <ChargeDialog
                open={dialog === 'charge'}
                onClose={() => setDialog(null)}
                folio={folio.number}
                currency={folio.currency}
                categories={options.categories}
            />
            <ConfirmDialog
                open={dialog === 'invoice'}
                onOpenChange={(open) => !open && setDialog(null)}
                title="Issue an invoice?"
                description="The invoice is a numbered, permanent snapshot of the folio as it stands now."
                confirmLabel="Issue invoice"
                processing={processing}
                onConfirm={() =>
                    router.post(
                        admin.folios.invoices.store.url(folio.number),
                        {},
                        {
                            preserveScroll: true,
                            onStart: () => setProcessing(true),
                            onFinish: () => {
                                setProcessing(false);
                                setDialog(null);
                            },
                        },
                    )
                }
            />
            <ReasonDialog
                open={reversing !== null}
                onClose={() => setReversing(null)}
                title={`Reverse "${reversing?.description}"?`}
                description={`A reversing line of ${reversing?.amount.formatted} is posted; the original stays on record.`}
                submitLabel="Post reversal"
                url={
                    reversing
                        ? admin.folioCharges.reverse.url(reversing.id)
                        : ''
                }
            />
            <ReasonDialog
                open={voiding !== null}
                onClose={() => setVoiding(null)}
                title={`Void invoice ${voiding}?`}
                description="The invoice remains on record marked void. Optionally issue a corrected replacement from the current folio."
                submitLabel="Void invoice"
                url={voiding ? admin.invoices.void.url(voiding) : ''}
                allowReissue
            />
            <RefundDialog
                payment={refunding}
                onClose={() => setRefunding(null)}
                methods={options.methods}
            />
        </>
    );
}

function ChargeDialog({
    open,
    onClose,
    folio,
    currency,
    categories,
}: {
    open: boolean;
    onClose: () => void;
    folio: string;
    currency: string;
    categories: { value: string; label: string }[];
}) {
    const today = new Date();
    const todayIso = [
        today.getFullYear(),
        String(today.getMonth() + 1).padStart(2, '0'),
        String(today.getDate()).padStart(2, '0'),
    ].join('-');
    const form = useForm({
        category: 'food_beverage',
        description: '',
        unit_amount: '',
        quantity: '1',
        service_date: todayIso,
        note: '',
        idempotency_key: '',
    });
    const { data, setData, errors } = form;

    useEffect(() => {
        if (open) {
            form.reset();
            form.clearErrors();
            form.setData('idempotency_key', crypto.randomUUID());
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    return (
        <FormDialog
            open={open}
            onOpenChange={(next) => !next && onClose()}
            title="Post a charge"
            description={`Amounts in ${currency}. Discounts are posted as credits.`}
            submitLabel="Post charge"
            processing={form.processing}
            onSubmit={() =>
                form.post(admin.folios.charges.store.url(folio), {
                    preserveScroll: true,
                    onSuccess: onClose,
                    onError: notifyValidationFailed,
                })
            }
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Category"
                    htmlFor="charge-category"
                    error={errors.category}
                    required
                >
                    <Select
                        value={data.category}
                        onValueChange={(value) => setData('category', value)}
                    >
                        <SelectTrigger id="charge-category">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {categories.map((category) => (
                                <SelectItem
                                    key={category.value}
                                    value={category.value}
                                >
                                    {category.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>
                <Field
                    label="Date of service"
                    htmlFor="charge-date"
                    error={errors.service_date}
                    required
                >
                    <Input
                        id="charge-date"
                        type="date"
                        max={todayIso}
                        value={data.service_date}
                        onChange={(event) =>
                            setData('service_date', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Description"
                    htmlFor="charge-description"
                    error={errors.description}
                    required
                    className="sm:col-span-2"
                >
                    <Input
                        id="charge-description"
                        value={data.description}
                        onChange={(event) =>
                            setData('description', event.target.value)
                        }
                        placeholder="Dinner — Atlantic Grill"
                    />
                </Field>
                <Field
                    label={`Unit amount (${currency})`}
                    htmlFor="charge-amount"
                    error={errors.unit_amount}
                    required
                >
                    <Input
                        id="charge-amount"
                        inputMode="decimal"
                        value={data.unit_amount}
                        onChange={(event) =>
                            setData('unit_amount', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Quantity"
                    htmlFor="charge-qty"
                    error={errors.quantity}
                    required
                >
                    <Input
                        id="charge-qty"
                        type="number"
                        min={1}
                        value={data.quantity}
                        onChange={(event) =>
                            setData('quantity', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Note"
                    htmlFor="charge-note"
                    error={errors.note}
                    className="sm:col-span-2"
                >
                    <Input
                        id="charge-note"
                        value={data.note}
                        onChange={(event) =>
                            setData('note', event.target.value)
                        }
                    />
                </Field>
            </div>
        </FormDialog>
    );
}

function ReasonDialog({
    open,
    onClose,
    title,
    description,
    submitLabel,
    url,
    allowReissue = false,
}: {
    open: boolean;
    onClose: () => void;
    title: string;
    description: string;
    submitLabel: string;
    url: string;
    allowReissue?: boolean;
}) {
    const form = useForm({ reason: '', reissue: allowReissue });

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
            title={title}
            description={description}
            submitLabel={submitLabel}
            processing={form.processing}
            onSubmit={() =>
                form.post(url, {
                    preserveScroll: true,
                    onSuccess: onClose,
                    onError: notifyValidationFailed,
                })
            }
        >
            <Field
                label="Reason"
                htmlFor="reason"
                error={form.errors.reason}
                required
            >
                <Textarea
                    id="reason"
                    rows={3}
                    value={form.data.reason}
                    onChange={(event) =>
                        form.setData('reason', event.target.value)
                    }
                />
            </Field>
            {allowReissue && (
                <label
                    htmlFor="reissue"
                    className="flex items-center gap-2 text-sm"
                >
                    <Checkbox
                        id="reissue"
                        checked={form.data.reissue}
                        onCheckedChange={(checked) =>
                            form.setData('reissue', checked === true)
                        }
                    />
                    Issue a corrected replacement invoice
                </label>
            )}
        </FormDialog>
    );
}

function RefundDialog({
    payment,
    onClose,
    methods,
}: {
    payment: Payment | null;
    onClose: () => void;
    methods: PaymentMethodOption[];
}) {
    const form = useForm({
        amount: '',
        method: 'cash',
        reason: '',
        external_reference: '',
        idempotency_key: '',
    });
    const { data, setData, errors } = form;

    useEffect(() => {
        if (payment) {
            form.clearErrors();
            form.setData({
                amount: payment.refundable.amount,
                method: 'cash',
                reason: '',
                external_reference: '',
                idempotency_key: crypto.randomUUID(),
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [payment?.reference]);

    return (
        <FormDialog
            open={payment !== null}
            onOpenChange={(next) => !next && onClose()}
            title={`Refund from ${payment?.reference}`}
            description={`Up to ${payment?.refundable.formatted} can be refunded, in the payment's currency.`}
            submitLabel="Process refund"
            processing={form.processing}
            onSubmit={() =>
                payment &&
                form.post(admin.payments.refunds.store.url(payment.reference), {
                    preserveScroll: true,
                    onSuccess: onClose,
                    onError: notifyValidationFailed,
                })
            }
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label={`Amount (${payment?.amount.currency})`}
                    htmlFor="refund-amount"
                    error={errors.amount}
                    required
                >
                    <Input
                        id="refund-amount"
                        inputMode="decimal"
                        value={data.amount}
                        onChange={(event) =>
                            setData('amount', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Paid back by"
                    htmlFor="refund-method"
                    error={errors.method}
                    required
                >
                    <Select
                        value={data.method}
                        onValueChange={(value) => setData('method', value)}
                    >
                        <SelectTrigger id="refund-method">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {methods.map((method) => (
                                <SelectItem
                                    key={method.value}
                                    value={method.value}
                                >
                                    {method.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>
                <Field
                    label="Reason"
                    htmlFor="refund-reason"
                    error={errors.reason}
                    required
                    className="sm:col-span-2"
                >
                    <Textarea
                        id="refund-reason"
                        rows={2}
                        value={data.reason}
                        onChange={(event) =>
                            setData('reason', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Transaction reference"
                    htmlFor="refund-ref"
                    error={errors.external_reference}
                    className="sm:col-span-2"
                >
                    <Input
                        id="refund-ref"
                        value={data.external_reference}
                        onChange={(event) =>
                            setData('external_reference', event.target.value)
                        }
                    />
                </Field>
            </div>
        </FormDialog>
    );
}

Folio.layout = {
    breadcrumbs: [{ title: 'Payments', href: admin.payments.index() }],
};
