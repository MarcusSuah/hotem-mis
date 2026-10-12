import { Link } from '@inertiajs/react';
import { Seo } from '@/components/site/seo';
import {
    CoverImage,
    EmptyNotice,
    PageHero,
    Prose,
} from '@/components/site/site-ui';
import { Button } from '@/components/ui/button';
import site from '@/routes/site';
import type { MoneyValue } from '@/types';

type Service = {
    slug: string;
    name: string;
    short_description: string | null;
    unit: string;
    is_featured: boolean;
    html: string;
    category: string;
    price: MoneyValue;
    image_url: string | null;
};

export default function Experiences({ services }: { services: Service[] }) {
    return (
        <>
            <Seo
                title="Experiences & services"
                description="Spa treatments, airport transfers, tours and more for hotel guests."
            />
            <PageHero
                eyebrow="Experiences"
                title="Services for your stay"
                lead="Arrange any of these through reception or let us know in your booking’s special requests."
            />
            <section className="mx-auto max-w-6xl space-y-6 px-4 py-14 sm:px-6">
                {services.length === 0 && (
                    <EmptyNotice>
                        Our services list is being updated.
                    </EmptyNotice>
                )}
                {services.map((service, index) => (
                    <article
                        key={service.slug}
                        className="grid overflow-hidden rounded-xl border bg-card shadow-xs md:grid-cols-2"
                    >
                        <div
                            className={
                                index % 2 === 1
                                    ? 'aspect-[4/3] md:order-2'
                                    : 'aspect-[4/3]'
                            }
                        >
                            <CoverImage
                                src={service.image_url}
                                alt={service.name}
                            />
                        </div>
                        <div className="flex flex-col justify-center gap-3 p-8">
                            <p className="text-xs font-semibold tracking-[0.25em] text-highlight-foreground uppercase dark:text-highlight">
                                {service.category}
                            </p>
                            <h2 className="font-display text-3xl font-semibold text-primary dark:text-foreground">
                                {service.name}
                            </h2>
                            {service.short_description && (
                                <p className="text-muted-foreground">
                                    {service.short_description}
                                </p>
                            )}
                            {service.html && (
                                <Prose
                                    html={service.html}
                                    className="text-sm"
                                />
                            )}
                            <p className="font-medium">
                                {service.price.formatted}{' '}
                                <span className="font-normal text-muted-foreground">
                                    {service.unit}
                                </span>
                            </p>
                        </div>
                    </article>
                ))}
                <div className="text-center">
                    <Button asChild variant="gold" size="lg">
                        <Link href={site.contact()}>
                            Ask about an experience
                        </Link>
                    </Button>
                </div>
            </section>
        </>
    );
}
