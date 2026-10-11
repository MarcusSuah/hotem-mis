import { Head, router, useForm } from '@inertiajs/react';
import {
    BedDouble,
    CalendarCheck,
    Check,
    Search,
    Star,
    UserPlus,
    Users,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { Field } from '@/components/admin/field';
import { FormErrorSummary } from '@/components/admin/form-error-summary';
import { PageHeader } from '@/components/admin/page-header';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { notifyValidationFailed } from '@/lib/form';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';
import type { MoneyValue } from '@/types';

type Criteria = {
    check_in_date: string;
    check_out_date: string;
    adults: number;
    children: number;
    currency: string;
    rate_plan_id: number | null;
};

type Quote = {
    nights: { date: string; amount: MoneyValue }[];
    room_total: MoneyValue;
    service_charge: MoneyValue;
    tax: MoneyValue;
    total: MoneyValue;
    deposit_due: MoneyValue;
    exchange_rate: string | null;
    base_currency: string;
    tax_rate: string;
    service_charge_rate: string;
};

type AvailableType = {
    id: number;
    name: string;
    image: string | null;
    max_occupancy: number;
    bed_type: string;
    available: number;
    quote: Quote | null;
    rooms: { id: number; number: string; floor: number }[];
};

type Availability = {
    nights: number;
    types: AvailableType[];
    error: string | null;
} | null;

type GuestResult = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    is_vip: boolean;
};

type Props = {
    criteria: Criteria;
    searchErrors: Record<string, string[]>;
    availability: Availability;
    guestResults: GuestResult[];
    ratePlans: {
        id: number;
        name: string;
        code: string;
        is_refundable: boolean;
        includes_breakfast: boolean;
        min_nights: number;
    }[];
    sources: { value: string; label: string }[];
    currencies: string[];
    can: { complimentary: boolean };
};

type BookingForm = {
    check_in_date: string;
    check_out_date: string;
    adults: string;
    children: string;
    currency: string;
    rate_plan_id: string;
    room_type_id: string;
    room_id: string;
    source: string;
    guest_id: string;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    country_code: string;
    special_requests: string;
    confirm: boolean;
    complimentary: boolean;
    idempotency_key: string;
};

export default function CreateReservation({
    criteria,
    searchErrors,
    availability,
    guestResults,
    ratePlans,
    sources,
    currencies,
    can,
}: Props) {
    const idempotencyKey = useMemo(() => crypto.randomUUID(), []);
    const form = useForm<BookingForm>({
        check_in_date: criteria.check_in_date,
        check_out_date: criteria.check_out_date,
        adults: String(criteria.adults),
        children: String(criteria.children),
        currency: criteria.currency,
        rate_plan_id: criteria.rate_plan_id
            ? String(criteria.rate_plan_id)
            : '',
        room_type_id: '',
        room_id: '',
        source: 'phone',
        guest_id: '',
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        country_code: '',
        special_requests: '',
        confirm: true,
        complimentary: false,
        idempotency_key: idempotencyKey,
    });
    const { data, setData, errors } = form;
    const [searching, setSearching] = useState(false);
    const [guestQuery, setGuestQuery] = useState('');
    const [selectedGuest, setSelectedGuest] = useState<GuestResult | null>(
        null,
    );
    const [newGuest, setNewGuest] = useState(false);
    const guestTimer = useRef<ReturnType<typeof setTimeout> | null>(null);

    const searched = useMemo(
        () => ({
            check_in_date: criteria.check_in_date,
            check_out_date: criteria.check_out_date,
            adults: criteria.adults,
            children: criteria.children,
            currency: criteria.currency,
            rate_plan_id: criteria.rate_plan_id,
        }),
        [criteria],
    );

    const searchIsCurrent =
        data.check_in_date === searched.check_in_date &&
        data.check_out_date === searched.check_out_date &&
        Number(data.adults) === searched.adults &&
        Number(data.children) === searched.children &&
        data.currency === searched.currency &&
        (data.rate_plan_id || '') ===
            (searched.rate_plan_id ? String(searched.rate_plan_id) : '');

    const selectedType =
        availability?.types.find(
            (type) => String(type.id) === data.room_type_id,
        ) ?? null;

    useEffect(() => {
        if (!searchIsCurrent) {
            setData((current) => ({
                ...current,
                room_type_id: '',
                room_id: '',
            }));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [searchIsCurrent]);

    function searchAvailability() {
        router.get(
            admin.reservations.create.url(),
            {
                check_in_date: data.check_in_date,
                check_out_date: data.check_out_date,
                adults: data.adults,
                children: data.children,
                currency: data.currency,
                rate_plan_id: data.rate_plan_id,
            },
            {
                only: ['availability', 'criteria', 'searchErrors'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onStart: () => setSearching(true),
                onFinish: () => setSearching(false),
            },
        );
    }

    function searchGuests(term: string) {
        setGuestQuery(term);

        if (guestTimer.current) {
            clearTimeout(guestTimer.current);
        }

        guestTimer.current = setTimeout(() => {
            router.get(
                admin.reservations.create.url(),
                { ...searched, guest_search: term },
                {
                    only: ['guestResults'],
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                },
            );
        }, 300);
    }

    function chooseGuest(guest: GuestResult) {
        setSelectedGuest(guest);
        setNewGuest(false);
        setData('guest_id', String(guest.id));
    }

    function submit() {
        form.post(admin.reservations.store.url(), {
            preserveScroll: true,
            onError: notifyValidationFailed,
        });
    }

    const canSubmit =
        searchIsCurrent &&
        selectedType !== null &&
        (data.guest_id !== '' || newGuest);

    return (
        <>
            <Head title="New reservation" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="New reservation"
                    description="Search availability, choose a room, then attach the guest."
                />

                <FormErrorSummary errors={errors} />

                <div className="grid gap-6 xl:grid-cols-[1fr_360px]">
                    <div className="space-y-6">
                        <Step number={1} title="Stay details">
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
                                <Field
                                    label="Check-in"
                                    htmlFor="check-in"
                                    error={
                                        errors.check_in_date ??
                                        searchErrors.check_in_date?.[0]
                                    }
                                    required
                                    className="lg:col-span-2"
                                >
                                    <Input
                                        id="check-in"
                                        type="date"
                                        value={data.check_in_date}
                                        onChange={(event) =>
                                            setData(
                                                'check_in_date',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Check-out"
                                    htmlFor="check-out"
                                    error={
                                        errors.check_out_date ??
                                        searchErrors.check_out_date?.[0]
                                    }
                                    required
                                    className="lg:col-span-2"
                                >
                                    <Input
                                        id="check-out"
                                        type="date"
                                        min={data.check_in_date}
                                        value={data.check_out_date}
                                        onChange={(event) =>
                                            setData(
                                                'check_out_date',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Adults"
                                    htmlFor="adults"
                                    error={errors.adults}
                                    required
                                >
                                    <Input
                                        id="adults"
                                        type="number"
                                        min={1}
                                        max={20}
                                        value={data.adults}
                                        onChange={(event) =>
                                            setData(
                                                'adults',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Children"
                                    htmlFor="children"
                                    error={errors.children}
                                    required
                                >
                                    <Input
                                        id="children"
                                        type="number"
                                        min={0}
                                        max={20}
                                        value={data.children}
                                        onChange={(event) =>
                                            setData(
                                                'children',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    label="Rate plan"
                                    htmlFor="rate-plan"
                                    error={errors.rate_plan_id}
                                    className="lg:col-span-3"
                                >
                                    <Select
                                        value={data.rate_plan_id}
                                        onValueChange={(value) =>
                                            setData('rate_plan_id', value)
                                        }
                                    >
                                        <SelectTrigger id="rate-plan">
                                            <SelectValue placeholder="Default rate" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {ratePlans.map((plan) => (
                                                <SelectItem
                                                    key={plan.id}
                                                    value={String(plan.id)}
                                                >
                                                    {plan.name}
                                                    {!plan.is_refundable &&
                                                        ' · non-refundable'}
                                                    {plan.includes_breakfast &&
                                                        ' · breakfast'}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>
                                <Field
                                    label="Charge in"
                                    htmlFor="currency"
                                    error={errors.currency}
                                    required
                                >
                                    <Select
                                        value={data.currency}
                                        onValueChange={(value) =>
                                            setData('currency', value)
                                        }
                                    >
                                        <SelectTrigger id="currency">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {currencies.map((currency) => (
                                                <SelectItem
                                                    key={currency}
                                                    value={currency}
                                                >
                                                    {currency}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>
                                <div className="flex items-end lg:col-span-2">
                                    <Button
                                        type="button"
                                        className="w-full"
                                        variant={
                                            searchIsCurrent
                                                ? 'outline'
                                                : 'default'
                                        }
                                        onClick={searchAvailability}
                                        disabled={searching}
                                    >
                                        {searching ? <Spinner /> : <Search />}
                                        {searchIsCurrent
                                            ? 'Refresh availability'
                                            : 'Check availability'}
                                    </Button>
                                </div>
                            </div>
                        </Step>

                        <Step number={2} title="Room">
                            {searching ? (
                                <div className="grid gap-3 md:grid-cols-2">
                                    {[0, 1, 2, 3].map((index) => (
                                        <Skeleton
                                            key={index}
                                            className="h-36 rounded-xl"
                                        />
                                    ))}
                                </div>
                            ) : !searchIsCurrent ? (
                                <p className="text-sm text-muted-foreground">
                                    Check availability to see rooms for these
                                    dates.
                                </p>
                            ) : availability?.error ? (
                                <p className="text-sm text-destructive">
                                    {availability.error}
                                </p>
                            ) : !availability ||
                              availability.types.length === 0 ? (
                                <p className="text-sm text-muted-foreground">
                                    No room types can accommodate this party.
                                </p>
                            ) : (
                                <div className="space-y-4">
                                    <div className="grid gap-3 md:grid-cols-2">
                                        {availability.types.map((type) => {
                                            const selected =
                                                String(type.id) ===
                                                data.room_type_id;
                                            const soldOut =
                                                type.available === 0;

                                            return (
                                                <button
                                                    key={type.id}
                                                    type="button"
                                                    disabled={soldOut}
                                                    onClick={() =>
                                                        setData((current) => ({
                                                            ...current,
                                                            room_type_id:
                                                                String(type.id),
                                                            room_id: '',
                                                        }))
                                                    }
                                                    aria-pressed={selected}
                                                    className={cn(
                                                        'flex gap-3 rounded-xl border p-3 text-left transition-colors',
                                                        selected
                                                            ? 'border-primary bg-secondary/60 ring-2 ring-primary/20'
                                                            : 'hover:border-primary/40',
                                                        soldOut &&
                                                            'cursor-not-allowed opacity-50',
                                                    )}
                                                >
                                                    <div className="size-20 shrink-0 overflow-hidden rounded-lg bg-sage dark:bg-muted">
                                                        {type.image ? (
                                                            <img
                                                                src={type.image}
                                                                alt=""
                                                                className="size-full object-cover"
                                                            />
                                                        ) : (
                                                            <BedDouble className="m-auto mt-6 size-8 text-primary/40" />
                                                        )}
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <div className="flex items-start justify-between gap-2">
                                                            <p className="font-semibold">
                                                                {type.name}
                                                            </p>
                                                            {selected && (
                                                                <Check className="size-4 text-primary" />
                                                            )}
                                                        </div>
                                                        <p className="text-xs text-muted-foreground">
                                                            {type.bed_type} · up
                                                            to{' '}
                                                            {type.max_occupancy}{' '}
                                                            guests
                                                        </p>
                                                        <p
                                                            className={cn(
                                                                'mt-1 text-xs font-medium',
                                                                soldOut
                                                                    ? 'text-destructive'
                                                                    : 'text-success',
                                                            )}
                                                        >
                                                            {soldOut
                                                                ? 'Sold out'
                                                                : `${type.available} available`}
                                                        </p>
                                                        {type.quote && (
                                                            <p className="mt-1 font-display text-lg font-semibold">
                                                                {
                                                                    type.quote
                                                                        .total
                                                                        .formatted
                                                                }
                                                                <span className="ml-1 font-sans text-xs font-normal text-muted-foreground">
                                                                    for{' '}
                                                                    {
                                                                        availability.nights
                                                                    }{' '}
                                                                    night
                                                                    {availability.nights ===
                                                                    1
                                                                        ? ''
                                                                        : 's'}
                                                                </span>
                                                            </p>
                                                        )}
                                                    </div>
                                                </button>
                                            );
                                        })}
                                    </div>
                                    <InputError message={errors.room_type_id} />

                                    {selectedType && (
                                        <div className="space-y-2">
                                            <Label>
                                                Assign a specific room
                                                (optional)
                                            </Label>
                                            <div className="flex flex-wrap gap-2">
                                                <RoomChip
                                                    label="Auto-assign"
                                                    active={data.room_id === ''}
                                                    onClick={() =>
                                                        setData('room_id', '')
                                                    }
                                                />
                                                {selectedType.rooms.map(
                                                    (room) => (
                                                        <RoomChip
                                                            key={room.id}
                                                            label={room.number}
                                                            active={
                                                                data.room_id ===
                                                                String(room.id)
                                                            }
                                                            onClick={() =>
                                                                setData(
                                                                    'room_id',
                                                                    String(
                                                                        room.id,
                                                                    ),
                                                                )
                                                            }
                                                        />
                                                    ),
                                                )}
                                            </div>
                                            <InputError
                                                message={errors.room_id}
                                            />
                                        </div>
                                    )}
                                </div>
                            )}
                        </Step>

                        <Step number={3} title="Guest">
                            {selectedGuest && !newGuest ? (
                                <div className="flex items-center justify-between rounded-lg border bg-secondary/40 p-3">
                                    <div>
                                        <p className="flex items-center gap-1 font-medium">
                                            {selectedGuest.name}
                                            {selectedGuest.is_vip && (
                                                <Star className="size-3.5 fill-highlight text-highlight" />
                                            )}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {[
                                                selectedGuest.email,
                                                selectedGuest.phone,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => {
                                            setSelectedGuest(null);
                                            setData('guest_id', '');
                                        }}
                                    >
                                        Change
                                    </Button>
                                </div>
                            ) : newGuest ? (
                                <div className="space-y-4">
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Field
                                            label="First name"
                                            htmlFor="first-name"
                                            error={errors.first_name}
                                            required
                                        >
                                            <Input
                                                id="first-name"
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
                                            htmlFor="last-name"
                                            error={errors.last_name}
                                            required
                                        >
                                            <Input
                                                id="last-name"
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
                                            htmlFor="guest-email"
                                            error={errors.email}
                                        >
                                            <Input
                                                id="guest-email"
                                                type="email"
                                                value={data.email}
                                                onChange={(event) =>
                                                    setData(
                                                        'email',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                        <Field
                                            label="Phone"
                                            htmlFor="guest-phone"
                                            error={errors.phone}
                                        >
                                            <Input
                                                id="guest-phone"
                                                type="tel"
                                                value={data.phone}
                                                onChange={(event) =>
                                                    setData(
                                                        'phone',
                                                        event.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => setNewGuest(false)}
                                    >
                                        Search existing guests instead
                                    </Button>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    <div className="relative">
                                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            aria-label="Search guests"
                                            placeholder="Search guests by name, email or phone"
                                            className="pl-9"
                                            value={guestQuery}
                                            onChange={(event) =>
                                                searchGuests(event.target.value)
                                            }
                                        />
                                    </div>
                                    {guestQuery !== '' && (
                                        <ul className="divide-y rounded-lg border">
                                            {guestResults.length === 0 ? (
                                                <li className="p-3 text-sm text-muted-foreground">
                                                    No matching guests.
                                                </li>
                                            ) : (
                                                guestResults.map((guest) => (
                                                    <li key={guest.id}>
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                chooseGuest(
                                                                    guest,
                                                                )
                                                            }
                                                            className="flex w-full items-center justify-between p-3 text-left hover:bg-muted/50"
                                                        >
                                                            <span>
                                                                <span className="font-medium">
                                                                    {guest.name}
                                                                </span>
                                                                <span className="block text-xs text-muted-foreground">
                                                                    {[
                                                                        guest.email,
                                                                        guest.phone,
                                                                    ]
                                                                        .filter(
                                                                            Boolean,
                                                                        )
                                                                        .join(
                                                                            ' · ',
                                                                        )}
                                                                </span>
                                                            </span>
                                                            {guest.is_vip && (
                                                                <Badge variant="gold">
                                                                    VIP
                                                                </Badge>
                                                            )}
                                                        </button>
                                                    </li>
                                                ))
                                            )}
                                        </ul>
                                    )}
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => {
                                            setNewGuest(true);
                                            setData('guest_id', '');
                                        }}
                                    >
                                        <UserPlus />
                                        New guest
                                    </Button>
                                    <InputError message={errors.guest_id} />
                                </div>
                            )}
                        </Step>

                        <Step number={4} title="Booking details">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    label="Channel"
                                    htmlFor="source"
                                    error={errors.source}
                                    required
                                >
                                    <Select
                                        value={data.source}
                                        onValueChange={(value) =>
                                            setData('source', value)
                                        }
                                    >
                                        <SelectTrigger id="source">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {sources.map((source) => (
                                                <SelectItem
                                                    key={source.value}
                                                    value={source.value}
                                                >
                                                    {source.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </Field>
                                <Field
                                    label="Special requests"
                                    htmlFor="requests"
                                    error={errors.special_requests}
                                    className="sm:col-span-2"
                                >
                                    <Textarea
                                        id="requests"
                                        rows={3}
                                        value={data.special_requests}
                                        onChange={(event) =>
                                            setData(
                                                'special_requests',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                            <div className="mt-4 flex flex-col gap-3">
                                <label
                                    htmlFor="confirm-now"
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <Checkbox
                                        id="confirm-now"
                                        checked={data.confirm}
                                        onCheckedChange={(checked) =>
                                            setData('confirm', checked === true)
                                        }
                                    />
                                    Confirm immediately (otherwise held as
                                    pending until confirmed)
                                </label>
                                {can.complimentary && (
                                    <label
                                        htmlFor="complimentary"
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            id="complimentary"
                                            checked={data.complimentary}
                                            onCheckedChange={(checked) =>
                                                setData(
                                                    'complimentary',
                                                    checked === true,
                                                )
                                            }
                                        />
                                        Complimentary stay (no room charge)
                                    </label>
                                )}
                                <InputError message={errors.complimentary} />
                            </div>
                        </Step>
                    </div>

                    <aside className="xl:sticky xl:top-4 xl:self-start">
                        <div className="overflow-hidden rounded-xl border bg-card shadow-xs">
                            <div className="bg-teal p-5 text-ivory">
                                <p className="text-xs tracking-[0.2em] text-gold uppercase">
                                    Summary
                                </p>
                                <p className="mt-1 font-display text-2xl">
                                    {selectedType?.name ?? 'Choose a room'}
                                </p>
                                <p className="mt-2 flex items-center gap-2 text-sm text-ivory/80">
                                    <CalendarCheck className="size-4" />
                                    {formatDate(data.check_in_date)} →{' '}
                                    {formatDate(data.check_out_date)}
                                </p>
                                <p className="mt-1 flex items-center gap-2 text-sm text-ivory/80">
                                    <Users className="size-4" />
                                    {data.adults} adult
                                    {data.adults === '1' ? '' : 's'}
                                    {Number(data.children) > 0 &&
                                        `, ${data.children} children`}
                                </p>
                            </div>
                            {selectedType?.quote ? (
                                <QuoteBreakdown
                                    quote={
                                        data.complimentary
                                            ? null
                                            : selectedType.quote
                                    }
                                />
                            ) : (
                                <p className="p-5 text-sm text-muted-foreground">
                                    The price appears once a room type is
                                    selected.
                                </p>
                            )}
                            <div className="border-t p-5">
                                <Button
                                    className="w-full"
                                    size="lg"
                                    disabled={!canSubmit || form.processing}
                                    onClick={submit}
                                >
                                    {form.processing && <Spinner />}
                                    Create reservation
                                </Button>
                                {!canSubmit && (
                                    <p className="mt-2 text-center text-xs text-muted-foreground">
                                        {!searchIsCurrent
                                            ? 'Check availability for the current dates.'
                                            : !selectedType
                                              ? 'Select a room type.'
                                              : 'Select or add the guest.'}
                                    </p>
                                )}
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </>
    );
}

function Step({
    number,
    title,
    children,
}: {
    number: number;
    title: string;
    children: React.ReactNode;
}) {
    return (
        <section className="rounded-xl border bg-card p-5 shadow-xs">
            <h2 className="mb-4 flex items-center gap-3 font-display text-xl font-semibold">
                <span className="flex size-7 items-center justify-center rounded-full bg-primary font-sans text-sm text-primary-foreground">
                    {number}
                </span>
                {title}
            </h2>
            {children}
        </section>
    );
}

function RoomChip({
    label,
    active,
    onClick,
}: {
    label: string;
    active: boolean;
    onClick: () => void;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={active}
            className={cn(
                'rounded-md border px-3 py-1.5 text-sm transition-colors',
                active
                    ? 'border-primary bg-primary text-primary-foreground'
                    : 'hover:border-primary/50',
            )}
        >
            {label}
        </button>
    );
}

function QuoteBreakdown({ quote }: { quote: Quote | null }) {
    if (quote === null) {
        return (
            <p className="p-5 text-sm font-medium">
                Complimentary — no charge.
            </p>
        );
    }

    return (
        <dl className="space-y-2 p-5 text-sm">
            <div className="max-h-40 space-y-1 overflow-y-auto pr-1">
                {quote.nights.map((night) => (
                    <div
                        key={night.date}
                        className="flex justify-between text-muted-foreground"
                    >
                        <dt>{formatDate(night.date)}</dt>
                        <dd>{night.amount.formatted}</dd>
                    </div>
                ))}
            </div>
            <div className="flex justify-between border-t pt-2">
                <dt>Room total</dt>
                <dd>{quote.room_total.formatted}</dd>
            </div>
            <div className="flex justify-between">
                <dt>Service charge ({Number(quote.service_charge_rate)}%)</dt>
                <dd>{quote.service_charge.formatted}</dd>
            </div>
            <div className="flex justify-between">
                <dt>Tax ({Number(quote.tax_rate)}%)</dt>
                <dd>{quote.tax.formatted}</dd>
            </div>
            <div className="flex justify-between border-t pt-2 text-base font-semibold">
                <dt>Total</dt>
                <dd>{quote.total.formatted}</dd>
            </div>
            <div className="flex justify-between text-xs text-muted-foreground">
                <dt>Deposit due</dt>
                <dd>{quote.deposit_due.formatted}</dd>
            </div>
            {quote.exchange_rate && (
                <p className="text-xs text-muted-foreground">
                    Converted from {quote.base_currency} at 1{' '}
                    {quote.base_currency} ={' '}
                    {Number(quote.exchange_rate).toLocaleString()}{' '}
                    {quote.total.currency}.
                </p>
            )}
        </dl>
    );
}

CreateReservation.layout = {
    breadcrumbs: [
        { title: 'Reservations', href: admin.reservations.index() },
        { title: 'New reservation', href: admin.reservations.create() },
    ],
};
