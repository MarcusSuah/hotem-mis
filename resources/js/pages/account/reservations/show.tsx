import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, Download, Star } from 'lucide-react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import { Field } from '@/components/admin/field';
import { Stars } from '@/components/site/site-ui';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { notifyValidationFailed } from '@/lib/form';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import account from '@/routes/account';
import type { MoneyValue } from '@/types';
import { statusVariant } from './index';
import type { AccountReservation } from './index';

type Props = {
    reservation: AccountReservation & {
        special_requests: string | null;
        room_total: MoneyValue;
        service_charge: MoneyValue;
        tax: MoneyValue;
        deposit_due: MoneyValue;
        amount_paid: MoneyValue;
        balance: MoneyValue | null;
        cancellation_fee: MoneyValue | null;
    };
    cancellation: { fee: MoneyValue } | null;
    invoices: { number: string; total: MoneyValue; issued_at: string }[];
    review: {
        rating: number;
        title: string;
        body: string;
        status_label: string;
        response: string | null;
    } | null;
    canReview: boolean;
};

export default function AccountReservationShow({
    reservation,
    cancellation,
    invoices,
    review,
    canReview,
}: Props) {
    const [cancelling, setCancelling] = useState(false);
    const [processing, setProcessing] = useState(false);

    return (
        <>
            <Head title={`Booking ${reservation.reference}`} />
            <div className="mx-auto max-w-5xl space-y-6 px-4 py-12 sm:px-6">
                <Link
                    href={account.reservations.index()}
                    className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" /> My bookings
                </Link>
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="font-display text-4xl font-semibold text-primary dark:text-foreground">
                            {reservation.room_type ?? 'Your stay'}
                        </h1>
                        <p className="mt-1 text-muted-foreground">
                            Booking{' '}
                            <span className="font-mono">
                                {reservation.reference}
                            </span>
                        </p>
                    </div>
                    <Badge
                        variant={
                            statusVariant[reservation.status] ?? 'secondary'
                        }
                        className="text-sm"
                    >
                        {reservation.status_label}
                    </Badge>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    <section className="space-y-4 rounded-xl border bg-card p-6 text-sm shadow-xs md:col-span-2">
                        <dl className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt className="text-muted-foreground">
                                    Arrival
                                </dt>
                                <dd className="font-medium">
                                    {formatDate(reservation.check_in_date)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Departure
                                </dt>
                                <dd className="font-medium">
                                    {formatDate(reservation.check_out_date)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Guests
                                </dt>
                                <dd className="font-medium">
                                    {reservation.adults} adults
                                    {reservation.children > 0 &&
                                        `, ${reservation.children} children`}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">
                                    Nights
                                </dt>
                                <dd className="font-medium">
                                    {reservation.nights}
                                </dd>
                            </div>
                        </dl>
                        {reservation.special_requests && (
                            <p className="rounded-lg bg-muted/60 p-3">
                                <span className="font-medium">
                                    Your requests:{' '}
                                </span>
                                {reservation.special_requests}
                            </p>
                        )}
                        {cancellation && (
                            <div className="border-t pt-4">
                                <Button
                                    variant="outline"
                                    className="text-destructive"
                                    onClick={() => setCancelling(true)}
                                >
                                    Cancel this booking
                                </Button>
                            </div>
                        )}
                    </section>

                    <aside className="space-y-4">
                        <section className="space-y-2 rounded-xl border bg-card p-6 text-sm shadow-xs">
                            <h2 className="font-display text-xl font-semibold">
                                Charges
                            </h2>
                            <dl className="space-y-1">
                                <div className="flex justify-between">
                                    <dt>Room</dt>
                                    <dd>{reservation.room_total.formatted}</dd>
                                </div>
                                {reservation.service_charge.minor > 0 && (
                                    <div className="flex justify-between">
                                        <dt>Service charge</dt>
                                        <dd>
                                            {
                                                reservation.service_charge
                                                    .formatted
                                            }
                                        </dd>
                                    </div>
                                )}
                                {reservation.tax.minor > 0 && (
                                    <div className="flex justify-between">
                                        <dt>Tax</dt>
                                        <dd>{reservation.tax.formatted}</dd>
                                    </div>
                                )}
                                <div className="flex justify-between border-t pt-1 font-semibold">
                                    <dt>Total</dt>
                                    <dd>{reservation.total.formatted}</dd>
                                </div>
                                <div className="flex justify-between text-muted-foreground">
                                    <dt>Paid</dt>
                                    <dd>{reservation.amount_paid.formatted}</dd>
                                </div>
                                {reservation.balance &&
                                    reservation.balance.minor > 0 && (
                                        <div className="flex justify-between font-medium">
                                            <dt>Balance on your bill</dt>
                                            <dd>
                                                {reservation.balance.formatted}
                                            </dd>
                                        </div>
                                    )}
                                {reservation.cancellation_fee && (
                                    <div className="flex justify-between text-destructive">
                                        <dt>Cancellation fee</dt>
                                        <dd>
                                            {
                                                reservation.cancellation_fee
                                                    .formatted
                                            }
                                        </dd>
                                    </div>
                                )}
                            </dl>
                        </section>
                        {invoices.length > 0 && (
                            <section className="space-y-2 rounded-xl border bg-card p-6 text-sm shadow-xs">
                                <h2 className="font-display text-xl font-semibold">
                                    Invoices
                                </h2>
                                <ul className="space-y-1">
                                    {invoices.map((invoice) => (
                                        <li key={invoice.number}>
                                            <a
                                                href={account.invoices.show.url(
                                                    invoice.number,
                                                )}
                                                className="flex items-center justify-between gap-2 hover:underline"
                                            >
                                                <span className="flex items-center gap-1.5">
                                                    <Download className="size-4" />{' '}
                                                    {invoice.number}
                                                </span>
                                                <span>
                                                    {invoice.total.formatted}
                                                </span>
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        )}
                    </aside>
                </div>

                {review && (
                    <section className="space-y-2 rounded-xl border bg-card p-6 shadow-xs">
                        <div className="flex items-center justify-between gap-2">
                            <h2 className="font-display text-2xl font-semibold">
                                Your review
                            </h2>
                            <Badge variant="secondary">
                                {review.status_label}
                            </Badge>
                        </div>
                        <Stars rating={review.rating} />
                        <p className="font-medium">{review.title}</p>
                        <p className="text-sm whitespace-pre-line text-muted-foreground">
                            {review.body}
                        </p>
                        {review.response && (
                            <p className="rounded-lg bg-sage/60 p-3 text-sm dark:bg-muted/40">
                                Hotel response: {review.response}
                            </p>
                        )}
                    </section>
                )}
                {canReview && <ReviewForm reference={reservation.reference} />}
            </div>

            {cancellation && (
                <ConfirmDialog
                    open={cancelling}
                    onOpenChange={setCancelling}
                    title="Cancel this booking?"
                    description={
                        cancellation.fee.minor > 0
                            ? `Under the cancellation policy a fee of ${cancellation.fee.formatted} applies and will be added to your bill.`
                            : 'You can cancel free of charge.'
                    }
                    confirmLabel="Cancel booking"
                    destructive
                    processing={processing}
                    onConfirm={() =>
                        router.post(
                            account.reservations.cancel.url(
                                reservation.reference,
                            ),
                            {},
                            {
                                preserveScroll: true,
                                onStart: () => setProcessing(true),
                                onFinish: () => {
                                    setProcessing(false);
                                    setCancelling(false);
                                },
                            },
                        )
                    }
                />
            )}
        </>
    );
}

function ReviewForm({ reference }: { reference: string }) {
    const form = useForm({ rating: 0, title: '', body: '' });
    const { data, setData, errors } = form;

    return (
        <form
            className="space-y-4 rounded-xl border border-highlight/50 bg-card p-6 shadow-xs"
            onSubmit={(event) => {
                event.preventDefault();
                form.post(account.reservations.review.url(reference), {
                    preserveScroll: true,
                    onError: notifyValidationFailed,
                });
            }}
        >
            <div>
                <h2 className="font-display text-2xl font-semibold">
                    How was your stay?
                </h2>
                <p className="text-sm text-muted-foreground">
                    Your review is checked by our team before it appears on the
                    website, shown with your first name and last initial.
                </p>
            </div>
            <fieldset>
                <legend className="mb-1 text-sm font-medium">Rating</legend>
                <div className="flex gap-1">
                    {[1, 2, 3, 4, 5].map((value) => (
                        <button
                            key={value}
                            type="button"
                            onClick={() => setData('rating', value)}
                            aria-label={`${value} star${value > 1 ? 's' : ''}`}
                            aria-pressed={data.rating === value}
                        >
                            <Star
                                className={cn(
                                    'size-7',
                                    value <= data.rating
                                        ? 'fill-gold text-gold'
                                        : 'text-muted-foreground/40',
                                )}
                            />
                        </button>
                    ))}
                </div>
                {errors.rating && (
                    <p className="mt-1 text-sm text-destructive">
                        {errors.rating}
                    </p>
                )}
            </fieldset>
            <Field
                label="Headline"
                htmlFor="r-title"
                error={errors.title}
                required
            >
                <Input
                    id="r-title"
                    value={data.title}
                    onChange={(event) => setData('title', event.target.value)}
                />
            </Field>
            <Field
                label="Your review"
                htmlFor="r-body"
                error={errors.body}
                required
            >
                <Textarea
                    id="r-body"
                    rows={5}
                    value={data.body}
                    onChange={(event) => setData('body', event.target.value)}
                />
            </Field>
            <Button type="submit" variant="gold" disabled={form.processing}>
                Submit review
            </Button>
        </form>
    );
}
