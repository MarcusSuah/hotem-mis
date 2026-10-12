import { Pagination } from '@/components/admin/pagination';
import { Seo } from '@/components/site/seo';
import { EmptyNotice, PageHero, PostCard } from '@/components/site/site-ui';
import type { PostCardData } from '@/components/site/site-ui';
import type { Paginated } from '@/types';

export default function BlogIndex({
    posts,
}: {
    posts: Paginated<PostCardData>;
}) {
    return (
        <>
            <Seo
                title="Journal"
                description="Stories from the hotel and our guide to the city."
            />
            <PageHero eyebrow="Journal" title="Stories & local guides" />
            <section className="mx-auto max-w-7xl space-y-10 px-4 py-16 sm:px-6">
                {posts.data.length === 0 ? (
                    <EmptyNotice>
                        Our first stories are on their way.
                    </EmptyNotice>
                ) : (
                    <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {posts.data.map((post) => (
                            <PostCard key={post.slug} post={post} />
                        ))}
                    </div>
                )}
                <Pagination paginator={posts} />
            </section>
        </>
    );
}
