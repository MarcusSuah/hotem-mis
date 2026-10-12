import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Info, ShieldCheck } from 'lucide-react';
import { useState } from 'react';
import { Field } from '@/components/admin/field';
import { FormErrorSummary } from '@/components/admin/form-error-summary';
import { criteriaQuery } from '@/components/site/booking-types';
import type {
    BookingCriteria,
    StayQuote,
} from '@/components/site/booking-types';
import { Seo } from '@/components/site/seo';
import { CoverImage, EmptyNotice } from '@/components/site/site-ui';
import type { RoomCardData } from '@/components/site/site-ui';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { notifyValidationFailed } from '@/lib/form';
import { formatDate } from '@/lib/format';
import site from '@/routes/site';

type Props = {
    criteria: BookingCriteria;
    room: RoomCardData;
    quote: StayQuote | null;
    offer: { slug: string; title: string } | null;
    policy: string | null;
    prefill: {
        first_name: string;
        last_name: string;
        email: string;
        phone: string;
        country_code: string;
    };
    note: string | null;
    holdHours: number;
};

export default function BookingDetails({
    criteria,
    room,
    quote,
    offer,
    policy,
    prefill,
    note,
    holdHours,
}: Props) {
    const [idempotencyKey] = useState(() => crypto.randomUUID());
    const form = useForm({
        ...criteriaQuery(criteria),
        room_type: room.slug,
        ...prefill,
        special_requests: '',
        accept_terms: false,
        website: '',
        idempotency_key: idempotencyKey,
    });
    const { data, setData, errors } = form;
    const backToResults = site.booking.availability({
        query: criteriaQuery(criteria),
    });

    return (
        <>
            <Seo title={`Book ${room.name}`} />
            <div className="mx-auto max-w-6xl px-4 py-10 sm:px-6">
                <Link
                    href={backToResults}
                    className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" /> Back to results
                </Link>
                <h1 className="mt-3 font-display text-4xl font-semibold text-primary dark:text-foreground">
                    Your details
                </h1>

                {quote === null ? (
                    <EmptyNotice>
                        {room.name} is no longer available for these dates.{' '}
                        <Link
                            href={backToResults}
                            className="text-primary underline"
                        >
                            See other rooms
                        </Link>
                        .
                    </EmptyNotice>
                ) : (
                    <div className="mt-8 grid gap-8 lg:grid-cols-[3fr_2fr]">
                        <form
                            className="space-y-5 rounded-xl border bg-card p-6 shadow-xs"
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.post(site.booking.store.url(), {
                                    onError: notifyValidationFailed,
                                });
                            }}
                        >
                            <FormErrorSummary errors={errors} />
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="First name"
                                    htmlFor="b-first"
                                    error={errors.first_name}
                                    required
                                >
                                    <Input
                                        id="b-first"
                                        autoComplete="given-name"
                                        value={data.first_name}
                                        onChange={(event) =>
                                            setData(
                                                'first_name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Last name"
                                    htmlFor="b-last"
                                    error={errors.last_name}
                                    required
                                >
                                    <Input
                                        id="b-last"
                                        autoComplete="family-name"
                                        value={data.last_name}
                                        onChange={(event) =>
                                            setData(
                                                'last_name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Email"
                                    htmlFor="b-email"
                                    error={errors.email}
                                    required
                                >
                                    <Input
                                        id="b-email"
                                        type="email"
                                        autoComplete="email"
                                        value={data.email}
                                        onChange={(event) =>
                                            setData('email', event.target.value)
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Phone"
                                    htmlFor="b-phone"
                                    error={errors.phone}
                                    required
                                >
                                    <Input
                                        id="b-phone"
                                        type="tel"
                                        autoComplete="tel"
                                        value={data.phone}
                                        onChange={(event) =>
                                            setData('phone', event.target.value)
                                        }
                                    />
                                </Field>
                            </div>
                            <Field
                                label="Special requests"
                                htmlFor="b-requests"
                                error={errors.special_requests}
                            >
                                <Textarea
                                    id="b-requests"
                                    rows={3}
                                    placeholder="Arrival time, dietary needs, celebrations…"
                                    value={data.special_requests}
                                    onChange={(event) =>
                                        setData(
                                            'special_requests',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <div className="hidden" aria-hidden>
                                <label htmlFor="b-website">
                                    Leave this field empty
                                </label>
                                <input
                                    id="b-website"
                                    tabIndex={-1}
                                    autoComplete="off"
                                    value={data.website}
                                    onChange={(event) =>
                                        setData('website', event.target.value)
                                    }
                                />
                            </div>
                            {policy && (
                                <p className="flex gap-2 rounded-lg bg-muted/60 p-3 text-sm">
                                    <ShieldCheck className="mt-0.5 size-4 shrink-0 text-highlight" />
                                    <span>
                                        <span className="font-medium">
                                            Cancellation:{' '}
                                        </span>
                                        {policy}
                                    </span>
                                </p>
                            )}
                            <label className="flex items-start gap-3 text-sm">
                                <Checkbox
                                    id="b-terms"
                                    checked={data.accept_terms}
                                    onCheckedChange={(checked) =>
                                        setData(
                                            'accept_terms',
                                            checked === true,
                                        )
                                    }
                                    className="mt-0.5"
                                />
                                <span>
                                    I accept the booking and cancellation terms
                                    above.
                                </span>
                            </label>
                            {errors.accept_terms && (
                                <p className="text-sm text-destructive">
                                    {errors.accept_terms}
                                </p>
                            )}
                            <Button
                                type="submit"
                                variant="gold"
                                size="lg"
                                className="w-full"
                                disabled={form.processing}
                            >
                                Request booking
                            </Button>
                            <p className="flex gap-2 text-xs text-muted-foreground">
                                <Info className="size-4 shrink-0" />
                                {note ??
                                    'Your reservation is held while our team confirms it.'}{' '}
                                We hold your room for {holdHours} hours. No card
                                details are taken online.
                            </p>
                        </form>

                        <aside className="space-y-4">
                            <div className="overflow-hidden rounded-xl border bg-card shadow-xs">
                                <div className="aspect-[16/9]">
                                    <CoverImage
                                        src={room.image_url}
                                        alt={room.image_alt}
                                    />
                                </div>
                                <div className="space-y-3 p-5 text-sm">
                                    <h2 className="font-display text-2xl font-semibold">
                                        {room.name}
                                    </h2>
                                    {offer && (
                                        <p className="text-highlight-foreground dark:text-highlight">
                                            Offer: {offer.title}
                                        </p>
                                    )}
                                    <p>
                                        {formatDate(criteria.check_in_date)} –{' '}
                                        {formatDate(criteria.check_out_date)}
                                        <span className="block text-muted-foreground">
                                            {quote.nights.length}{' '}
                                            {quote.nights.length === 1
                                                ? 'night'
                                                : 'nights'}{' '}
                                            · {criteria.adults} adults
                                            {criteria.children > 0 &&
                                                ` · ${criteria.children} children`}
                                        </span>
                                    </p>
                                    <dl className="space-y-1 border-t pt-3">
                                        {quote.nights.map((night) => (
                                            <div
                                                key={night.date}
                                                className="flex justify-between text-muted-foreground"
                                            >
                                                <dt>
                                                    {formatDate(night.date)}
                                                </dt>
                                                <dd>
                                                    {night.amount.formatted}
                                                </dd>
                                            </div>
                                        ))}
                                        <div className="flex justify-between border-t pt-2">
                                            <dt>Room total</dt>
                                            <dd>
                                                {quote.room_total.formatted}
                                            </dd>
                                        </div>
                                        {quote.service_charge.minor > 0 && (
                                            <div className="flex justify-between">
                                                <dt>
                                                    Service charge (
                                                    {Number(
                                                        quote.service_charge_rate,
                                                    )}
                                                    %)
                                                </dt>
                                                <dd>
                                                    {
                                                        quote.service_charge
                                                            .formatted
                                                    }
                                                </dd>
                                            </div>
                                        )}
                                        {quote.tax.minor > 0 && (
                                            <div className="flex justify-between">
                                                <dt>
                                                    Tax (
                                                    {Number(quote.tax_rate)}%)
                                                </dt>
                                                <dd>{quote.tax.formatted}</dd>
                                            </div>
                                        )}
                                        <div className="flex justify-between border-t pt-2 text-base font-semibold">
                                            <dt>Total</dt>
                                            <dd>{quote.total.formatted}</dd>
                                        </div>
                                        {quote.deposit_due.minor > 0 && (
                                            <div className="flex justify-between text-muted-foreground">
                                                <dt>Deposit to confirm</dt>
                                                <dd>
                                                    {
                                                        quote.deposit_due
                                                            .formatted
                                                    }
                                                </dd>
                                            </div>
                                        )}
                                    </dl>
                                    {quote.exchange_rate && (
                                        <p className="text-xs text-muted-foreground">
                                            Converted from{' '}
                                            {quote.base_total.formatted} at 1{' '}
                                            {quote.base_currency} ={' '}
                                            {Number(quote.exchange_rate)}{' '}
                                            {quote.currency}. This rate is
                                            locked for your booking.
                                        </p>
                                    )}
                                </div>
                            </div>
                        </aside>
                    </div>
                )}
            </div>
        </>
    );
}
