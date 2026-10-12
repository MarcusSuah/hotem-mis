import { Seo } from '@/components/site/seo';
import {
    CoverImage,
    PostCard,
    Prose,
    SectionHeading,
} from '@/components/site/site-ui';
import type { PostCardData } from '@/components/site/site-ui';
import { formatDate } from '@/lib/format';

type Post = PostCardData & {
    html: string;
    meta_title: string | null;
    meta_description: string | null;
};

export default function BlogShow({
    post,
    related,
}: {
    post: Post;
    related: PostCardData[];
}) {
    return (
        <>
            <Seo
                title={post.meta_title ?? post.title}
                description={post.meta_description}
                image={post.image_url}
                type="article"
            />
            <article className="mx-auto max-w-3xl px-4 py-12 sm:px-6">
                <p className="text-sm text-muted-foreground">
                    {formatDate(post.published_at)}
                    {post.author && ` · ${post.author}`}
                </p>
                <h1 className="mt-3 font-display text-5xl leading-tight font-semibold text-primary dark:text-foreground">
                    {post.title}
                </h1>
                {post.image_url && (
                    <div className="mt-8 aspect-[16/9] overflow-hidden rounded-xl">
                        <CoverImage src={post.image_url} alt={post.image_alt} />
                    </div>
                )}
                <Prose html={post.html} className="mt-8" />
            </article>
            {related.length > 0 && (
                <section className="mx-auto max-w-7xl space-y-8 px-4 pb-20 sm:px-6">
                    <SectionHeading title="More from the journal" />
                    <div className="grid gap-6 md:grid-cols-3">
                        {related.map((item) => (
                            <PostCard key={item.slug} post={item} />
                        ))}
                    </div>
                </section>
            )}
        </>
    );
}
