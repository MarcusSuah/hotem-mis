import { Head, router, useForm } from '@inertiajs/react';
import {
    CalendarDays,
    ExternalLink,
    FileText,
    Newspaper,
    Plus,
    Tag,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import { EmptyState } from '@/components/admin/empty-state';
import { Field } from '@/components/admin/field';
import { FormDialog } from '@/components/admin/form-dialog';
import { FormErrorSummary } from '@/components/admin/form-error-summary';
import { ImagePicker } from '@/components/admin/image-picker';
import { OptionSelect } from '@/components/admin/option-select';
import { PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { fieldError, notifyValidationFailed } from '@/lib/form';
import { formatDate, formatDateTime } from '@/lib/format';
import admin from '@/routes/admin';

type Kind = 'page' | 'post' | 'event' | 'offer';

type Base = {
    id: number;
    title: string;
    slug: string;
    body: string | null;
    url: string;
};
type PageRow = Base & {
    summary: string | null;
    meta_title: string | null;
    meta_description: string | null;
    is_published: boolean;
    show_in_footer: boolean;
    display_order: number;
    updated_at: string | null;
};
type PostRow = Base & {
    excerpt: string | null;
    cover_image_alt: string | null;
    meta_title: string | null;
    meta_description: string | null;
    published_at: string | null;
    is_live: boolean;
    author: string | null;
    image_url: string | null;
};
type EventRow = Base & {
    summary: string | null;
    venue: string | null;
    image_alt: string | null;
    is_published: boolean;
    starts_at: string;
    ends_at: string | null;
    image_url: string | null;
};
type OfferRow = Base & {
    summary: string | null;
    image_alt: string | null;
    rate_plan_id: number | null;
    room_type_id: number | null;
    is_published: boolean;
    display_order: number;
    valid_from: string | null;
    valid_until: string | null;
    rate_plan: string | null;
    room_type: string | null;
    image_url: string | null;
};
type AnyRow = PageRow | PostRow | EventRow | OfferRow;

type Props = {
    pages: PageRow[];
    posts: PostRow[];
    events: EventRow[];
    offers: OfferRow[];
    ratePlans: { id: number; name: string }[];
    roomTypes: { id: number; name: string }[];
};

type Editing = { kind: Kind; row: AnyRow | null };

const labels: Record<Kind, string> = {
    page: 'page',
    post: 'article',
    event: 'event',
    offer: 'offer',
};

const routesFor = {
    page: admin.website.pages,
    post: admin.website.posts,
    event: admin.website.events,
    offer: admin.website.offers,
};

function Row({
    title,
    meta,
    live,
    url,
    onEdit,
    onDelete,
}: {
    title: string;
    meta: string;
    live: boolean;
    url: string;
    onEdit: () => void;
    onDelete: () => void;
}) {
    return (
        <li className="flex flex-wrap items-center justify-between gap-3 p-4 text-sm">
            <div className="min-w-0">
                <p className="font-medium">
                    {title}{' '}
                    {live ? (
                        <Badge variant="success">Live</Badge>
                    ) : (
                        <Badge variant="muted">Draft</Badge>
                    )}
                </p>
                <p className="text-xs text-muted-foreground">{meta}</p>
            </div>
            <div className="flex items-center gap-1">
                {live && (
                    <Button asChild variant="ghost" size="sm">
                        <a href={url} target="_blank" rel="noreferrer">
                            <ExternalLink />
                            View
                        </a>
                    </Button>
                )}
                <Button variant="ghost" size="sm" onClick={onEdit}>
                    Edit
                </Button>
                <Button
                    variant="ghost"
                    size="sm"
                    className="text-destructive"
                    onClick={onDelete}
                >
                    Remove
                </Button>
            </div>
        </li>
    );
}

export default function WebsiteContent(props: Props) {
    const { pages, posts, events, offers } = props;
    const [editing, setEditing] = useState<Editing | null>(null);
    const [removing, setRemoving] = useState<{
        kind: Kind;
        row: AnyRow;
    } | null>(null);
    const [processing, setProcessing] = useState(false);

    const newButton = (kind: Kind) => (
        <div className="flex justify-end border-b p-3">
            <Button size="sm" onClick={() => setEditing({ kind, row: null })}>
                <Plus />
                New {labels[kind]}
            </Button>
        </div>
    );

    return (
        <>
            <Head title="Website content" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Website content"
                    description="Pages, journal articles, events and offers. Content is written in Markdown; raw HTML is removed when published."
                />
                <Tabs defaultValue="pages" className="gap-4">
                    <TabsList>
                        <TabsTrigger value="pages">
                            Pages ({pages.length})
                        </TabsTrigger>
                        <TabsTrigger value="posts">
                            Journal ({posts.length})
                        </TabsTrigger>
                        <TabsTrigger value="events">
                            Events ({events.length})
                        </TabsTrigger>
                        <TabsTrigger value="offers">
                            Offers ({offers.length})
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent
                        value="pages"
                        className="rounded-xl border bg-card shadow-xs"
                    >
                        {newButton('page')}
                        {pages.length === 0 ? (
                            <EmptyState
                                icon={FileText}
                                title="No pages yet"
                                description="Add About us, house policies or a privacy notice."
                            />
                        ) : (
                            <ul className="divide-y">
                                {pages.map((row) => (
                                    <Row
                                        key={row.id}
                                        title={row.title}
                                        meta={`/pages/${row.slug}${row.show_in_footer ? ' · in footer' : ''} · updated ${formatDateTime(row.updated_at)}`}
                                        live={row.is_published}
                                        url={row.url}
                                        onEdit={() =>
                                            setEditing({ kind: 'page', row })
                                        }
                                        onDelete={() =>
                                            setRemoving({ kind: 'page', row })
                                        }
                                    />
                                ))}
                            </ul>
                        )}
                    </TabsContent>

                    <TabsContent
                        value="posts"
                        className="rounded-xl border bg-card shadow-xs"
                    >
                        {newButton('post')}
                        {posts.length === 0 ? (
                            <EmptyState
                                icon={Newspaper}
                                title="No articles yet"
                            />
                        ) : (
                            <ul className="divide-y">
                                {posts.map((row) => (
                                    <Row
                                        key={row.id}
                                        title={row.title}
                                        meta={`${row.published_at ? (row.is_live ? `Published ${formatDateTime(row.published_at)}` : `Scheduled for ${formatDateTime(row.published_at)}`) : 'Draft'}${row.author ? ` · ${row.author}` : ''}`}
                                        live={row.is_live}
                                        url={row.url}
                                        onEdit={() =>
                                            setEditing({ kind: 'post', row })
                                        }
                                        onDelete={() =>
                                            setRemoving({ kind: 'post', row })
                                        }
                                    />
                                ))}
                            </ul>
                        )}
                    </TabsContent>

                    <TabsContent
                        value="events"
                        className="rounded-xl border bg-card shadow-xs"
                    >
                        {newButton('event')}
                        {events.length === 0 ? (
                            <EmptyState
                                icon={CalendarDays}
                                title="No events yet"
                            />
                        ) : (
                            <ul className="divide-y">
                                {events.map((row) => (
                                    <Row
                                        key={row.id}
                                        title={row.title}
                                        meta={`${formatDateTime(row.starts_at)}${row.venue ? ` · ${row.venue}` : ''}`}
                                        live={row.is_published}
                                        url={row.url}
                                        onEdit={() =>
                                            setEditing({ kind: 'event', row })
                                        }
                                        onDelete={() =>
                                            setRemoving({ kind: 'event', row })
                                        }
                                    />
                                ))}
                            </ul>
                        )}
                    </TabsContent>

                    <TabsContent
                        value="offers"
                        className="rounded-xl border bg-card shadow-xs"
                    >
                        {newButton('offer')}
                        {offers.length === 0 ? (
                            <EmptyState
                                icon={Tag}
                                title="No offers yet"
                                description="Link an offer to a public rate plan so the website books it at the right price."
                            />
                        ) : (
                            <ul className="divide-y">
                                {offers.map((row) => (
                                    <Row
                                        key={row.id}
                                        title={row.title}
                                        meta={[
                                            row.rate_plan
                                                ? `Rate: ${row.rate_plan}`
                                                : 'Standard rate',
                                            row.room_type,
                                            row.valid_until
                                                ? `until ${formatDate(row.valid_until)}`
                                                : null,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                        live={row.is_published}
                                        url={row.url}
                                        onEdit={() =>
                                            setEditing({ kind: 'offer', row })
                                        }
                                        onDelete={() =>
                                            setRemoving({ kind: 'offer', row })
                                        }
                                    />
                                ))}
                            </ul>
                        )}
                    </TabsContent>
                </Tabs>
            </div>

            <ContentDialog
                editing={editing}
                onClose={() => setEditing(null)}
                ratePlans={props.ratePlans}
                roomTypes={props.roomTypes}
            />
            <ConfirmDialog
                open={removing !== null}
                onOpenChange={(open) => !open && setRemoving(null)}
                title={`Remove “${removing?.row.title ?? ''}”?`}
                description="It disappears from the website immediately. Its address is kept reserved."
                confirmLabel="Remove"
                destructive
                processing={processing}
                onConfirm={() =>
                    removing &&
                    router.delete(
                        routesFor[removing.kind].destroy.url(removing.row.slug),
                        {
                            preserveScroll: true,
                            onStart: () => setProcessing(true),
                            onFinish: () => {
                                setProcessing(false);
                                setRemoving(null);
                            },
                        },
                    )
                }
            />
        </>
    );
}

type FormData = {
    title: string;
    slug: string;
    body: string;
    summary: string;
    excerpt: string;
    meta_title: string;
    meta_description: string;
    is_published: boolean;
    show_in_footer: boolean;
    display_order: string;
    published_at: string;
    venue: string;
    starts_at: string;
    ends_at: string;
    rate_plan_id: string;
    room_type_id: string;
    valid_from: string;
    valid_until: string;
    image: File | null;
    image_alt: string;
    cover_image_alt: string;
};

const blank: FormData = {
    title: '',
    slug: '',
    body: '',
    summary: '',
    excerpt: '',
    meta_title: '',
    meta_description: '',
    is_published: false,
    show_in_footer: false,
    display_order: '0',
    published_at: '',
    venue: '',
    starts_at: '',
    ends_at: '',
    rate_plan_id: '',
    room_type_id: '',
    valid_from: '',
    valid_until: '',
    image: null,
    image_alt: '',
    cover_image_alt: '',
};

function toForm(kind: Kind, row: AnyRow | null): FormData {
    if (row === null) {
        return blank;
    }

    const value = (input: unknown) =>
        typeof input === 'string' || typeof input === 'number'
            ? String(input)
            : '';
    const source = row as Record<string, unknown>;

    return {
        ...blank,
        ...Object.fromEntries(
            Object.keys(blank).map((key) => [
                key,
                typeof blank[key as keyof FormData] === 'boolean'
                    ? Boolean(source[key])
                    : value(source[key]),
            ]),
        ),
        image: null,
        is_published: kind === 'post' ? false : Boolean(source.is_published),
    } as FormData;
}

const fieldsFor: Record<Kind, (keyof FormData)[]> = {
    page: [
        'title',
        'slug',
        'body',
        'summary',
        'meta_title',
        'meta_description',
        'is_published',
        'show_in_footer',
        'display_order',
    ],
    post: [
        'title',
        'slug',
        'body',
        'excerpt',
        'meta_title',
        'meta_description',
        'published_at',
        'image',
        'cover_image_alt',
    ],
    event: [
        'title',
        'slug',
        'body',
        'summary',
        'venue',
        'starts_at',
        'ends_at',
        'is_published',
        'image',
        'image_alt',
    ],
    offer: [
        'title',
        'slug',
        'body',
        'summary',
        'rate_plan_id',
        'room_type_id',
        'valid_from',
        'valid_until',
        'is_published',
        'display_order',
        'image',
        'image_alt',
    ],
};

function ContentDialog({
    editing,
    onClose,
    ratePlans,
    roomTypes,
}: {
    editing: Editing | null;
    onClose: () => void;
    ratePlans: Props['ratePlans'];
    roomTypes: Props['roomTypes'];
}) {
    const form = useForm<FormData>(blank);
    const { data, setData, errors } = form;
    const kind = editing?.kind ?? 'page';
    const has = (field: keyof FormData) => fieldsFor[kind].includes(field);
    const row = editing?.row ?? null;
    const imageUrl = row && 'image_url' in row ? row.image_url : null;

    useEffect(() => {
        if (editing) {
            form.clearErrors();
            form.setData(toForm(editing.kind, editing.row));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [editing]);

    function submit() {
        if (!editing) {
            return;
        }

        const routes = routesFor[editing.kind];
        const options = {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
            onError: notifyValidationFailed,
        };

        form.transform((values) => {
            const payload: Record<string, unknown> = Object.fromEntries(
                fieldsFor[editing.kind].map((field) => [field, values[field]]),
            );

            if (!values.image) {
                delete payload.image;
            }

            return editing.row ? { ...payload, _method: 'put' } : payload;
        });

        form.post(
            editing.row
                ? routes.update.url(editing.row.slug)
                : routes.store.url(),
            options,
        );
    }

    return (
        <FormDialog
            open={editing !== null}
            onOpenChange={(open) => !open && onClose()}
            title={row ? `Edit ${labels[kind]}` : `New ${labels[kind]}`}
            size="xl"
            processing={form.processing}
            onSubmit={submit}
        >
            <FormErrorSummary errors={errors} />
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Title"
                    htmlFor="ct-title"
                    error={errors.title}
                    required
                >
                    <Input
                        id="ct-title"
                        value={data.title}
                        onChange={(event) =>
                            setData('title', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Web address"
                    htmlFor="ct-slug"
                    error={errors.slug}
                >
                    <Input
                        id="ct-slug"
                        value={data.slug}
                        placeholder="Generated from the title"
                        onChange={(event) =>
                            setData('slug', event.target.value)
                        }
                    />
                </Field>
            </div>
            {has('summary') && (
                <Field
                    label="Summary"
                    htmlFor="ct-summary"
                    error={errors.summary}
                >
                    <Textarea
                        id="ct-summary"
                        rows={2}
                        value={data.summary}
                        onChange={(event) =>
                            setData('summary', event.target.value)
                        }
                    />
                </Field>
            )}
            {has('excerpt') && (
                <Field
                    label="Excerpt"
                    htmlFor="ct-excerpt"
                    error={errors.excerpt}
                >
                    <Textarea
                        id="ct-excerpt"
                        rows={2}
                        value={data.excerpt}
                        onChange={(event) =>
                            setData('excerpt', event.target.value)
                        }
                    />
                </Field>
            )}
            <Field
                label="Content (Markdown)"
                htmlFor="ct-body"
                error={errors.body}
                required={kind === 'page' || kind === 'post'}
            >
                <Textarea
                    id="ct-body"
                    rows={10}
                    className="font-mono text-sm"
                    value={data.body}
                    onChange={(event) => setData('body', event.target.value)}
                    placeholder={
                        '## Heading\n\nParagraph text with **bold** and [links](https://example.com).'
                    }
                />
            </Field>

            <div className="grid gap-4 sm:grid-cols-2">
                {has('venue') && (
                    <Field
                        label="Venue"
                        htmlFor="ct-venue"
                        error={errors.venue}
                    >
                        <Input
                            id="ct-venue"
                            value={data.venue}
                            onChange={(event) =>
                                setData('venue', event.target.value)
                            }
                        />
                    </Field>
                )}
                {has('starts_at') && (
                    <Field
                        label="Starts"
                        htmlFor="ct-starts"
                        error={errors.starts_at}
                        required
                    >
                        <Input
                            id="ct-starts"
                            type="datetime-local"
                            value={data.starts_at}
                            onChange={(event) =>
                                setData('starts_at', event.target.value)
                            }
                        />
                    </Field>
                )}
                {has('ends_at') && (
                    <Field
                        label="Ends"
                        htmlFor="ct-ends"
                        error={errors.ends_at}
                    >
                        <Input
                            id="ct-ends"
                            type="datetime-local"
                            value={data.ends_at}
                            onChange={(event) =>
                                setData('ends_at', event.target.value)
                            }
                        />
                    </Field>
                )}
                {has('rate_plan_id') && (
                    <Field
                        label="Rate plan applied"
                        htmlFor="ct-plan"
                        error={errors.rate_plan_id}
                    >
                        <OptionSelect
                            id="ct-plan"
                            value={data.rate_plan_id}
                            placeholder="Standard rate"
                            options={ratePlans.map((plan) => ({
                                value: String(plan.id),
                                label: plan.name,
                            }))}
                            onChange={(value) => setData('rate_plan_id', value)}
                        />
                    </Field>
                )}
                {has('room_type_id') && (
                    <Field
                        label="Room type"
                        htmlFor="ct-room"
                        error={errors.room_type_id}
                    >
                        <OptionSelect
                            id="ct-room"
                            value={data.room_type_id}
                            placeholder="Any room type"
                            options={roomTypes.map((type) => ({
                                value: String(type.id),
                                label: type.name,
                            }))}
                            onChange={(value) => setData('room_type_id', value)}
                        />
                    </Field>
                )}
                {has('valid_from') && (
                    <Field
                        label="Arrivals from"
                        htmlFor="ct-from"
                        error={errors.valid_from}
                    >
                        <Input
                            id="ct-from"
                            type="date"
                            value={data.valid_from}
                            onChange={(event) =>
                                setData('valid_from', event.target.value)
                            }
                        />
                    </Field>
                )}
                {has('valid_until') && (
                    <Field
                        label="Arrivals until"
                        htmlFor="ct-until"
                        error={errors.valid_until}
                    >
                        <Input
                            id="ct-until"
                            type="date"
                            value={data.valid_until}
                            onChange={(event) =>
                                setData('valid_until', event.target.value)
                            }
                        />
                    </Field>
                )}
                {has('published_at') && (
                    <Field
                        label="Publish at (blank = draft)"
                        htmlFor="ct-published"
                        error={errors.published_at}
                    >
                        <Input
                            id="ct-published"
                            type="datetime-local"
                            value={data.published_at}
                            onChange={(event) =>
                                setData('published_at', event.target.value)
                            }
                        />
                    </Field>
                )}
                {has('display_order') && (
                    <Field
                        label="Display order"
                        htmlFor="ct-order"
                        error={errors.display_order}
                        required
                    >
                        <Input
                            id="ct-order"
                            type="number"
                            min={0}
                            value={data.display_order}
                            onChange={(event) =>
                                setData('display_order', event.target.value)
                            }
                        />
                    </Field>
                )}
                {has('meta_title') && (
                    <Field
                        label="SEO title"
                        htmlFor="ct-meta-title"
                        error={errors.meta_title}
                    >
                        <Input
                            id="ct-meta-title"
                            maxLength={70}
                            value={data.meta_title}
                            onChange={(event) =>
                                setData('meta_title', event.target.value)
                            }
                        />
                    </Field>
                )}
                {has('meta_description') && (
                    <Field
                        label="SEO description"
                        htmlFor="ct-meta-description"
                        error={errors.meta_description}
                    >
                        <Input
                            id="ct-meta-description"
                            maxLength={300}
                            value={data.meta_description}
                            onChange={(event) =>
                                setData('meta_description', event.target.value)
                            }
                        />
                    </Field>
                )}
            </div>

            {has('image') && (
                <div className="grid gap-4 sm:grid-cols-2">
                    <ImagePicker
                        label={imageUrl ? 'Replace image' : 'Image'}
                        files={data.image ? [data.image] : []}
                        onFilesChange={(files) =>
                            setData('image', files[0] ?? null)
                        }
                        error={fieldError(errors, 'image')}
                    />
                    {kind === 'post' ? (
                        <Field
                            label="Image description"
                            htmlFor="ct-alt"
                            error={errors.cover_image_alt}
                        >
                            <Input
                                id="ct-alt"
                                value={data.cover_image_alt}
                                onChange={(event) =>
                                    setData(
                                        'cover_image_alt',
                                        event.target.value,
                                    )
                                }
                            />
                        </Field>
                    ) : (
                        <Field
                            label="Image description"
                            htmlFor="ct-alt"
                            error={errors.image_alt}
                        >
                            <Input
                                id="ct-alt"
                                value={data.image_alt}
                                onChange={(event) =>
                                    setData('image_alt', event.target.value)
                                }
                            />
                        </Field>
                    )}
                </div>
            )}

            <div className="flex flex-wrap gap-6">
                {has('is_published') && (
                    <div className="flex items-center gap-2">
                        <Switch
                            id="ct-published-flag"
                            checked={data.is_published}
                            onCheckedChange={(checked) =>
                                setData('is_published', checked)
                            }
                        />
                        <Label htmlFor="ct-published-flag">Published</Label>
                    </div>
                )}
                {has('show_in_footer') && (
                    <div className="flex items-center gap-2">
                        <Switch
                            id="ct-footer"
                            checked={data.show_in_footer}
                            onCheckedChange={(checked) =>
                                setData('show_in_footer', checked)
                            }
                        />
                        <Label htmlFor="ct-footer">Link in footer</Label>
                    </div>
                )}
            </div>
        </FormDialog>
    );
}

WebsiteContent.layout = {
    breadcrumbs: [
        { title: 'Website content', href: admin.website.content.index() },
    ],
};
