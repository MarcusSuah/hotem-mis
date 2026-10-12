import { Seo } from '@/components/site/seo';
import { PageHero, Prose } from '@/components/site/site-ui';

type Props = {
    page: {
        title: string;
        summary: string | null;
        html: string;
        meta_title: string | null;
        meta_description: string | null;
        updated_at: string | null;
    };
};

export default function SitePage({ page }: Props) {
    return (
        <>
            <Seo
                title={page.meta_title ?? page.title}
                description={page.meta_description}
            />
            <PageHero title={page.title} lead={page.summary} />
            <article className="mx-auto max-w-3xl px-4 py-14 sm:px-6">
                <Prose html={page.html} />
            </article>
        </>
    );
}
