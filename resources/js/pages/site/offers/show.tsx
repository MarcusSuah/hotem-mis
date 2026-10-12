import { Link } from '@inertiajs/react';
import { Coffee, ShieldCheck } from 'lucide-react';
import { Seo } from '@/components/site/seo';
import { CoverImage, Prose } from '@/components/site/site-ui';
import type { OfferCardData } from '@/components/site/site-ui';
import { Button } from '@/components/ui/button';
import { formatDate } from '@/lib/format';
import site from '@/routes/site';

type Offer = OfferCardData & {
    html: string;
    valid_from: string | null;
    includes_breakfast: boolean;
    policy: string | null;
    expired: boolean;
    meta_description: string | null;
};

export default function OfferShow({ offer }: { offer: Offer }) {
    return (
        <>
            <Seo
                title={offer.title}
                description={offer.meta_description}
                image={offer.image_url}
            />
            <div className="mx-auto max-w-5xl px-4 py-12 sm:px-6">
                <div className="aspect-[21/9] overflow-hidden rounded-xl">
                    <CoverImage src={offer.image_url} alt={offer.image_alt} />
                </div>
                <div className="mt-10 grid gap-10 md:grid-cols-[2fr_1fr]">
                    <article className="space-y-4">
                        <p className="text-xs font-semibold tracking-[0.25em] text-highlight-foreground uppercase dark:text-highlight">
                            Offer
                        </p>
                        <h1 className="font-display text-5xl font-semibold text-primary dark:text-foreground">
                            {offer.title}
                        </h1>
                        {offer.summary && (
                            <p className="text-lg text-muted-foreground">
                                {offer.summary}
                            </p>
                        )}
                        {offer.html && <Prose html={offer.html} />}
                    </article>
                    <aside className="h-fit space-y-4 rounded-xl border bg-card p-6 text-sm shadow-xs">
                        {offer.expired ? (
                            <p className="font-medium">This offer has ended.</p>
                        ) : (
                            <>
                                {(offer.valid_from || offer.valid_until) && (
                                    <p>
                                        For arrivals{' '}
                                        {offer.valid_from
                                            ? `from ${formatDate(offer.valid_from)}`
                                            : ''}{' '}
                                        {offer.valid_until
                                            ? `until ${formatDate(offer.valid_until)}`
                                            : ''}
                                    </p>
                                )}
                                {offer.room_type && (
                                    <p>
                                        Available in the {offer.room_type.name}.
                                    </p>
                                )}
                                {offer.includes_breakfast && (
                                    <p className="flex items-center gap-2">
                                        <Coffee className="size-4 text-highlight" />{' '}
                                        Breakfast included
                                    </p>
                                )}
                                {offer.policy && (
                                    <p className="flex gap-2">
                                        <ShieldCheck className="mt-0.5 size-4 shrink-0 text-highlight" />{' '}
                                        {offer.policy}
                                    </p>
                                )}
                                <Button
                                    asChild
                                    variant="gold"
                                    className="w-full"
                                >
                                    <Link
                                        href={site.booking.start({
                                            query: { offer: offer.slug },
                                        })}
                                    >
                                        Book this offer
                                    </Link>
                                </Button>
                            </>
                        )}
                    </aside>
                </div>
            </div>
        </>
    );
}
