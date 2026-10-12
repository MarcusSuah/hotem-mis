import { Link } from '@inertiajs/react';
import {
    BedDouble,
    CalendarDays,
    MapPin,
    Maximize2,
    Star,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { formatDate, formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import site from '@/routes/site';
import type { MoneyValue } from '@/types';

export type RoomCardData = {
    slug: string;
    name: string;
    short_description: string | null;
    image_url: string | null;
    image_alt: string;
    max_occupancy: number;
    bed: string;
    size_sqm: string | null;
    from_price: MoneyValue;
};

export type OfferCardData = {
    slug: string;
    title: string;
    summary: string | null;
    image_url: string | null;
    image_alt: string;
    valid_until: string | null;
    room_type: { slug: string; name: string } | null;
};

export type EventCardData = {
    slug: string;
    title: string;
    summary: string | null;
    venue: string | null;
    starts_at: string;
    ends_at: string | null;
    image_url: string | null;
    image_alt: string;
};

export type PostCardData = {
    slug: string;
    title: string;
    excerpt: string | null;
    image_url: string | null;
    image_alt: string;
    published_at: string | null;
    author: string | null;
};

export type ReviewData = {
    id: number;
    rating: number;
    title: string;
    body: string;
    author: string;
    stayed: string;
    response: string | null;
};

/**
 * Eyebrow, display heading and optional lead for a website section.
 */
export function SectionHeading({
    eyebrow,
    title,
    lead,
    align = 'left',
    className,
}: {
    eyebrow?: string;
    title: string;
    lead?: ReactNode;
    align?: 'left' | 'center';
    className?: string;
}) {
    return (
        <div
            className={cn(
                'max-w-2xl space-y-3',
                align === 'center' && 'mx-auto text-center',
                className,
            )}
        >
            {eyebrow && (
                <p className="text-xs font-semibold tracking-[0.25em] text-highlight-foreground uppercase dark:text-highlight">
                    {eyebrow}
                </p>
            )}
            <h2 className="font-display text-4xl font-semibold text-primary dark:text-foreground">
                {title}
            </h2>
            {lead && <p className="text-muted-foreground">{lead}</p>}
        </div>
    );
}

/**
 * Banner at the top of inner website pages.
 */
export function PageHero({
    eyebrow,
    title,
    lead,
    image,
}: {
    eyebrow?: string;
    title: string;
    lead?: ReactNode;
    image?: string | null;
}) {
    return (
        <section className="relative overflow-hidden bg-teal text-ivory">
            {image && (
                <img
                    src={image}
                    alt=""
                    className="absolute inset-0 size-full object-cover opacity-30"
                />
            )}
            <div className="relative mx-auto max-w-7xl px-4 py-16 sm:px-6 md:py-20">
                {eyebrow && (
                    <p className="text-xs font-semibold tracking-[0.25em] text-gold uppercase">
                        {eyebrow}
                    </p>
                )}
                <h1 className="mt-3 max-w-3xl font-display text-4xl font-semibold md:text-5xl">
                    {title}
                </h1>
                {lead && <p className="mt-4 max-w-2xl text-ivory/80">{lead}</p>}
            </div>
        </section>
    );
}

/**
 * Image with a branded placeholder when no photo has been uploaded.
 */
export function CoverImage({
    src,
    alt,
    className,
}: {
    src: string | null;
    alt: string;
    className?: string;
}) {
    return src ? (
        <img
            src={src}
            alt={alt}
            loading="lazy"
            className={cn('size-full object-cover', className)}
        />
    ) : (
        <div
            className={cn(
                'flex size-full items-center justify-center bg-gradient-to-br from-teal to-teal-soft',
                className,
            )}
            role="img"
            aria-label={alt}
        >
            <span className="font-display text-3xl text-gold/70">
                {alt.slice(0, 1)}
            </span>
        </div>
    );
}

export function Stars({
    rating,
    className,
}: {
    rating: number;
    className?: string;
}) {
    return (
        <span
            className={cn('inline-flex gap-0.5', className)}
            aria-label={`${rating} out of 5`}
        >
            {[1, 2, 3, 4, 5].map((value) => (
                <Star
                    key={value}
                    className={cn(
                        'size-4',
                        value <= Math.round(rating)
                            ? 'fill-gold text-gold'
                            : 'text-muted-foreground/40',
                    )}
                    aria-hidden
                />
            ))}
        </span>
    );
}

export function Prose({
    html,
    className,
}: {
    html: string;
    className?: string;
}) {
    // The server renders Markdown with raw HTML stripped and unsafe links refused.
    return (
        <div
            className={cn('site-prose', className)}
            dangerouslySetInnerHTML={{ __html: html }}
        />
    );
}

export function RoomCard({ room }: { room: RoomCardData }) {
    return (
        <article className="group flex flex-col overflow-hidden rounded-xl border bg-card shadow-xs transition hover:shadow-md">
            <Link
                href={site.rooms.show(room.slug)}
                className="aspect-[4/3] overflow-hidden"
            >
                <CoverImage
                    src={room.image_url}
                    alt={room.image_alt}
                    className="transition duration-500 group-hover:scale-105"
                />
            </Link>
            <div className="flex flex-1 flex-col gap-3 p-5">
                <h3 className="font-display text-2xl font-semibold text-primary dark:text-foreground">
                    <Link href={site.rooms.show(room.slug)}>{room.name}</Link>
                </h3>
                {room.short_description && (
                    <p className="line-clamp-2 text-sm text-muted-foreground">
                        {room.short_description}
                    </p>
                )}
                <ul className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                    <li className="flex items-center gap-1">
                        <Users className="size-3.5 text-highlight" /> Up to{' '}
                        {room.max_occupancy}
                    </li>
                    <li className="flex items-center gap-1">
                        <BedDouble className="size-3.5 text-highlight" />{' '}
                        {room.bed}
                    </li>
                    {room.size_sqm && (
                        <li className="flex items-center gap-1">
                            <Maximize2 className="size-3.5 text-highlight" />{' '}
                            {Number(room.size_sqm)} m²
                        </li>
                    )}
                </ul>
                <p className="mt-auto pt-2 text-sm">
                    From{' '}
                    <span className="text-lg font-semibold text-primary dark:text-foreground">
                        {room.from_price.formatted}
                    </span>{' '}
                    / night
                </p>
            </div>
        </article>
    );
}

export function OfferCard({ offer }: { offer: OfferCardData }) {
    return (
        <article className="group flex flex-col overflow-hidden rounded-xl border bg-card shadow-xs">
            <Link
                href={site.offers.show(offer.slug)}
                className="aspect-[16/9] overflow-hidden"
            >
                <CoverImage
                    src={offer.image_url}
                    alt={offer.image_alt}
                    className="transition duration-500 group-hover:scale-105"
                />
            </Link>
            <div className="flex flex-1 flex-col gap-2 p-5">
                <h3 className="font-display text-2xl font-semibold text-primary dark:text-foreground">
                    <Link href={site.offers.show(offer.slug)}>
                        {offer.title}
                    </Link>
                </h3>
                {offer.summary && (
                    <p className="text-sm text-muted-foreground">
                        {offer.summary}
                    </p>
                )}
                {offer.valid_until && (
                    <p className="mt-auto pt-2 text-xs text-muted-foreground">
                        Book by {formatDate(offer.valid_until)}
                    </p>
                )}
            </div>
        </article>
    );
}

export function EventCard({ event }: { event: EventCardData }) {
    return (
        <article className="group flex flex-col overflow-hidden rounded-xl border bg-card shadow-xs">
            <Link
                href={site.events.show(event.slug)}
                className="aspect-[16/9] overflow-hidden"
            >
                <CoverImage
                    src={event.image_url}
                    alt={event.image_alt}
                    className="transition duration-500 group-hover:scale-105"
                />
            </Link>
            <div className="flex flex-1 flex-col gap-2 p-5">
                <p className="flex items-center gap-1.5 text-xs font-medium text-highlight-foreground dark:text-highlight">
                    <CalendarDays className="size-3.5" />{' '}
                    {formatDateTime(event.starts_at)}
                </p>
                <h3 className="font-display text-2xl font-semibold text-primary dark:text-foreground">
                    <Link href={site.events.show(event.slug)}>
                        {event.title}
                    </Link>
                </h3>
                {event.summary && (
                    <p className="text-sm text-muted-foreground">
                        {event.summary}
                    </p>
                )}
                {event.venue && (
                    <p className="mt-auto flex items-center gap-1.5 pt-2 text-xs text-muted-foreground">
                        <MapPin className="size-3.5" /> {event.venue}
                    </p>
                )}
            </div>
        </article>
    );
}

export function PostCard({ post }: { post: PostCardData }) {
    return (
        <article className="group flex flex-col overflow-hidden rounded-xl border bg-card shadow-xs">
            <Link
                href={site.blog.show(post.slug)}
                className="aspect-[16/9] overflow-hidden"
            >
                <CoverImage
                    src={post.image_url}
                    alt={post.image_alt}
                    className="transition duration-500 group-hover:scale-105"
                />
            </Link>
            <div className="flex flex-1 flex-col gap-2 p-5">
                <p className="text-xs text-muted-foreground">
                    {formatDate(post.published_at)}
                    {post.author && ` · ${post.author}`}
                </p>
                <h3 className="font-display text-2xl font-semibold text-primary dark:text-foreground">
                    <Link href={site.blog.show(post.slug)}>{post.title}</Link>
                </h3>
                {post.excerpt && (
                    <p className="line-clamp-3 text-sm text-muted-foreground">
                        {post.excerpt}
                    </p>
                )}
            </div>
        </article>
    );
}

export function ReviewCard({ review }: { review: ReviewData }) {
    return (
        <figure className="flex h-full flex-col gap-3 rounded-xl border bg-card p-6 shadow-xs">
            <Stars rating={review.rating} />
            <blockquote className="space-y-2">
                <p className="font-display text-xl font-semibold text-primary dark:text-foreground">
                    “{review.title}”
                </p>
                <p className="text-sm whitespace-pre-line text-muted-foreground">
                    {review.body}
                </p>
            </blockquote>
            <figcaption className="mt-auto pt-2 text-sm font-medium">
                {review.author}{' '}
                <span className="font-normal text-muted-foreground">
                    · stayed {review.stayed}
                </span>
            </figcaption>
            {review.response && (
                <div className="rounded-lg bg-sage/60 p-3 text-sm dark:bg-muted/40">
                    <p className="text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                        Response from the hotel
                    </p>
                    <p className="mt-1 whitespace-pre-line">
                        {review.response}
                    </p>
                </div>
            )}
        </figure>
    );
}

export function EmptyNotice({ children }: { children: ReactNode }) {
    return (
        <p className="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
            {children}
        </p>
    );
}
