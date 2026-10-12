import { Head, usePage } from '@inertiajs/react';

/**
 * Page title plus description, canonical and social sharing tags.
 */
export function Seo({
    title,
    description,
    image,
    type = 'website',
}: {
    title?: string | null;
    description?: string | null;
    image?: string | null;
    type?: 'website' | 'article';
}) {
    const page = usePage();
    const { hotel } = page.props;
    const canonical = `${hotel.url}${page.url.split('?')[0]}`;
    const summary = description ?? hotel.tagline ?? undefined;
    const socialTitle = title ? `${title} | ${hotel.name}` : hotel.name;

    return (
        <Head title={title ?? undefined}>
            {summary && (
                <meta
                    head-key="description"
                    name="description"
                    content={summary}
                />
            )}
            <link head-key="canonical" rel="canonical" href={canonical} />
            <meta head-key="og:type" property="og:type" content={type} />
            <meta
                head-key="og:title"
                property="og:title"
                content={socialTitle}
            />
            {summary && (
                <meta
                    head-key="og:description"
                    property="og:description"
                    content={summary}
                />
            )}
            <meta head-key="og:url" property="og:url" content={canonical} />
            <meta
                head-key="og:site_name"
                property="og:site_name"
                content={hotel.name}
            />
            {image && (
                <meta head-key="og:image" property="og:image" content={image} />
            )}
            <meta
                head-key="twitter:card"
                name="twitter:card"
                content={image ? 'summary_large_image' : 'summary'}
            />
        </Head>
    );
}
