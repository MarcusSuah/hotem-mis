import { X } from 'lucide-react';
import { useState } from 'react';
import { Seo } from '@/components/site/seo';
import { EmptyNotice, PageHero } from '@/components/site/site-ui';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

type Image = {
    id: number;
    url: string;
    alt: string;
    caption: string | null;
    category: string;
};

export default function Gallery({
    images,
    categories,
}: {
    images: Image[];
    categories: { value: string; label: string }[];
}) {
    const [category, setCategory] = useState('');
    const [open, setOpen] = useState<Image | null>(null);
    const visible = category
        ? images.filter((image) => image.category === category)
        : images;
    const used = categories.filter((option) =>
        images.some((image) => image.category === option.value),
    );

    return (
        <>
            <Seo
                title="Gallery"
                description="Photos of our rooms, dining and grounds."
                image={images[0]?.url}
            />
            <PageHero eyebrow="Gallery" title="A look around" />
            <section className="mx-auto max-w-7xl space-y-8 px-4 py-14 sm:px-6">
                {used.length > 1 && (
                    <div
                        className="flex flex-wrap gap-2"
                        role="group"
                        aria-label="Filter photos"
                    >
                        {[{ value: '', label: 'All' }, ...used].map(
                            (option) => (
                                <button
                                    key={option.value}
                                    type="button"
                                    aria-pressed={category === option.value}
                                    onClick={() => setCategory(option.value)}
                                    className={cn(
                                        'rounded-full border px-4 py-1.5 text-sm transition',
                                        category === option.value
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'hover:bg-muted',
                                    )}
                                >
                                    {option.label}
                                </button>
                            ),
                        )}
                    </div>
                )}
                {visible.length === 0 ? (
                    <EmptyNotice>Photos are coming soon.</EmptyNotice>
                ) : (
                    <div className="columns-1 gap-4 sm:columns-2 lg:columns-3">
                        {visible.map((image) => (
                            <button
                                key={image.id}
                                type="button"
                                onClick={() => setOpen(image)}
                                className="mb-4 block w-full overflow-hidden rounded-lg focus-visible:ring-2 focus-visible:ring-highlight focus-visible:outline-none"
                            >
                                <img
                                    src={image.url}
                                    alt={image.alt}
                                    loading="lazy"
                                    className="w-full transition duration-500 hover:scale-[1.02]"
                                />
                            </button>
                        ))}
                    </div>
                )}
            </section>
            <Dialog
                open={open !== null}
                onOpenChange={(next) => !next && setOpen(null)}
            >
                <DialogContent className="max-w-5xl border-0 bg-black p-0 [&>button]:hidden">
                    <DialogTitle className="sr-only">{open?.alt}</DialogTitle>
                    {open && (
                        <figure>
                            <img
                                src={open.url}
                                alt={open.alt}
                                className="max-h-[80vh] w-full object-contain"
                            />
                            {open.caption && (
                                <figcaption className="p-3 text-center text-sm text-white/80">
                                    {open.caption}
                                </figcaption>
                            )}
                        </figure>
                    )}
                    <button
                        type="button"
                        onClick={() => setOpen(null)}
                        className="absolute top-2 right-2 rounded-full bg-black/60 p-2 text-white"
                        aria-label="Close"
                    >
                        <X className="size-4" />
                    </button>
                </DialogContent>
            </Dialog>
        </>
    );
}
