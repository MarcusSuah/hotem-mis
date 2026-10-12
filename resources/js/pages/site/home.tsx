import { Link } from '@inertiajs/react';
import { ArrowRight, ConciergeBell } from 'lucide-react';
import { BookingSearchForm } from '@/components/site/booking-search-form';
import { Seo } from '@/components/site/seo';
import {
    CoverImage,
    EventCard,
    OfferCard,
    PostCard,
    Prose,
    ReviewCard,
    RoomCard,
    SectionHeading,
    Stars,
} from '@/components/site/site-ui';
import type {
    EventCardData,
    OfferCardData,
    PostCardData,
    ReviewData,
    RoomCardData,
} from '@/components/site/site-ui';
import { Button } from '@/components/ui/button';
import site from '@/routes/site';

type Props = {
    hero: {
        eyebrow: string | null;
        title: string | null;
        subtitle: string | null;
        image_url: string | null;
    };
    about: { title: string | null; html: string };
    rooms: RoomCardData[];
    offers: OfferCardData[];
    events: EventCardData[];
    posts: PostCardData[];
    services: {
        slug: string;
        name: string;
        short_description: string | null;
        unit: string;
        image_url: string | null;
    }[];
    reviews: { average: number; count: number; latest: ReviewData[] };
    gallery: { id: number; url: string; alt: string }[];
};

function SectionLink({ href, children }: { href: string; children: string }) {
    return (
        <Link
            href={href}
            className="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline dark:text-highlight"
        >
            {children} <ArrowRight className="size-4" />
        </Link>
    );
}

export default function Home({
    hero,
    about,
    rooms,
    offers,
    events,
    posts,
    services,
    reviews,
    gallery,
}: Props) {
    return (
        <>
            <Seo description={hero.subtitle} image={hero.image_url} />

            <section className="relative isolate overflow-hidden bg-teal text-ivory">
                {hero.image_url ? (
                    <img
                        src={hero.image_url}
                        alt=""
                        className="absolute inset-0 -z-10 size-full object-cover opacity-40"
                    />
                ) : (
                    <div className="absolute inset-0 -z-10 bg-[radial-gradient(circle_at_top_right,var(--teal-soft),transparent_60%)]" />
                )}
                <div className="mx-auto max-w-7xl px-4 pt-20 pb-28 sm:px-6 md:pt-28 md:pb-36">
                    {hero.eyebrow && (
                        <p className="text-xs font-semibold tracking-[0.3em] text-gold uppercase">
                            {hero.eyebrow}
                        </p>
                    )}
                    <h1 className="mt-4 max-w-3xl font-display text-5xl leading-tight font-semibold md:text-6xl">
                        {hero.title}
                    </h1>
                    {hero.subtitle && (
                        <p className="mt-5 max-w-2xl text-lg text-ivory/80">
                            {hero.subtitle}
                        </p>
                    )}
                </div>
            </section>

            <div className="relative z-10 mx-auto -mt-16 max-w-6xl px-4 sm:px-6">
                <BookingSearchForm variant="hero" />
            </div>

            {about.title && (
                <section className="mx-auto grid max-w-7xl gap-10 px-4 py-20 sm:px-6 md:grid-cols-2 md:items-center">
                    <SectionHeading eyebrow="Welcome" title={about.title} />
                    {about.html ? (
                        <Prose html={about.html} />
                    ) : (
                        <p className="text-muted-foreground">{hero.subtitle}</p>
                    )}
                </section>
            )}

            {rooms.length > 0 && (
                <section className="bg-sage/50 py-20 dark:bg-muted/30">
                    <div className="mx-auto max-w-7xl space-y-10 px-4 sm:px-6">
                        <div className="flex flex-wrap items-end justify-between gap-4">
                            <SectionHeading
                                eyebrow="Stay"
                                title="Rooms & suites"
                                lead="Calm, considered rooms with everything you need to rest well."
                            />
                            <SectionLink href={site.rooms.index.url()}>
                                All rooms
                            </SectionLink>
                        </div>
                        <div className="grid gap-6 md:grid-cols-3">
                            {rooms.map((room) => (
                                <RoomCard key={room.slug} room={room} />
                            ))}
                        </div>
                    </div>
                </section>
            )}

            {offers.length > 0 && (
                <section className="mx-auto max-w-7xl space-y-10 px-4 py-20 sm:px-6">
                    <div className="flex flex-wrap items-end justify-between gap-4">
                        <SectionHeading
                            eyebrow="Offers"
                            title="Packages for your stay"
                        />
                        <SectionLink href={site.offers.index.url()}>
                            All offers
                        </SectionLink>
                    </div>
                    <div className="grid gap-6 md:grid-cols-3">
                        {offers.map((offer) => (
                            <OfferCard key={offer.slug} offer={offer} />
                        ))}
                    </div>
                </section>
            )}

            {services.length > 0 && (
                <section className="bg-teal py-20 text-ivory">
                    <div className="mx-auto max-w-7xl space-y-10 px-4 sm:px-6">
                        <div className="flex flex-wrap items-end justify-between gap-4">
                            <div className="max-w-2xl space-y-3">
                                <p className="text-xs font-semibold tracking-[0.25em] text-gold uppercase">
                                    Experiences
                                </p>
                                <h2 className="font-display text-4xl font-semibold">
                                    More than a place to sleep
                                </h2>
                            </div>
                            <Link
                                href={site.experiences()}
                                className="inline-flex items-center gap-1 text-sm font-medium text-gold hover:underline"
                            >
                                All experiences{' '}
                                <ArrowRight className="size-4" />
                            </Link>
                        </div>
                        <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            {services.map((service) => (
                                <div
                                    key={service.slug}
                                    className="overflow-hidden rounded-xl bg-white/5 ring-1 ring-white/10"
                                >
                                    <div className="aspect-[4/3]">
                                        <CoverImage
                                            src={service.image_url}
                                            alt={service.name}
                                        />
                                    </div>
                                    <div className="space-y-1 p-4">
                                        <p className="flex items-center gap-2 font-display text-xl">
                                            <ConciergeBell className="size-4 text-gold" />{' '}
                                            {service.name}
                                        </p>
                                        {service.short_description && (
                                            <p className="text-sm text-ivory/70">
                                                {service.short_description}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </section>
            )}

            {reviews.count > 0 && (
                <section className="mx-auto max-w-7xl space-y-10 px-4 py-20 sm:px-6">
                    <div className="flex flex-wrap items-end justify-between gap-4">
                        <SectionHeading
                            eyebrow="Guest reviews"
                            title="In our guests’ words"
                            lead={
                                <span className="inline-flex items-center gap-2">
                                    <Stars rating={reviews.average} />{' '}
                                    {reviews.average.toFixed(1)} from{' '}
                                    {reviews.count} verified{' '}
                                    {reviews.count === 1 ? 'stay' : 'stays'}
                                </span>
                            }
                        />
                        <SectionLink href={site.reviews.url()}>
                            Read all reviews
                        </SectionLink>
                    </div>
                    <div className="grid gap-6 md:grid-cols-3">
                        {reviews.latest.map((review) => (
                            <ReviewCard key={review.id} review={review} />
                        ))}
                    </div>
                </section>
            )}

            {events.length > 0 && (
                <section className="bg-sage/50 py-20 dark:bg-muted/30">
                    <div className="mx-auto max-w-7xl space-y-10 px-4 sm:px-6">
                        <div className="flex flex-wrap items-end justify-between gap-4">
                            <SectionHeading
                                eyebrow="What’s on"
                                title="Upcoming events"
                            />
                            <SectionLink href={site.events.index.url()}>
                                All events
                            </SectionLink>
                        </div>
                        <div className="grid gap-6 md:grid-cols-3">
                            {events.map((event) => (
                                <EventCard key={event.slug} event={event} />
                            ))}
                        </div>
                    </div>
                </section>
            )}

            {gallery.length > 0 && (
                <section className="mx-auto max-w-7xl space-y-8 px-4 py-20 sm:px-6">
                    <div className="flex flex-wrap items-end justify-between gap-4">
                        <SectionHeading
                            eyebrow="Gallery"
                            title="A look around"
                        />
                        <SectionLink href={site.gallery.url()}>
                            View the gallery
                        </SectionLink>
                    </div>
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-3">
                        {gallery.map((image) => (
                            <img
                                key={image.id}
                                src={image.url}
                                alt={image.alt}
                                loading="lazy"
                                className="aspect-[4/3] w-full rounded-lg object-cover"
                            />
                        ))}
                    </div>
                </section>
            )}

            {posts.length > 0 && (
                <section className="mx-auto max-w-7xl space-y-10 px-4 pb-20 sm:px-6">
                    <div className="flex flex-wrap items-end justify-between gap-4">
                        <SectionHeading
                            eyebrow="Journal"
                            title="Stories & local guides"
                        />
                        <SectionLink href={site.blog.index.url()}>
                            Read the journal
                        </SectionLink>
                    </div>
                    <div className="grid gap-6 md:grid-cols-3">
                        {posts.map((post) => (
                            <PostCard key={post.slug} post={post} />
                        ))}
                    </div>
                </section>
            )}

            <section className="bg-teal text-ivory">
                <div className="mx-auto flex max-w-7xl flex-col items-start justify-between gap-6 px-4 py-14 sm:px-6 md:flex-row md:items-center">
                    <h2 className="font-display text-3xl font-semibold">
                        Ready when you are.
                    </h2>
                    <Button asChild variant="gold" size="lg">
                        <Link href={site.booking.start()}>
                            Check availability
                        </Link>
                    </Button>
                </div>
            </section>
        </>
    );
}
