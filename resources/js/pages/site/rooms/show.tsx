import { BedDouble, Bath, Check, Maximize2, Users } from 'lucide-react';
import { useState } from 'react';
import { BookingSearchForm } from '@/components/site/booking-search-form';
import { Seo } from '@/components/site/seo';
import {
    CoverImage,
    Prose,
    ReviewCard,
    RoomCard,
    SectionHeading,
} from '@/components/site/site-ui';
import type { ReviewData, RoomCardData } from '@/components/site/site-ui';
import { cn } from '@/lib/utils';

type Room = RoomCardData & {
    description_html: string;
    max_adults: number;
    max_children: number;
    bathroom_details: string | null;
    images: { url: string; alt: string }[];
    amenities: { name: string; icon: string | null }[];
    meta_title: string | null;
    meta_description: string | null;
};

export default function RoomShow({
    room,
    others,
    reviews,
}: {
    room: Room;
    others: RoomCardData[];
    reviews: ReviewData[];
}) {
    const photos =
        room.images.length > 0
            ? room.images
            : room.image_url
              ? [{ url: room.image_url, alt: room.image_alt }]
              : [];
    const [active, setActive] = useState(0);

    return (
        <>
            <Seo
                title={room.meta_title ?? room.name}
                description={room.meta_description}
                image={photos[0]?.url}
            />
            <section className="mx-auto max-w-7xl px-4 py-10 sm:px-6">
                <div className="grid gap-8 lg:grid-cols-[3fr_2fr]">
                    <div className="space-y-3">
                        <div className="aspect-[16/10] overflow-hidden rounded-xl">
                            <CoverImage
                                src={photos[active]?.url ?? null}
                                alt={photos[active]?.alt ?? room.name}
                            />
                        </div>
                        {photos.length > 1 && (
                            <div className="flex gap-2 overflow-x-auto pb-1">
                                {photos.map((photo, index) => (
                                    <button
                                        key={photo.url}
                                        type="button"
                                        onClick={() => setActive(index)}
                                        className={cn(
                                            'h-16 w-24 shrink-0 overflow-hidden rounded-md ring-2 ring-transparent',
                                            index === active &&
                                                'ring-highlight',
                                        )}
                                        aria-label={`Show photo ${index + 1}`}
                                    >
                                        <img
                                            src={photo.url}
                                            alt=""
                                            className="size-full object-cover"
                                        />
                                    </button>
                                ))}
                            </div>
                        )}
                    </div>
                    <div className="space-y-5">
                        <p className="text-xs font-semibold tracking-[0.25em] text-highlight-foreground uppercase dark:text-highlight">
                            Room
                        </p>
                        <h1 className="font-display text-5xl font-semibold text-primary dark:text-foreground">
                            {room.name}
                        </h1>
                        {room.short_description && (
                            <p className="text-lg text-muted-foreground">
                                {room.short_description}
                            </p>
                        )}
                        <ul className="grid grid-cols-2 gap-3 text-sm">
                            <li className="flex items-center gap-2">
                                <Users className="size-4 text-highlight" />{' '}
                                {room.max_adults} adults · {room.max_children}{' '}
                                children
                            </li>
                            <li className="flex items-center gap-2">
                                <BedDouble className="size-4 text-highlight" />{' '}
                                {room.bed}
                            </li>
                            {room.size_sqm && (
                                <li className="flex items-center gap-2">
                                    <Maximize2 className="size-4 text-highlight" />{' '}
                                    {Number(room.size_sqm)} m²
                                </li>
                            )}
                            {room.bathroom_details && (
                                <li className="flex items-center gap-2">
                                    <Bath className="size-4 text-highlight" />{' '}
                                    {room.bathroom_details}
                                </li>
                            )}
                        </ul>
                        <p>
                            From{' '}
                            <span className="text-2xl font-semibold text-primary dark:text-foreground">
                                {room.from_price.formatted}
                            </span>{' '}
                            per night, before taxes
                        </p>
                    </div>
                </div>
            </section>

            <section className="bg-sage/50 py-8 dark:bg-muted/30">
                <div className="mx-auto max-w-7xl px-4 sm:px-6">
                    <BookingSearchForm />
                </div>
            </section>

            <section className="mx-auto grid max-w-7xl gap-12 px-4 py-16 sm:px-6 lg:grid-cols-[3fr_2fr]">
                <div>
                    {room.description_html ? (
                        <Prose html={room.description_html} />
                    ) : (
                        <p className="text-muted-foreground">
                            {room.short_description}
                        </p>
                    )}
                </div>
                {room.amenities.length > 0 && (
                    <div className="rounded-xl border bg-card p-6 shadow-xs">
                        <h2 className="font-display text-2xl font-semibold">
                            In the room
                        </h2>
                        <ul className="mt-4 grid gap-2 text-sm sm:grid-cols-2">
                            {room.amenities.map((amenity) => (
                                <li
                                    key={amenity.name}
                                    className="flex items-center gap-2"
                                >
                                    <Check className="size-4 text-highlight" />{' '}
                                    {amenity.name}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </section>

            {reviews.length > 0 && (
                <section className="mx-auto max-w-7xl space-y-8 px-4 pb-16 sm:px-6">
                    <SectionHeading
                        eyebrow="Reviews"
                        title="Guests who stayed here"
                    />
                    <div className="grid gap-6 md:grid-cols-3">
                        {reviews.map((review) => (
                            <ReviewCard key={review.id} review={review} />
                        ))}
                    </div>
                </section>
            )}

            {others.length > 0 && (
                <section className="mx-auto max-w-7xl space-y-8 px-4 pb-20 sm:px-6">
                    <SectionHeading title="You may also like" />
                    <div className="grid gap-6 md:grid-cols-3">
                        {others.map((other) => (
                            <RoomCard key={other.slug} room={other} />
                        ))}
                    </div>
                </section>
            )}
        </>
    );
}
