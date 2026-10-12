import { Pagination } from '@/components/admin/pagination';
import { Seo } from '@/components/site/seo';
import {
    EmptyNotice,
    PageHero,
    ReviewCard,
    Stars,
} from '@/components/site/site-ui';
import type { ReviewData } from '@/components/site/site-ui';
import type { Paginated } from '@/types';

type Props = {
    summary: {
        average: number;
        count: number;
        distribution: Record<string, number>;
    };
    reviews: Paginated<ReviewData>;
};

export default function Reviews({ summary, reviews }: Props) {
    return (
        <>
            <Seo
                title="Guest reviews"
                description="Verified reviews from guests who have stayed with us."
            />
            <PageHero
                eyebrow="Guest reviews"
                title="What our guests say"
                lead="Every review comes from a completed stay and is checked by our team before it is published."
            />
            <section className="mx-auto grid max-w-7xl gap-10 px-4 py-14 sm:px-6 lg:grid-cols-[18rem_1fr]">
                <aside className="h-fit space-y-4 rounded-xl border bg-card p-6 shadow-xs">
                    {summary.count > 0 ? (
                        <>
                            <p className="font-display text-5xl font-semibold text-primary dark:text-foreground">
                                {summary.average.toFixed(1)}
                            </p>
                            <Stars rating={summary.average} />
                            <p className="text-sm text-muted-foreground">
                                From {summary.count} verified{' '}
                                {summary.count === 1 ? 'stay' : 'stays'}
                            </p>
                            <ul className="space-y-1.5 text-sm">
                                {[5, 4, 3, 2, 1].map((rating) => {
                                    const total =
                                        summary.distribution[rating] ?? 0;

                                    return (
                                        <li
                                            key={rating}
                                            className="flex items-center gap-2"
                                        >
                                            <span className="w-3">
                                                {rating}
                                            </span>
                                            <span className="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                                                <span
                                                    className="block h-full bg-gold"
                                                    style={{
                                                        width: `${(total / summary.count) * 100}%`,
                                                    }}
                                                />
                                            </span>
                                            <span className="w-6 text-right text-muted-foreground">
                                                {total}
                                            </span>
                                        </li>
                                    );
                                })}
                            </ul>
                        </>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            No reviews have been published yet.
                        </p>
                    )}
                </aside>
                <div className="space-y-6">
                    {reviews.data.length === 0 ? (
                        <EmptyNotice>
                            Reviews from our guests will appear here.
                        </EmptyNotice>
                    ) : (
                        <div className="grid gap-6 md:grid-cols-2">
                            {reviews.data.map((review) => (
                                <ReviewCard key={review.id} review={review} />
                            ))}
                        </div>
                    )}
                    <Pagination paginator={reviews} />
                </div>
            </section>
        </>
    );
}
