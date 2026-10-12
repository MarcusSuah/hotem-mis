import { Head, router, useForm } from '@inertiajs/react';
import { ImagePlus, Images } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import { EmptyState } from '@/components/admin/empty-state';
import { Field } from '@/components/admin/field';
import { FormDialog } from '@/components/admin/form-dialog';
import { ImagePicker } from '@/components/admin/image-picker';
import { OptionSelect } from '@/components/admin/option-select';
import type { Option } from '@/components/admin/option-select';
import { PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { fieldError, notifyValidationFailed } from '@/lib/form';
import admin from '@/routes/admin';

type Photo = {
    id: number;
    alt_text: string;
    caption: string | null;
    display_order: number;
    is_published: boolean;
    category: string;
    category_label: string;
    url: string;
};

export default function GalleryAdmin({
    images,
    categories,
}: {
    images: Photo[];
    categories: Option[];
}) {
    const [uploading, setUploading] = useState(false);
    const [editing, setEditing] = useState<Photo | null>(null);
    const [removing, setRemoving] = useState<Photo | null>(null);
    const [processing, setProcessing] = useState(false);

    return (
        <>
            <Head title="Gallery" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Gallery"
                    description="Photos shown on the website gallery and homepage."
                    actions={
                        <Button onClick={() => setUploading(true)}>
                            <ImagePlus />
                            Add photos
                        </Button>
                    }
                />
                {images.length === 0 ? (
                    <div className="rounded-xl border bg-card shadow-xs">
                        <EmptyState icon={Images} title="No photos yet" />
                    </div>
                ) : (
                    <div className="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4">
                        {images.map((image) => (
                            <figure
                                key={image.id}
                                className="overflow-hidden rounded-xl border bg-card shadow-xs"
                            >
                                <img
                                    src={image.url}
                                    alt={image.alt_text}
                                    className="aspect-[4/3] w-full object-cover"
                                    loading="lazy"
                                />
                                <figcaption className="space-y-2 p-3 text-sm">
                                    <p className="line-clamp-1">
                                        {image.alt_text}
                                    </p>
                                    <div className="flex flex-wrap items-center gap-1">
                                        <Badge variant="secondary">
                                            {image.category_label}
                                        </Badge>
                                        {!image.is_published && (
                                            <Badge variant="muted">
                                                Hidden
                                            </Badge>
                                        )}
                                    </div>
                                    <div className="flex gap-1">
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() => setEditing(image)}
                                        >
                                            Edit
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="text-destructive"
                                            onClick={() => setRemoving(image)}
                                        >
                                            Remove
                                        </Button>
                                    </div>
                                </figcaption>
                            </figure>
                        ))}
                    </div>
                )}
            </div>

            <UploadDialog
                open={uploading}
                onClose={() => setUploading(false)}
                categories={categories}
            />
            <EditDialog
                image={editing}
                onClose={() => setEditing(null)}
                categories={categories}
            />
            <ConfirmDialog
                open={removing !== null}
                onOpenChange={(open) => !open && setRemoving(null)}
                title="Remove this photo?"
                description="The file is deleted and the photo disappears from the website."
                confirmLabel="Remove"
                destructive
                processing={processing}
                onConfirm={() =>
                    removing &&
                    router.delete(
                        admin.website.gallery.destroy.url(removing.id),
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

function UploadDialog({
    open,
    onClose,
    categories,
}: {
    open: boolean;
    onClose: () => void;
    categories: Option[];
}) {
    const form = useForm<{
        photos: File[];
        category: string;
        alt_text: string;
    }>({ photos: [], category: categories[0]?.value ?? '', alt_text: '' });

    useEffect(() => {
        if (open) {
            form.reset();
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    return (
        <FormDialog
            open={open}
            onOpenChange={(next) => !next && onClose()}
            title="Add photos"
            description="Up to 20 photos at a time. You can refine each description afterwards."
            submitLabel="Upload"
            processing={form.processing}
            onSubmit={() =>
                form.post(admin.website.gallery.store.url(), {
                    forceFormData: true,
                    preserveScroll: true,
                    onSuccess: onClose,
                    onError: notifyValidationFailed,
                })
            }
        >
            <ImagePicker
                label="Photos"
                multiple
                files={form.data.photos}
                onFilesChange={(files) => form.setData('photos', files)}
                error={fieldError(form.errors, 'photos')}
            />
            <Field
                label="Category"
                htmlFor="g-category"
                error={form.errors.category}
                required
            >
                <OptionSelect
                    id="g-category"
                    value={form.data.category}
                    options={categories}
                    onChange={(value) => form.setData('category', value)}
                />
            </Field>
            <Field
                label="Description for screen readers"
                htmlFor="g-alt"
                error={form.errors.alt_text}
                required
            >
                <Input
                    id="g-alt"
                    value={form.data.alt_text}
                    onChange={(event) =>
                        form.setData('alt_text', event.target.value)
                    }
                    placeholder="Ocean-view suite with balcony"
                />
            </Field>
        </FormDialog>
    );
}

function EditDialog({
    image,
    onClose,
    categories,
}: {
    image: Photo | null;
    onClose: () => void;
    categories: Option[];
}) {
    const form = useForm({
        alt_text: '',
        caption: '',
        category: '',
        display_order: '0',
        is_published: true,
    });

    useEffect(() => {
        if (image) {
            form.clearErrors();
            form.setData({
                alt_text: image.alt_text,
                caption: image.caption ?? '',
                category: image.category,
                display_order: String(image.display_order),
                is_published: image.is_published,
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [image]);

    return (
        <FormDialog
            open={image !== null}
            onOpenChange={(open) => !open && onClose()}
            title="Edit photo"
            processing={form.processing}
            onSubmit={() =>
                image &&
                form.put(admin.website.gallery.update.url(image.id), {
                    preserveScroll: true,
                    onSuccess: onClose,
                    onError: notifyValidationFailed,
                })
            }
        >
            <Field
                label="Description"
                htmlFor="ge-alt"
                error={form.errors.alt_text}
                required
            >
                <Input
                    id="ge-alt"
                    value={form.data.alt_text}
                    onChange={(event) =>
                        form.setData('alt_text', event.target.value)
                    }
                />
            </Field>
            <Field
                label="Caption"
                htmlFor="ge-caption"
                error={form.errors.caption}
            >
                <Input
                    id="ge-caption"
                    value={form.data.caption}
                    onChange={(event) =>
                        form.setData('caption', event.target.value)
                    }
                />
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Category"
                    htmlFor="ge-category"
                    error={form.errors.category}
                    required
                >
                    <OptionSelect
                        id="ge-category"
                        value={form.data.category}
                        options={categories}
                        onChange={(value) => form.setData('category', value)}
                    />
                </Field>
                <Field
                    label="Display order"
                    htmlFor="ge-order"
                    error={form.errors.display_order}
                    required
                >
                    <Input
                        id="ge-order"
                        type="number"
                        min={0}
                        value={form.data.display_order}
                        onChange={(event) =>
                            form.setData('display_order', event.target.value)
                        }
                    />
                </Field>
            </div>
            <div className="flex items-center gap-2">
                <Switch
                    id="ge-published"
                    checked={form.data.is_published}
                    onCheckedChange={(checked) =>
                        form.setData('is_published', checked)
                    }
                />
                <Label htmlFor="ge-published">Shown on the website</Label>
            </div>
        </FormDialog>
    );
}

GalleryAdmin.layout = {
    breadcrumbs: [{ title: 'Gallery', href: admin.website.gallery.index() }],
};
