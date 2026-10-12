import { CalendarDays, MapPin } from 'lucide-react';
import { Seo } from '@/components/site/seo';
import { CoverImage, Prose } from '@/components/site/site-ui';
import type { EventCardData } from '@/components/site/site-ui';
import { formatDateTime } from '@/lib/format';

type Event = EventCardData & {
    html: string;
    is_past: boolean;
    meta_description: string | null;
};

export default function EventShow({ event }: { event: Event }) {
    return (
        <>
            <Seo
                title={event.title}
                description={event.meta_description}
                image={event.image_url}
                type="article"
            />
            <article className="mx-auto max-w-4xl px-4 py-12 sm:px-6">
                <div className="aspect-[21/9] overflow-hidden rounded-xl">
                    <CoverImage src={event.image_url} alt={event.image_alt} />
                </div>
                <h1 className="mt-8 font-display text-5xl font-semibold text-primary dark:text-foreground">
                    {event.title}
                </h1>
                <div className="mt-4 flex flex-wrap gap-4 text-sm text-muted-foreground">
                    <span className="flex items-center gap-1.5">
                        <CalendarDays className="size-4 text-highlight" />
                        {formatDateTime(event.starts_at)}
                        {event.ends_at && ` – ${formatDateTime(event.ends_at)}`}
                    </span>
                    {event.venue && (
                        <span className="flex items-center gap-1.5">
                            <MapPin className="size-4 text-highlight" />{' '}
                            {event.venue}
                        </span>
                    )}
                </div>
                {event.is_past && (
                    <p className="mt-4 rounded-lg bg-muted p-3 text-sm">
                        This event has already taken place.
                    </p>
                )}
                {event.summary && (
                    <p className="mt-6 text-lg text-muted-foreground">
                        {event.summary}
                    </p>
                )}
                {event.html && <Prose html={event.html} className="mt-6" />}
            </article>
        </>
    );
}
