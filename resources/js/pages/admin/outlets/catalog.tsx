import { Head, router, useForm } from '@inertiajs/react';
import { ConciergeBell, FolderPlus, Plus, Star, Utensils } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ConfirmDialog } from '@/components/admin/confirm-dialog';
import { EmptyState } from '@/components/admin/empty-state';
import { Field } from '@/components/admin/field';
import { FormDialog } from '@/components/admin/form-dialog';
import { FormErrorSummary } from '@/components/admin/form-error-summary';
import { ImagePicker } from '@/components/admin/image-picker';
import { OptionSelect } from '@/components/admin/option-select';
import type { Option } from '@/components/admin/option-select';
import { PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { fieldError, notifyValidationFailed } from '@/lib/form';
import admin from '@/routes/admin';
import type { MoneyValue } from '@/types';

type Category = {
    id: number;
    name: string;
    slug: string;
    display_order: number;
    is_active: boolean;
    items_count: number;
};

type MenuItem = {
    id: number;
    slug: string;
    menu_category_id: number;
    name: string;
    description: string | null;
    is_available: boolean;
    is_featured: boolean;
    display_order: number;
    category: string;
    price: MoneyValue;
    image_url: string | null;
};

type Service = {
    id: number;
    slug: string;
    name: string;
    short_description: string | null;
    description: string | null;
    unit: string;
    is_active: boolean;
    is_featured: boolean;
    display_order: number;
    category: string;
    category_label: string;
    price: MoneyValue;
    image_url: string | null;
};

type Props = {
    categories: Category[];
    menuItems: MenuItem[];
    services: Service[];
    serviceCategories: Option[];
    currency: string;
};

type Removal = { kind: 'item' | 'service'; slug: string; name: string };

export default function Catalog({
    categories,
    menuItems,
    services,
    serviceCategories,
    currency,
}: Props) {
    const [category, setCategory] = useState<Category | null | undefined>(
        undefined,
    );
    const [item, setItem] = useState<MenuItem | null | undefined>(undefined);
    const [service, setService] = useState<Service | null | undefined>(
        undefined,
    );
    const [removing, setRemoving] = useState<Removal | null>(null);
    const [processing, setProcessing] = useState(false);

    function remove() {
        if (!removing) {
            return;
        }

        const url =
            removing.kind === 'item'
                ? admin.catalog.menuItems.destroy.url(removing.slug)
                : admin.catalog.services.destroy.url(removing.slug);
        router.delete(url, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => {
                setProcessing(false);
                setRemoving(null);
            },
        });
    }

    return (
        <>
            <Head title="Menu & services" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Menu & services"
                    description={`What outlets can sell and what the website shows. Prices in ${currency}.`}
                />

                <Tabs defaultValue="menu" className="gap-4">
                    <TabsList>
                        <TabsTrigger value="menu">Menu</TabsTrigger>
                        <TabsTrigger value="services">Services</TabsTrigger>
                    </TabsList>

                    <TabsContent value="menu" className="space-y-4">
                        <div className="flex flex-wrap justify-end gap-2">
                            <Button
                                variant="outline"
                                onClick={() => setCategory(null)}
                            >
                                <FolderPlus />
                                New category
                            </Button>
                            <Button
                                onClick={() => setItem(null)}
                                disabled={categories.length === 0}
                            >
                                <Plus />
                                New menu item
                            </Button>
                        </div>
                        {categories.length === 0 ? (
                            <div className="rounded-xl border bg-card shadow-xs">
                                <EmptyState
                                    icon={Utensils}
                                    title="No menu yet"
                                    description="Start with a category such as Breakfast or Cocktails."
                                />
                            </div>
                        ) : (
                            categories.map((row) => (
                                <section
                                    key={row.id}
                                    className="rounded-xl border bg-card shadow-xs"
                                >
                                    <header className="flex items-center justify-between gap-2 border-b px-4 py-3">
                                        <h2 className="font-display text-lg font-semibold">
                                            {row.name}{' '}
                                            {!row.is_active && (
                                                <Badge variant="muted">
                                                    Hidden
                                                </Badge>
                                            )}
                                        </h2>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => setCategory(row)}
                                        >
                                            Edit
                                        </Button>
                                    </header>
                                    <ul className="divide-y">
                                        {menuItems
                                            .filter(
                                                (menuItem) =>
                                                    menuItem.menu_category_id ===
                                                    row.id,
                                            )
                                            .map((menuItem) => (
                                                <li
                                                    key={menuItem.id}
                                                    className="flex items-center gap-3 px-4 py-2 text-sm"
                                                >
                                                    {menuItem.image_url ? (
                                                        <img
                                                            src={
                                                                menuItem.image_url
                                                            }
                                                            alt=""
                                                            className="size-10 rounded object-cover"
                                                            loading="lazy"
                                                        />
                                                    ) : (
                                                        <span className="size-10 rounded bg-muted" />
                                                    )}
                                                    <div className="min-w-0 flex-1">
                                                        <p className="font-medium">
                                                            {menuItem.name}{' '}
                                                            {menuItem.is_featured && (
                                                                <Star
                                                                    className="inline size-3 fill-highlight text-highlight"
                                                                    aria-label="Featured"
                                                                />
                                                            )}
                                                            {!menuItem.is_available && (
                                                                <Badge
                                                                    variant="muted"
                                                                    className="ml-1"
                                                                >
                                                                    Unavailable
                                                                </Badge>
                                                            )}
                                                        </p>
                                                        {menuItem.description && (
                                                            <p className="truncate text-xs text-muted-foreground">
                                                                {
                                                                    menuItem.description
                                                                }
                                                            </p>
                                                        )}
                                                    </div>
                                                    <span className="font-medium">
                                                        {
                                                            menuItem.price
                                                                .formatted
                                                        }
                                                    </span>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            setItem(menuItem)
                                                        }
                                                    >
                                                        Edit
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        className="text-destructive"
                                                        onClick={() =>
                                                            setRemoving({
                                                                kind: 'item',
                                                                slug: menuItem.slug,
                                                                name: menuItem.name,
                                                            })
                                                        }
                                                    >
                                                        Remove
                                                    </Button>
                                                </li>
                                            ))}
                                        {row.items_count === 0 && (
                                            <li className="px-4 py-3 text-sm text-muted-foreground">
                                                No items in this category.
                                            </li>
                                        )}
                                    </ul>
                                </section>
                            ))
                        )}
                    </TabsContent>

                    <TabsContent value="services" className="space-y-4">
                        <div className="flex justify-end">
                            <Button onClick={() => setService(null)}>
                                <Plus />
                                New service
                            </Button>
                        </div>
                        <div className="rounded-xl border bg-card shadow-xs">
                            {services.length === 0 ? (
                                <EmptyState
                                    icon={ConciergeBell}
                                    title="No services yet"
                                    description="Spa treatments, airport transfers, laundry and more."
                                />
                            ) : (
                                <ul className="divide-y">
                                    {services.map((row) => (
                                        <li
                                            key={row.id}
                                            className="flex items-center gap-3 px-4 py-3 text-sm"
                                        >
                                            {row.image_url ? (
                                                <img
                                                    src={row.image_url}
                                                    alt=""
                                                    className="size-12 rounded object-cover"
                                                    loading="lazy"
                                                />
                                            ) : (
                                                <span className="size-12 rounded bg-muted" />
                                            )}
                                            <div className="min-w-0 flex-1">
                                                <p className="font-medium">
                                                    {row.name}{' '}
                                                    {row.is_featured && (
                                                        <Star
                                                            className="inline size-3 fill-highlight text-highlight"
                                                            aria-label="Featured"
                                                        />
                                                    )}
                                                    {!row.is_active && (
                                                        <Badge
                                                            variant="muted"
                                                            className="ml-1"
                                                        >
                                                            Inactive
                                                        </Badge>
                                                    )}
                                                </p>
                                                <p className="truncate text-xs text-muted-foreground">
                                                    {row.category_label}
                                                    {row.short_description &&
                                                        ` · ${row.short_description}`}
                                                </p>
                                            </div>
                                            <span className="font-medium">
                                                {row.price.formatted}{' '}
                                                <span className="font-normal text-muted-foreground">
                                                    / {row.unit}
                                                </span>
                                            </span>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => setService(row)}
                                            >
                                                Edit
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="text-destructive"
                                                onClick={() =>
                                                    setRemoving({
                                                        kind: 'service',
                                                        slug: row.slug,
                                                        name: row.name,
                                                    })
                                                }
                                            >
                                                Remove
                                            </Button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    </TabsContent>
                </Tabs>
            </div>

            <CategoryDialog
                category={category}
                onClose={() => setCategory(undefined)}
            />
            <MenuItemDialog
                item={item}
                onClose={() => setItem(undefined)}
                categories={categories}
            />
            <ServiceDialog
                service={service}
                onClose={() => setService(undefined)}
                serviceCategories={serviceCategories}
            />
            <ConfirmDialog
                open={removing !== null}
                onOpenChange={(open) => !open && setRemoving(null)}
                title={`Remove ${removing?.name ?? ''}?`}
                description="It will no longer be offered. Past orders keep their recorded name and price."
                confirmLabel="Remove"
                destructive
                processing={processing}
                onConfirm={remove}
            />
        </>
    );
}

function CategoryDialog({
    category,
    onClose,
}: {
    category: Category | null | undefined;
    onClose: () => void;
}) {
    const form = useForm({ name: '', display_order: '0', is_active: true });

    useEffect(() => {
        if (category !== undefined) {
            form.clearErrors();
            form.setData(
                category
                    ? {
                          name: category.name,
                          display_order: String(category.display_order),
                          is_active: category.is_active,
                      }
                    : { name: '', display_order: '0', is_active: true },
            );
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [category]);

    function submit() {
        const options = {
            preserveScroll: true,
            onSuccess: onClose,
            onError: notifyValidationFailed,
        };

        if (category) {
            form.put(
                admin.catalog.menuCategories.update.url(category.slug),
                options,
            );
        } else {
            form.post(admin.catalog.menuCategories.store.url(), options);
        }
    }

    return (
        <FormDialog
            open={category !== undefined}
            onOpenChange={(open) => !open && onClose()}
            title={category ? 'Edit category' : 'New menu category'}
            processing={form.processing}
            onSubmit={submit}
        >
            <Field
                label="Name"
                htmlFor="c-name"
                error={form.errors.name}
                required
            >
                <Input
                    id="c-name"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                />
            </Field>
            <Field
                label="Display order"
                htmlFor="c-order"
                error={form.errors.display_order}
                required
            >
                <Input
                    id="c-order"
                    type="number"
                    min={0}
                    value={form.data.display_order}
                    onChange={(event) =>
                        form.setData('display_order', event.target.value)
                    }
                />
            </Field>
            <div className="flex items-center gap-2">
                <Switch
                    id="c-active"
                    checked={form.data.is_active}
                    onCheckedChange={(checked) =>
                        form.setData('is_active', checked)
                    }
                />
                <Label htmlFor="c-active">Shown on menus</Label>
            </div>
        </FormDialog>
    );
}

function MenuItemDialog({
    item,
    onClose,
    categories,
}: {
    item: MenuItem | null | undefined;
    onClose: () => void;
    categories: Category[];
}) {
    const blank = {
        menu_category_id: '',
        name: '',
        description: '',
        price: '',
        is_available: true,
        is_featured: false,
        display_order: '0',
        image: null as File | null,
    };
    const form = useForm(blank);
    const { data, setData, errors } = form;

    useEffect(() => {
        if (item !== undefined) {
            form.clearErrors();
            form.setData(
                item
                    ? {
                          menu_category_id: String(item.menu_category_id),
                          name: item.name,
                          description: item.description ?? '',
                          price: item.price.amount,
                          is_available: item.is_available,
                          is_featured: item.is_featured,
                          display_order: String(item.display_order),
                          image: null,
                      }
                    : {
                          ...blank,
                          menu_category_id: categories[0]
                              ? String(categories[0].id)
                              : '',
                      },
            );
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [item]);

    function submit() {
        const options = {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
            onError: notifyValidationFailed,
        };

        if (item) {
            form.transform((values) => ({ ...values, _method: 'put' }));
            form.post(admin.catalog.menuItems.update.url(item.slug), options);
        } else {
            form.transform((values) => values);
            form.post(admin.catalog.menuItems.store.url(), options);
        }
    }

    return (
        <FormDialog
            open={item !== undefined}
            onOpenChange={(open) => !open && onClose()}
            title={item ? `Edit ${item.name}` : 'New menu item'}
            processing={form.processing}
            onSubmit={submit}
        >
            <FormErrorSummary errors={errors} />
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Name"
                    htmlFor="mi-name"
                    error={errors.name}
                    required
                >
                    <Input
                        id="mi-name"
                        value={data.name}
                        onChange={(event) =>
                            setData('name', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Category"
                    htmlFor="mi-category"
                    error={errors.menu_category_id}
                    required
                >
                    <OptionSelect
                        id="mi-category"
                        value={data.menu_category_id}
                        options={categories.map((row) => ({
                            value: String(row.id),
                            label: row.name,
                        }))}
                        onChange={(value) => setData('menu_category_id', value)}
                    />
                </Field>
                <Field
                    label="Price"
                    htmlFor="mi-price"
                    error={errors.price}
                    required
                >
                    <Input
                        id="mi-price"
                        inputMode="decimal"
                        value={data.price}
                        onChange={(event) =>
                            setData('price', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Display order"
                    htmlFor="mi-order"
                    error={errors.display_order}
                    required
                >
                    <Input
                        id="mi-order"
                        type="number"
                        min={0}
                        value={data.display_order}
                        onChange={(event) =>
                            setData('display_order', event.target.value)
                        }
                    />
                </Field>
            </div>
            <Field
                label="Description"
                htmlFor="mi-description"
                error={errors.description}
            >
                <Textarea
                    id="mi-description"
                    rows={2}
                    value={data.description}
                    onChange={(event) =>
                        setData('description', event.target.value)
                    }
                />
            </Field>
            <div className="flex flex-wrap gap-6">
                <div className="flex items-center gap-2">
                    <Switch
                        id="mi-available"
                        checked={data.is_available}
                        onCheckedChange={(checked) =>
                            setData('is_available', checked)
                        }
                    />
                    <Label htmlFor="mi-available">Available</Label>
                </div>
                <div className="flex items-center gap-2">
                    <Switch
                        id="mi-featured"
                        checked={data.is_featured}
                        onCheckedChange={(checked) =>
                            setData('is_featured', checked)
                        }
                    />
                    <Label htmlFor="mi-featured">Featured on website</Label>
                </div>
            </div>
            <ImagePicker
                label={item?.image_url ? 'Replace photo' : 'Photo'}
                files={data.image ? [data.image] : []}
                onFilesChange={(files) => setData('image', files[0] ?? null)}
                error={fieldError(errors, 'image')}
            />
        </FormDialog>
    );
}

function ServiceDialog({
    service,
    onClose,
    serviceCategories,
}: {
    service: Service | null | undefined;
    onClose: () => void;
    serviceCategories: Option[];
}) {
    const blank = {
        name: '',
        category: 'other',
        short_description: '',
        description: '',
        price: '',
        unit: 'per booking',
        is_active: true,
        is_featured: false,
        display_order: '0',
        image: null as File | null,
    };
    const form = useForm(blank);
    const { data, setData, errors } = form;

    useEffect(() => {
        if (service !== undefined) {
            form.clearErrors();
            form.setData(
                service
                    ? {
                          name: service.name,
                          category: service.category,
                          short_description: service.short_description ?? '',
                          description: service.description ?? '',
                          price: service.price.amount,
                          unit: service.unit,
                          is_active: service.is_active,
                          is_featured: service.is_featured,
                          display_order: String(service.display_order),
                          image: null,
                      }
                    : blank,
            );
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [service]);

    function submit() {
        const options = {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: onClose,
            onError: notifyValidationFailed,
        };

        if (service) {
            form.transform((values) => ({ ...values, _method: 'put' }));
            form.post(admin.catalog.services.update.url(service.slug), options);
        } else {
            form.transform((values) => values);
            form.post(admin.catalog.services.store.url(), options);
        }
    }

    return (
        <FormDialog
            open={service !== undefined}
            onOpenChange={(open) => !open && onClose()}
            title={service ? `Edit ${service.name}` : 'New service'}
            size="lg"
            processing={form.processing}
            onSubmit={submit}
        >
            <FormErrorSummary errors={errors} />
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Name"
                    htmlFor="sv-name"
                    error={errors.name}
                    required
                >
                    <Input
                        id="sv-name"
                        value={data.name}
                        onChange={(event) =>
                            setData('name', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Category"
                    htmlFor="sv-category"
                    error={errors.category}
                    required
                >
                    <OptionSelect
                        id="sv-category"
                        value={data.category}
                        options={serviceCategories}
                        onChange={(value) => setData('category', value)}
                    />
                </Field>
                <Field
                    label="Price"
                    htmlFor="sv-price"
                    error={errors.price}
                    required
                >
                    <Input
                        id="sv-price"
                        inputMode="decimal"
                        value={data.price}
                        onChange={(event) =>
                            setData('price', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Charged"
                    htmlFor="sv-unit"
                    error={errors.unit}
                    required
                >
                    <Input
                        id="sv-unit"
                        value={data.unit}
                        onChange={(event) =>
                            setData('unit', event.target.value)
                        }
                        placeholder="per person, per trip"
                    />
                </Field>
                <Field
                    label="Display order"
                    htmlFor="sv-order"
                    error={errors.display_order}
                    required
                >
                    <Input
                        id="sv-order"
                        type="number"
                        min={0}
                        value={data.display_order}
                        onChange={(event) =>
                            setData('display_order', event.target.value)
                        }
                    />
                </Field>
            </div>
            <Field
                label="Short description"
                htmlFor="sv-short"
                error={errors.short_description}
            >
                <Input
                    id="sv-short"
                    value={data.short_description}
                    onChange={(event) =>
                        setData('short_description', event.target.value)
                    }
                />
            </Field>
            <Field
                label="Full description"
                htmlFor="sv-description"
                error={errors.description}
            >
                <Textarea
                    id="sv-description"
                    rows={3}
                    value={data.description}
                    onChange={(event) =>
                        setData('description', event.target.value)
                    }
                />
            </Field>
            <div className="flex flex-wrap gap-6">
                <div className="flex items-center gap-2">
                    <Switch
                        id="sv-active"
                        checked={data.is_active}
                        onCheckedChange={(checked) =>
                            setData('is_active', checked)
                        }
                    />
                    <Label htmlFor="sv-active">Bookable</Label>
                </div>
                <div className="flex items-center gap-2">
                    <Switch
                        id="sv-featured"
                        checked={data.is_featured}
                        onCheckedChange={(checked) =>
                            setData('is_featured', checked)
                        }
                    />
                    <Label htmlFor="sv-featured">Featured on website</Label>
                </div>
            </div>
            <ImagePicker
                label={service?.image_url ? 'Replace photo' : 'Photo'}
                files={data.image ? [data.image] : []}
                onFilesChange={(files) => setData('image', files[0] ?? null)}
                error={fieldError(errors, 'image')}
            />
        </FormDialog>
    );
}

Catalog.layout = {
    breadcrumbs: [{ title: 'Menu & services', href: admin.catalog.index() }],
};
