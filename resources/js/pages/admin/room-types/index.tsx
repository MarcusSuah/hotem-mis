import { Head, router, useForm } from '@inertiajs/react';
import {
    BedDouble,
    Pencil,
    Plus,
    Ruler,
    Star,
    Trash2,
    Users,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import { EmptyState } from '@/components/admin/empty-state';
import { Field } from '@/components/admin/field';
import { FormDialog } from '@/components/admin/form-dialog';
import { FormErrorSummary } from '@/components/admin/form-error-summary';
import { ImagePicker } from '@/components/admin/image-picker';
import type { ExistingImage } from '@/components/admin/image-picker';
import { PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { fieldError, notifyValidationFailed } from '@/lib/form';
import admin from '@/routes/admin';
import type { MoneyValue } from '@/types';

type RoomType = {
    id: number;
    name: string;
    slug: string;
    short_description: string | null;
    description: string | null;
    featured_image_url: string | null;
    featured_image_alt: string | null;
    max_adults: number;
    max_children: number;
    max_occupancy: number;
    included_adults: number;
    included_children: number;
    bed_type: string;
    bed_count: number;
    size_sqm: string | null;
    bathroom_details: string | null;
    base_rate: MoneyValue;
    weekend_rate: MoneyValue | null;
    extra_adult_rate: MoneyValue;
    extra_child_rate: MoneyValue;
    is_active: boolean;
    is_featured: boolean;
    display_order: number;
    meta_title: string | null;
    meta_description: string | null;
    rooms_count: number;
    amenity_ids: number[];
    amenities: string[];
    images: ExistingImage[];
};

type Amenity = { id: number; name: string };

export default function RoomTypesIndex({
    roomTypes,
    amenities,
    can,
}: {
    roomTypes: RoomType[];
    amenities: Amenity[];
    can: { manage: boolean };
}) {
    const [editing, setEditing] = useState<RoomType | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [deleting, setDeleting] = useState<RoomType | null>(null);
    const [processing, setProcessing] = useState(false);

    function remove() {
        if (!deleting) {
            return;
        }

        router.delete(admin.roomTypes.destroy.url(deleting.slug), {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setDeleting(null);
            },
        });
    }

    return (
        <>
            <Head title="Room types" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Room types"
                    description="Categories of rooms with their capacity, standard rates, amenities and photography."
                    actions={
                        can.manage && (
                            <Button
                                onClick={() => {
                                    setEditing(null);
                                    setFormOpen(true);
                                }}
                            >
                                <Plus />
                                New room type
                            </Button>
                        )
                    }
                />

                {roomTypes.length === 0 ? (
                    <div className="rounded-xl border bg-card">
                        <EmptyState
                            icon={BedDouble}
                            title="No room types yet"
                            description="Create your first room category, then add rooms to it."
                        />
                    </div>
                ) : (
                    <div className="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                        {roomTypes.map((type) => (
                            <article
                                key={type.id}
                                className="flex flex-col overflow-hidden rounded-xl border bg-card shadow-xs"
                            >
                                <div className="relative aspect-[16/9] bg-sage dark:bg-muted">
                                    {type.featured_image_url ? (
                                        <img
                                            src={type.featured_image_url}
                                            alt={
                                                type.featured_image_alt ??
                                                type.name
                                            }
                                            className="size-full object-cover"
                                            loading="lazy"
                                        />
                                    ) : (
                                        <div className="flex size-full items-center justify-center text-primary/40">
                                            <BedDouble className="size-12" />
                                        </div>
                                    )}
                                    <div className="absolute top-3 left-3 flex gap-1.5">
                                        {type.is_featured && (
                                            <Badge
                                                variant="gold"
                                                className="bg-highlight text-highlight-foreground"
                                            >
                                                <Star />
                                                Featured
                                            </Badge>
                                        )}
                                        {!type.is_active && (
                                            <Badge variant="muted">
                                                Inactive
                                            </Badge>
                                        )}
                                    </div>
                                </div>
                                <div className="flex flex-1 flex-col gap-3 p-5">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <h2 className="font-display text-2xl font-semibold">
                                                {type.name}
                                            </h2>
                                            <p className="text-sm text-muted-foreground">
                                                {type.rooms_count}{' '}
                                                {type.rooms_count === 1
                                                    ? 'room'
                                                    : 'rooms'}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="font-display text-xl font-semibold text-primary dark:text-highlight">
                                                {type.base_rate.formatted}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                per night
                                            </p>
                                        </div>
                                    </div>
                                    {type.short_description && (
                                        <p className="line-clamp-2 text-sm text-muted-foreground">
                                            {type.short_description}
                                        </p>
                                    )}
                                    <div className="flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                                        <span className="flex items-center gap-1.5">
                                            <Users className="size-4 text-highlight" />{' '}
                                            Up to {type.max_occupancy}
                                        </span>
                                        <span className="flex items-center gap-1.5">
                                            <BedDouble className="size-4 text-highlight" />{' '}
                                            {type.bed_count} × {type.bed_type}
                                        </span>
                                        {type.size_sqm && (
                                            <span className="flex items-center gap-1.5">
                                                <Ruler className="size-4 text-highlight" />{' '}
                                                {Number(type.size_sqm)} m²
                                            </span>
                                        )}
                                    </div>
                                    {can.manage && (
                                        <div className="mt-auto flex gap-2 pt-2">
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                onClick={() => {
                                                    setEditing(type);
                                                    setFormOpen(true);
                                                }}
                                            >
                                                <Pencil />
                                                Edit
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="text-destructive"
                                                onClick={() =>
                                                    setDeleting(type)
                                                }
                                            >
                                                <Trash2 />
                                                Delete
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            </article>
                        ))}
                    </div>
                )}
            </div>

            <RoomTypeFormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                roomType={editing}
                amenities={amenities}
            />

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title={`Delete ${deleting?.name}?`}
                description="Room types that still have rooms cannot be deleted. Existing reservations keep their history."
                confirmLabel="Delete room type"
                destructive
                processing={processing}
                onConfirm={remove}
            />
        </>
    );
}

type RoomTypeForm = {
    name: string;
    slug: string;
    short_description: string;
    description: string;
    max_adults: string;
    max_children: string;
    max_occupancy: string;
    included_adults: string;
    included_children: string;
    bed_type: string;
    bed_count: string;
    size_sqm: string;
    bathroom_details: string;
    base_rate: string;
    weekend_rate: string;
    extra_adult_rate: string;
    extra_child_rate: string;
    is_active: boolean;
    is_featured: boolean;
    display_order: string;
    meta_title: string;
    meta_description: string;
    amenity_ids: number[];
    featured_image: File | null;
    featured_image_alt: string;
    gallery: File[];
    remove_image_ids: number[];
};

/** Form fields edited through plain text inputs. */
type TextField = {
    [K in keyof RoomTypeForm]: RoomTypeForm[K] extends string ? K : never;
}[keyof RoomTypeForm];

const blank: RoomTypeForm = {
    name: '',
    slug: '',
    short_description: '',
    description: '',
    max_adults: '2',
    max_children: '1',
    max_occupancy: '3',
    included_adults: '2',
    included_children: '0',
    bed_type: 'King',
    bed_count: '1',
    size_sqm: '',
    bathroom_details: '',
    base_rate: '',
    weekend_rate: '',
    extra_adult_rate: '0',
    extra_child_rate: '0',
    is_active: true,
    is_featured: false,
    display_order: '0',
    meta_title: '',
    meta_description: '',
    amenity_ids: [],
    featured_image: null,
    featured_image_alt: '',
    gallery: [],
    remove_image_ids: [],
};

function RoomTypeFormDialog({
    open,
    onOpenChange,
    roomType,
    amenities,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    roomType: RoomType | null;
    amenities: Amenity[];
}) {
    const form = useForm<RoomTypeForm>(blank);
    const { data, setData, errors } = form;

    useEffect(() => {
        if (!open) {
            return;
        }

        form.clearErrors();
        form.setData(
            roomType
                ? {
                      ...blank,
                      name: roomType.name,
                      slug: roomType.slug,
                      short_description: roomType.short_description ?? '',
                      description: roomType.description ?? '',
                      max_adults: String(roomType.max_adults),
                      max_children: String(roomType.max_children),
                      max_occupancy: String(roomType.max_occupancy),
                      included_adults: String(roomType.included_adults),
                      included_children: String(roomType.included_children),
                      bed_type: roomType.bed_type,
                      bed_count: String(roomType.bed_count),
                      size_sqm: roomType.size_sqm ?? '',
                      bathroom_details: roomType.bathroom_details ?? '',
                      base_rate: roomType.base_rate.amount,
                      weekend_rate: roomType.weekend_rate?.amount ?? '',
                      extra_adult_rate: roomType.extra_adult_rate.amount,
                      extra_child_rate: roomType.extra_child_rate.amount,
                      is_active: roomType.is_active,
                      is_featured: roomType.is_featured,
                      display_order: String(roomType.display_order),
                      meta_title: roomType.meta_title ?? '',
                      meta_description: roomType.meta_description ?? '',
                      amenity_ids: roomType.amenity_ids,
                      featured_image_alt: roomType.featured_image_alt ?? '',
                  }
                : blank,
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, roomType?.id]);

    function submit() {
        const options = {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
            onError: notifyValidationFailed,
        };

        if (roomType) {
            form.transform((values) => ({ ...values, _method: 'put' }));
            form.post(admin.roomTypes.update.url(roomType.slug), options);
        } else {
            form.transform((values) => values);
            form.post(admin.roomTypes.store.url(), options);
        }
    }

    function text(
        field: TextField,
        label: string,
        props: React.ComponentProps<typeof Input> = {},
        required = false,
        hint?: string,
    ) {
        const id = `rt-${field}`;

        return (
            <Field
                label={label}
                htmlFor={id}
                error={errors[field]}
                required={required}
                hint={hint}
            >
                <Input
                    id={id}
                    value={data[field]}
                    onChange={(event) => setData(field, event.target.value)}
                    aria-invalid={Boolean(errors[field])}
                    {...props}
                />
            </Field>
        );
    }

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={roomType ? `Edit ${roomType.name}` : 'New room type'}
            description="Rates are in the hotel's base currency and apply to new bookings only."
            submitLabel={roomType ? 'Save changes' : 'Create room type'}
            processing={form.processing}
            onSubmit={submit}
            size="xl"
        >
            <FormErrorSummary errors={errors} />

            <section className="grid gap-4 sm:grid-cols-2">
                {text(
                    'name',
                    'Name',
                    { placeholder: 'Deluxe Ocean King' },
                    true,
                )}
                {text(
                    'slug',
                    'URL slug',
                    { placeholder: 'generated from the name' },
                    false,
                    'Leave blank to keep or generate automatically.',
                )}
                <Field
                    label="Short description"
                    htmlFor="rt-short"
                    error={errors.short_description}
                    className="sm:col-span-2"
                >
                    <Input
                        id="rt-short"
                        value={data.short_description}
                        onChange={(event) =>
                            setData('short_description', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Description"
                    htmlFor="rt-description"
                    error={errors.description}
                    className="sm:col-span-2"
                >
                    <Textarea
                        id="rt-description"
                        rows={4}
                        value={data.description}
                        onChange={(event) =>
                            setData('description', event.target.value)
                        }
                    />
                </Field>
            </section>

            <section className="space-y-3">
                <h3 className="font-display text-lg font-semibold">
                    Capacity & layout
                </h3>
                <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
                    {text(
                        'max_adults',
                        'Max adults',
                        { type: 'number', min: 1 },
                        true,
                    )}
                    {text(
                        'max_children',
                        'Max children',
                        { type: 'number', min: 0 },
                        true,
                    )}
                    {text(
                        'max_occupancy',
                        'Max guests',
                        { type: 'number', min: 1 },
                        true,
                    )}
                    {text(
                        'included_adults',
                        'Adults in rate',
                        { type: 'number', min: 1 },
                        true,
                    )}
                    {text(
                        'included_children',
                        'Children in rate',
                        { type: 'number', min: 0 },
                        true,
                    )}
                    {text('bed_type', 'Bed type', {}, true)}
                    {text(
                        'bed_count',
                        'Beds',
                        { type: 'number', min: 1 },
                        true,
                    )}
                    {text('size_sqm', 'Size (m²)', {
                        type: 'number',
                        step: '0.1',
                    })}
                    <div className="sm:col-span-2">
                        {text('bathroom_details', 'Bathroom')}
                    </div>
                </div>
            </section>

            <section className="space-y-3">
                <h3 className="font-display text-lg font-semibold">
                    Rates per night
                </h3>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {text(
                        'base_rate',
                        'Standard rate',
                        { inputMode: 'decimal', placeholder: '150.00' },
                        true,
                    )}
                    {text('weekend_rate', 'Fri & Sat rate', {
                        inputMode: 'decimal',
                        placeholder: 'same as standard',
                    })}
                    {text(
                        'extra_adult_rate',
                        'Extra adult',
                        { inputMode: 'decimal' },
                        true,
                    )}
                    {text(
                        'extra_child_rate',
                        'Extra child',
                        { inputMode: 'decimal' },
                        true,
                    )}
                </div>
            </section>

            <section className="space-y-3">
                <h3 className="font-display text-lg font-semibold">
                    Amenities
                </h3>
                {amenities.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Add amenities under Rates & Policies.
                    </p>
                ) : (
                    <div className="grid gap-2 sm:grid-cols-3">
                        {amenities.map((amenity) => (
                            <label
                                key={amenity.id}
                                htmlFor={`amenity-${amenity.id}`}
                                className="flex items-center gap-2 text-sm"
                            >
                                <Checkbox
                                    id={`amenity-${amenity.id}`}
                                    checked={data.amenity_ids.includes(
                                        amenity.id,
                                    )}
                                    onCheckedChange={(checked) =>
                                        setData(
                                            'amenity_ids',
                                            checked === true
                                                ? [
                                                      ...data.amenity_ids,
                                                      amenity.id,
                                                  ]
                                                : data.amenity_ids.filter(
                                                      (id) => id !== amenity.id,
                                                  ),
                                        )
                                    }
                                />
                                {amenity.name}
                            </label>
                        ))}
                    </div>
                )}
            </section>

            <section className="grid gap-4 sm:grid-cols-2">
                <ImagePicker
                    label="Featured image"
                    files={data.featured_image ? [data.featured_image] : []}
                    onFilesChange={(files) =>
                        setData('featured_image', files[0] ?? null)
                    }
                    existing={
                        roomType?.featured_image_url && !data.featured_image
                            ? [
                                  {
                                      id: 0,
                                      url: roomType.featured_image_url,
                                      alt_text: roomType.featured_image_alt,
                                  },
                              ]
                            : []
                    }
                    error={errors.featured_image}
                />
                {text(
                    'featured_image_alt',
                    'Featured image description (alt text)',
                    { placeholder: 'King bed facing the ocean at sunset' },
                )}
                <div className="sm:col-span-2">
                    <ImagePicker
                        label="Gallery"
                        multiple
                        files={data.gallery}
                        onFilesChange={(files) => setData('gallery', files)}
                        existing={roomType?.images ?? []}
                        removedIds={data.remove_image_ids}
                        onToggleRemove={(id) =>
                            setData(
                                'remove_image_ids',
                                data.remove_image_ids.includes(id)
                                    ? data.remove_image_ids.filter(
                                          (value) => value !== id,
                                      )
                                    : [...data.remove_image_ids, id],
                            )
                        }
                        error={fieldError(errors, 'gallery')}
                    />
                </div>
            </section>

            <section className="space-y-3">
                <h3 className="font-display text-lg font-semibold">
                    Visibility & SEO
                </h3>
                <div className="flex flex-wrap gap-6">
                    <div className="flex items-center gap-2">
                        <Switch
                            id="rt-active"
                            checked={data.is_active}
                            onCheckedChange={(checked) =>
                                setData('is_active', checked)
                            }
                        />
                        <Label htmlFor="rt-active">Bookable</Label>
                    </div>
                    <div className="flex items-center gap-2">
                        <Switch
                            id="rt-featured"
                            checked={data.is_featured}
                            onCheckedChange={(checked) =>
                                setData('is_featured', checked)
                            }
                        />
                        <Label htmlFor="rt-featured">Featured on website</Label>
                    </div>
                </div>
                <div className="grid gap-4 sm:grid-cols-3">
                    {text(
                        'display_order',
                        'Display order',
                        { type: 'number', min: 0 },
                        true,
                    )}
                    <div className="sm:col-span-2">
                        {text('meta_title', 'SEO title')}
                    </div>
                    <div className="sm:col-span-3">
                        {text('meta_description', 'SEO description')}
                    </div>
                </div>
            </section>
        </FormDialog>
    );
}

RoomTypesIndex.layout = {
    breadcrumbs: [{ title: 'Room types', href: admin.roomTypes.index() }],
};
