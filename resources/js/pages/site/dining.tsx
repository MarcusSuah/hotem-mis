import { Star } from 'lucide-react';
import { Seo } from '@/components/site/seo';
import { EmptyNotice, PageHero } from '@/components/site/site-ui';
import type { MoneyValue } from '@/types';

type MenuSection = {
    name: string;
    items: {
        name: string;
        description: string | null;
        is_featured: boolean;
        price: MoneyValue;
        image_url: string | null;
    }[];
};

export default function Dining({ menu }: { menu: MenuSection[] }) {
    return (
        <>
            <Seo
                title="Dining"
                description="Our restaurant and bar menu, from Liberian classics to international favourites."
            />
            <PageHero
                eyebrow="Dining"
                title="Restaurant & bar"
                lead="Liberian classics and international favourites, served all day. Residents can charge meals to their room."
            />
            <section className="mx-auto max-w-4xl space-y-12 px-4 py-14 sm:px-6">
                {menu.length === 0 && (
                    <EmptyNotice>Our menu is being updated.</EmptyNotice>
                )}
                {menu.map((section) => (
                    <div key={section.name}>
                        <h2 className="border-b pb-2 font-display text-3xl font-semibold text-primary dark:text-foreground">
                            {section.name}
                        </h2>
                        <ul className="mt-4 divide-y">
                            {section.items.map((item) => (
                                <li key={item.name} className="flex gap-4 py-4">
                                    {item.image_url && (
                                        <img
                                            src={item.image_url}
                                            alt=""
                                            loading="lazy"
                                            className="size-16 shrink-0 rounded-lg object-cover"
                                        />
                                    )}
                                    <div className="flex-1">
                                        <p className="flex items-center gap-2 font-medium">
                                            {item.name}
                                            {item.is_featured && (
                                                <Star
                                                    className="size-3.5 fill-gold text-gold"
                                                    aria-label="Chef’s recommendation"
                                                />
                                            )}
                                        </p>
                                        {item.description && (
                                            <p className="text-sm text-muted-foreground">
                                                {item.description}
                                            </p>
                                        )}
                                    </div>
                                    <p className="font-medium whitespace-nowrap">
                                        {item.price.formatted}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}
                <p className="text-xs text-muted-foreground">
                    Applicable taxes are added to the bill.
                </p>
            </section>
        </>
    );
}
