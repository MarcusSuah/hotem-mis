import { Seo } from '@/components/site/seo';
import { EmptyNotice, PageHero, RoomCard } from '@/components/site/site-ui';
import type { RoomCardData } from '@/components/site/site-ui';

export default function RoomsIndex({ rooms }: { rooms: RoomCardData[] }) {
    return (
        <>
            <Seo
                title="Rooms & suites"
                description="Explore our rooms and suites and check live availability."
            />
            <PageHero
                eyebrow="Stay"
                title="Rooms & suites"
                lead="Every room is prepared and inspected before you arrive. Prices shown are our standard nightly rate before taxes."
            />
            <section className="mx-auto max-w-7xl px-4 py-16 sm:px-6">
                {rooms.length === 0 ? (
                    <EmptyNotice>
                        Our rooms are being updated. Please contact us to book.
                    </EmptyNotice>
                ) : (
                    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {rooms.map((room) => (
                            <RoomCard key={room.slug} room={room} />
                        ))}
                    </div>
                )}
            </section>
        </>
    );
}
