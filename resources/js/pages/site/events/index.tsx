import { Seo } from '@/components/site/seo';
import { EmptyNotice, EventCard, PageHero } from '@/components/site/site-ui';
import type { EventCardData } from '@/components/site/site-ui';

export default function EventsIndex({ events }: { events: EventCardData[] }) {
    return (
        <>
            <Seo
                title="Events"
                description="Live music, dinners and celebrations at the hotel."
            />
            <PageHero eyebrow="What’s on" title="Events at the hotel" />
            <section className="mx-auto max-w-7xl px-4 py-16 sm:px-6">
                {events.length === 0 ? (
                    <EmptyNotice>
                        No events are scheduled right now. Check back soon.
                    </EmptyNotice>
                ) : (
                    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {events.map((event) => (
                            <EventCard key={event.slug} event={event} />
                        ))}
                    </div>
                )}
            </section>
        </>
    );
}
