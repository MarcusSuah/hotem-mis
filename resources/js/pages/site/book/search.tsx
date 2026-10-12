import { Link, usePage } from '@inertiajs/react';
import { BadgePercent, CalendarSearch } from 'lucide-react';
import { BookingSearchForm } from '@/components/site/booking-search-form';
import { criteriaQuery } from '@/components/site/booking-types';
import type {
    BookingCriteria,
    StayQuote,
} from '@/components/site/booking-types';
import { Seo } from '@/components/site/seo';
import { CoverImage, EmptyNotice, PageHero } from '@/components/site/site-ui';
import type { RoomCardData } from '@/components/site/site-ui';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import site from '@/routes/site';

type Result = { room: RoomCardData; available: number; quote: StayQuote };

type Props = {
    criteria: BookingCriteria;
    offer: { slug: string; title: string; summary: string | null } | null;
    results: Result[] | null;
    limits: { max_nights: number; max_advance_days: number };
};

export default function BookingSearch({
    criteria,
    offer,
    results,
    limits,
}: Props) {
    const { errors } = usePage().props as {
        errors: Partial<Record<string, string>>;
    };
    const nights = results?.[0]?.quote.nights.length ?? 0;

    return (
        <>
            <Seo
                title="Book your stay"
                description="Check live availability and book directly with the hotel."
            />
            <PageHero
                eyebrow="Reservations"
                title="Book your stay"
                lead={`Book directly for our best available rate. Stays of up to ${limits.max_nights} nights can be booked online.`}
            />

            <div className="mx-auto max-w-6xl space-y-8 px-4 py-10 sm:px-6">
                {offer && (
                    <div className="flex items-start gap-3 rounded-xl border border-highlight/60 bg-highlight/10 p-4">
                        <BadgePercent className="mt-0.5 size-5 text-highlight-foreground dark:text-highlight" />
                        <div>
                            <p className="font-medium">
                                Booking the “{offer.title}” offer
                            </p>
                            {offer.summary && (
                                <p className="text-sm text-muted-foreground">
                                    {offer.summary}
                                </p>
                            )}
                        </div>
                    </div>
                )}

                <BookingSearchForm
                    key={JSON.stringify(criteria)}
                    initial={criteria}
                    errors={errors}
                />

                {results !== null && (
                    <section aria-live="polite" className="space-y-4">
                        <h2 className="font-display text-3xl font-semibold text-primary dark:text-foreground">
                            {results.length > 0
                                ? `${results.length} ${results.length === 1 ? 'room type' : 'room types'} available`
                                : 'No rooms available'}
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            {formatDate(criteria.check_in_date)} –{' '}
                            {formatDate(criteria.check_out_date)} · {nights}{' '}
                            {nights === 1 ? 'night' : 'nights'} ·{' '}
                            {criteria.adults}{' '}
                            {criteria.adults === 1 ? 'adult' : 'adults'}
                            {criteria.children > 0 &&
                                `, ${criteria.children} ${criteria.children === 1 ? 'child' : 'children'}`}
                        </p>

                        {results.length === 0 ? (
                            <EmptyNotice>
                                <CalendarSearch className="mx-auto mb-2 size-6" />
                                We’re fully booked for those dates or no room
                                fits your party. Try different dates, or contact
                                us and we’ll do our best to help.
                            </EmptyNotice>
                        ) : (
                            <ul className="space-y-4">
                                {results.map(({ room, available, quote }) => (
                                    <li
                                        key={room.slug}
                                        className="grid overflow-hidden rounded-xl border bg-card shadow-xs md:grid-cols-[16rem_1fr_auto]"
                                    >
                                        <div className="aspect-[4/3] md:aspect-auto">
                                            <CoverImage
                                                src={room.image_url}
                                                alt={room.image_alt}
                                            />
                                        </div>
                                        <div className="space-y-2 p-5">
                                            <h3 className="font-display text-2xl font-semibold text-primary dark:text-foreground">
                                                <Link
                                                    href={site.rooms.show(
                                                        room.slug,
                                                    )}
                                                >
                                                    {room.name}
                                                </Link>
                                            </h3>
                                            {room.short_description && (
                                                <p className="text-sm text-muted-foreground">
                                                    {room.short_description}
                                                </p>
                                            )}
                                            <p className="text-xs text-muted-foreground">
                                                Sleeps {room.max_occupancy} ·{' '}
                                                {room.bed}
                                            </p>
                                            {available <= 3 && (
                                                <p className="text-sm font-medium text-destructive">
                                                    Only {available} left for
                                                    these dates
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex flex-col items-start justify-center gap-2 border-t p-5 md:items-end md:border-t-0 md:border-l">
                                            <p className="text-2xl font-semibold text-primary dark:text-foreground">
                                                {quote.total.formatted}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Total incl. taxes & service
                                            </p>
                                            <Button asChild variant="gold">
                                                <Link
                                                    href={site.booking.details(
                                                        room.slug,
                                                        {
                                                            query: criteriaQuery(
                                                                criteria,
                                                            ),
                                                        },
                                                    )}
                                                >
                                                    Select
                                                </Link>
                                            </Button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                )}
            </div>
        </>
    );
}
