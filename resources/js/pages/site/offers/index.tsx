import { Seo } from '@/components/site/seo';
import { EmptyNotice, OfferCard, PageHero } from '@/components/site/site-ui';
import type { OfferCardData } from '@/components/site/site-ui';

export default function OffersIndex({ offers }: { offers: OfferCardData[] }) {
    return (
        <>
            <Seo
                title="Offers & packages"
                description="Current packages and seasonal offers when you book directly."
            />
            <PageHero
                eyebrow="Offers"
                title="Offers & packages"
                lead="Book directly with us to take advantage of these packages."
            />
            <section className="mx-auto max-w-7xl px-4 py-16 sm:px-6">
                {offers.length === 0 ? (
                    <EmptyNotice>
                        There are no special offers right now — our best
                        available rate is always online.
                    </EmptyNotice>
                ) : (
                    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {offers.map((offer) => (
                            <OfferCard key={offer.slug} offer={offer} />
                        ))}
                    </div>
                )}
            </section>
        </>
    );
}
