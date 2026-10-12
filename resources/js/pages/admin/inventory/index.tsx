import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDownUp,
    Boxes,
    CalendarClock,
    Package,
    Plus,
    Search,
    Trash2,
    Truck,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { Field } from '@/components/admin/field';
import { FormDialog } from '@/components/admin/form-dialog';
import { FormErrorSummary } from '@/components/admin/form-error-summary';
import { OptionSelect } from '@/components/admin/option-select';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatCard } from '@/components/admin/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { useFilters } from '@/hooks/use-filters';
import { fieldError, notifyValidationFailed } from '@/lib/form';
import { formatDate } from '@/lib/format';
import admin from '@/routes/admin';
import type { MoneyValue, Paginated } from '@/types';

type Item = {
    id: number;
    sku: string;
    name: string;
    unit: string;
    inventory_category_id: number;
    preferred_supplier_id: number | null;
    tracks_expiry: boolean;
    is_active: boolean;
    quantity: string;
    reorder_level: string;
    category: string;
    supplier: string | null;
    average_cost: MoneyValue;
    value: MoneyValue;
    is_low: boolean;
    expiring_soon: boolean;
};

type Supplier = {
    id: number;
    name: string;
    contact_person: string | null;
    email: string | null;
    phone: string | null;
    address: string | null;
    tax_id: string | null;
    notes: string | null;
    is_active: boolean;
};

type Order = {
    number: string;
    supplier: string;
    status: string;
    status_label: string;
    total: MoneyValue;
    expected_on: string | null;
    created_at: string | null;
};

type MovementType = { value: string; label: string; direction: number };

type Filters = { search: string; category: string; stock: string };

type Props = {
    items: Paginated<Item>;
    filters: Filters;
    summary: { value: MoneyValue; low: number; expiring: number };
    categories: { id: number; name: string }[];
    suppliers: Supplier[];
    purchaseOrders: Order[];
    movementTypes: MovementType[];
    stockCatalog: { id: number; name: string; unit: string }[];
    baseCurrency: string;
};

const orderVariant: Record<
    string,
    'muted' | 'secondary' | 'warning' | 'success' | 'danger'
> = {
    draft: 'muted',
    ordered: 'secondary',
    partially_received: 'warning',
    received: 'success',
    cancelled: 'danger',
};

export default function InventoryIndex(props: Props) {
    const {
        items,
        summary,
        categories,
        suppliers,
        purchaseOrders,
        movementTypes,
    } = props;
    const { filters, setFilter } = useFilters<Filters>(
        admin.inventory.index.url(),
        props.filters,
    );
    const [editing, setEditing] = useState<Item | null | undefined>(undefined);
    const [moving, setMoving] = useState<Item | null>(null);
    const [supplier, setSupplier] = useState<Supplier | null | undefined>(
        undefined,
    );
    const [ordering, setOrdering] = useState(false);

    return (
        <>
            <Head title="Inventory" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Inventory"
                    description="Stock on hand, movements, suppliers and purchasing."
                    actions={
                        <>
                            <Button
                                variant="outline"
                                onClick={() => setOrdering(true)}
                                disabled={suppliers.length === 0}
                            >
                                <Truck />
                                New purchase order
                            </Button>
                            <Button onClick={() => setEditing(null)}>
                                <Plus />
                                New stock item
                            </Button>
                        </>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard
                        label="Stock value"
                        value={summary.value.formatted}
                        icon={Boxes}
                        tone="gold"
                        hint="At moving average cost"
                    />
                    <StatCard
                        label="At or below reorder level"
                        value={summary.low}
                        icon={AlertTriangle}
                    />
                    <StatCard
                        label="Batches expiring within 30 days"
                        value={summary.expiring}
                        icon={CalendarClock}
                    />
                </div>

                <Tabs defaultValue="items" className="gap-4">
                    <TabsList>
                        <TabsTrigger value="items">Stock</TabsTrigger>
                        <TabsTrigger value="orders">
                            Purchase orders
                        </TabsTrigger>
                        <TabsTrigger value="suppliers">Suppliers</TabsTrigger>
                    </TabsList>

                    <TabsContent value="items">
                        <div className="rounded-xl border bg-card shadow-xs">
                            <div className="grid gap-3 border-b p-4 sm:grid-cols-4">
                                <div className="relative sm:col-span-2">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        aria-label="Search"
                                        placeholder="Name or SKU"
                                        className="pl-9"
                                        value={filters.search}
                                        onChange={(event) =>
                                            setFilter(
                                                'search',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <OptionSelect
                                    ariaLabel="Category"
                                    value={filters.category}
                                    placeholder="All categories"
                                    options={categories.map((category) => ({
                                        value: String(category.id),
                                        label: category.name,
                                    }))}
                                    onChange={(value) =>
                                        setFilter('category', value, true)
                                    }
                                />
                                <OptionSelect
                                    ariaLabel="Stock level"
                                    value={filters.stock}
                                    placeholder="All stock"
                                    options={[
                                        { value: 'low', label: 'Low stock' },
                                        {
                                            value: 'expiring',
                                            label: 'Expiring soon',
                                        },
                                    ]}
                                    onChange={(value) =>
                                        setFilter('stock', value, true)
                                    }
                                />
                            </div>
                            {items.data.length === 0 ? (
                                <EmptyState
                                    icon={Package}
                                    title="No stock items"
                                    description="Add the consumables, linen and spare parts you track."
                                />
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Item</TableHead>
                                            <TableHead className="hidden md:table-cell">
                                                Category
                                            </TableHead>
                                            <TableHead className="text-right">
                                                On hand
                                            </TableHead>
                                            <TableHead className="hidden text-right sm:table-cell">
                                                Avg cost
                                            </TableHead>
                                            <TableHead className="hidden text-right sm:table-cell">
                                                Value
                                            </TableHead>
                                            <TableHead />
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {items.data.map((item) => (
                                            <TableRow
                                                key={item.id}
                                                className={
                                                    item.is_active
                                                        ? undefined
                                                        : 'opacity-60'
                                                }
                                            >
                                                <TableCell className="whitespace-normal">
                                                    <Link
                                                        href={admin.inventory.items.show(
                                                            item.id,
                                                        )}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {item.name}
                                                    </Link>
                                                    <span className="block font-mono text-xs text-muted-foreground">
                                                        {item.sku}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="hidden md:table-cell">
                                                    {item.category}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {Number(item.quantity)}{' '}
                                                    {item.unit}
                                                    <span className="mt-0.5 flex justify-end gap-1">
                                                        {item.is_low && (
                                                            <Badge variant="danger">
                                                                Reorder
                                                            </Badge>
                                                        )}
                                                        {item.expiring_soon && (
                                                            <Badge variant="warning">
                                                                Expiring
                                                            </Badge>
                                                        )}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="hidden text-right sm:table-cell">
                                                    {
                                                        item.average_cost
                                                            .formatted
                                                    }
                                                </TableCell>
                                                <TableCell className="hidden text-right sm:table-cell">
                                                    {item.value.formatted}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            setMoving(item)
                                                        }
                                                    >
                                                        <ArrowDownUp />
                                                        Movement
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            setEditing(item)
                                                        }
                                                    >
                                                        Edit
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                            <div className="border-t p-4">
                                <Pagination paginator={items} />
                            </div>
                        </div>
                    </TabsContent>

                    <TabsContent value="orders">
                        <div className="rounded-xl border bg-card shadow-xs">
                            {purchaseOrders.length === 0 ? (
                                <EmptyState
                                    icon={Truck}
                                    title="No purchase orders yet"
                                />
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Order</TableHead>
                                            <TableHead>Supplier</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="hidden sm:table-cell">
                                                Expected
                                            </TableHead>
                                            <TableHead className="text-right">
                                                Total
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {purchaseOrders.map((order) => (
                                            <TableRow key={order.number}>
                                                <TableCell>
                                                    <Link
                                                        href={admin.purchaseOrders.show(
                                                            order.number,
                                                        )}
                                                        className="font-mono font-medium hover:underline"
                                                    >
                                                        {order.number}
                                                    </Link>
                                                </TableCell>
                                                <TableCell>
                                                    {order.supplier}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant={
                                                            orderVariant[
                                                                order.status
                                                            ]
                                                        }
                                                    >
                                                        {order.status_label}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="hidden sm:table-cell">
                                                    {order.expected_on
                                                        ? formatDate(
                                                              order.expected_on,
                                                          )
                                                        : '—'}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {order.total.formatted}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </div>
                    </TabsContent>

                    <TabsContent value="suppliers">
                        <div className="rounded-xl border bg-card shadow-xs">
                            <div className="flex justify-end border-b p-3">
                                <Button
                                    size="sm"
                                    onClick={() => setSupplier(null)}
                                >
                                    <Plus />
                                    Add supplier
                                </Button>
                            </div>
                            {suppliers.length === 0 ? (
                                <EmptyState
                                    icon={Truck}
                                    title="No suppliers yet"
                                />
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Supplier</TableHead>
                                            <TableHead>Contact</TableHead>
                                            <TableHead className="hidden md:table-cell">
                                                Phone
                                            </TableHead>
                                            <TableHead />
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {suppliers.map((row) => (
                                            <TableRow key={row.id}>
                                                <TableCell>
                                                    {row.name}{' '}
                                                    {!row.is_active && (
                                                        <Badge variant="muted">
                                                            Inactive
                                                        </Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {row.contact_person ?? '—'}
                                                    {row.email && (
                                                        <span className="block text-xs text-muted-foreground">
                                                            {row.email}
                                                        </span>
                                                    )}
                                                </TableCell>
                                                <TableCell className="hidden md:table-cell">
                                                    {row.phone ?? '—'}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            setSupplier(row)
                                                        }
                                                    >
                                                        Edit
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                        </div>
                    </TabsContent>
                </Tabs>
            </div>

            <ItemDialog
                item={editing}
                onClose={() => setEditing(undefined)}
                categories={categories}
                suppliers={suppliers}
            />
            <MovementDialog
                item={moving}
                onClose={() => setMoving(null)}
                movementTypes={movementTypes}
            />
            <SupplierDialog
                supplier={supplier}
                onClose={() => setSupplier(undefined)}
            />
            <OrderDialog
                open={ordering}
                onClose={() => setOrdering(false)}
                suppliers={suppliers.filter((row) => row.is_active)}
                catalog={props.stockCatalog}
                baseCurrency={props.baseCurrency}
            />
        </>
    );
}

function ItemDialog({
    item,
    onClose,
    categories,
    suppliers,
}: {
    item: Item | null | undefined;
    onClose: () => void;
    categories: Props['categories'];
    suppliers: Supplier[];
}) {
    const blank = {
        name: '',
        sku: '',
        inventory_category_id: '',
        new_category: '',
        preferred_supplier_id: '',
        unit: 'each',
        reorder_level: '0',
        tracks_expiry: false,
        is_active: true,
    };
    const form = useForm(blank);
    const { data, setData, errors } = form;

    useEffect(() => {
        if (item !== undefined) {
            form.clearErrors();
            form.setData(
                item === null
                    ? blank
                    : {
                          name: item.name,
                          sku: item.sku,
                          inventory_category_id: String(
                              item.inventory_category_id,
                          ),
                          new_category: '',
                          preferred_supplier_id: item.preferred_supplier_id
                              ? String(item.preferred_supplier_id)
                              : '',
                          unit: item.unit,
                          reorder_level: String(Number(item.reorder_level)),
                          tracks_expiry: item.tracks_expiry,
                          is_active: item.is_active,
                      },
            );
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [item]);

    function submit() {
        const options = {
            preserveScroll: true,
            onSuccess: onClose,
            onError: notifyValidationFailed,
        };

        if (item) {
            form.put(admin.inventory.items.update.url(item.id), options);
        } else {
            form.post(admin.inventory.items.store.url(), options);
        }
    }

    return (
        <FormDialog
            open={item !== undefined}
            onOpenChange={(open) => !open && onClose()}
            title={item ? `Edit ${item.name}` : 'New stock item'}
            description={
                item
                    ? 'Quantities change only through stock movements.'
                    : 'Opening stock is recorded afterwards as a receipt movement.'
            }
            processing={form.processing}
            onSubmit={submit}
        >
            <FormErrorSummary errors={errors} />
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Name"
                    htmlFor="i-name"
                    error={errors.name}
                    required
                >
                    <Input
                        id="i-name"
                        value={data.name}
                        onChange={(event) =>
                            setData('name', event.target.value)
                        }
                    />
                </Field>
                <Field label="SKU" htmlFor="i-sku" error={errors.sku} required>
                    <Input
                        id="i-sku"
                        className="font-mono uppercase"
                        value={data.sku}
                        onChange={(event) =>
                            setData('sku', event.target.value.toUpperCase())
                        }
                    />
                </Field>
                <Field
                    label="Category"
                    htmlFor="i-category"
                    error={errors.inventory_category_id}
                >
                    <OptionSelect
                        id="i-category"
                        value={data.inventory_category_id}
                        placeholder="New category…"
                        options={categories.map((category) => ({
                            value: String(category.id),
                            label: category.name,
                        }))}
                        onChange={(value) =>
                            setData('inventory_category_id', value)
                        }
                    />
                </Field>
                {!data.inventory_category_id && (
                    <Field
                        label="New category"
                        htmlFor="i-new-category"
                        error={errors.new_category}
                        required
                    >
                        <Input
                            id="i-new-category"
                            value={data.new_category}
                            onChange={(event) =>
                                setData('new_category', event.target.value)
                            }
                        />
                    </Field>
                )}
                <Field
                    label="Unit"
                    htmlFor="i-unit"
                    error={errors.unit}
                    required
                >
                    <Input
                        id="i-unit"
                        value={data.unit}
                        onChange={(event) =>
                            setData('unit', event.target.value)
                        }
                        placeholder="each, kg, litre, box"
                    />
                </Field>
                <Field
                    label="Reorder level"
                    htmlFor="i-reorder"
                    error={errors.reorder_level}
                    required
                >
                    <Input
                        id="i-reorder"
                        inputMode="decimal"
                        value={data.reorder_level}
                        onChange={(event) =>
                            setData('reorder_level', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Preferred supplier"
                    htmlFor="i-supplier"
                    error={errors.preferred_supplier_id}
                >
                    <OptionSelect
                        id="i-supplier"
                        value={data.preferred_supplier_id}
                        placeholder="None"
                        options={suppliers.map((row) => ({
                            value: String(row.id),
                            label: row.name,
                        }))}
                        onChange={(value) =>
                            setData('preferred_supplier_id', value)
                        }
                    />
                </Field>
            </div>
            <div className="flex flex-wrap gap-6">
                <div className="flex items-center gap-2">
                    <Switch
                        id="i-expiry"
                        checked={data.tracks_expiry}
                        onCheckedChange={(checked) =>
                            setData('tracks_expiry', checked)
                        }
                    />
                    <Label htmlFor="i-expiry">Track expiry dates</Label>
                </div>
                <div className="flex items-center gap-2">
                    <Switch
                        id="i-active"
                        checked={data.is_active}
                        onCheckedChange={(checked) =>
                            setData('is_active', checked)
                        }
                    />
                    <Label htmlFor="i-active">Active</Label>
                </div>
            </div>
        </FormDialog>
    );
}

function MovementDialog({
    item,
    onClose,
    movementTypes,
}: {
    item: Item | null;
    onClose: () => void;
    movementTypes: MovementType[];
}) {
    const form = useForm({
        type: 'issue',
        quantity: '',
        notes: '',
        reference: '',
    });
    const { data, setData, errors } = form;
    const isAdjustment = data.type === 'adjustment';

    useEffect(() => {
        if (item) {
            form.reset();
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [item]);

    return (
        <FormDialog
            open={item !== null}
            onOpenChange={(open) => !open && onClose()}
            title={item ? `Stock movement · ${item.name}` : 'Stock movement'}
            description={
                item
                    ? `${Number(item.quantity)} ${item.unit} on hand. Stock can never go below zero.`
                    : undefined
            }
            submitLabel="Record"
            processing={form.processing}
            onSubmit={() =>
                item &&
                form.post(admin.inventory.items.movements.url(item.id), {
                    preserveScroll: true,
                    onSuccess: onClose,
                    onError: notifyValidationFailed,
                })
            }
        >
            <Field
                label="Movement"
                htmlFor="mv-type"
                error={errors.type}
                required
            >
                <OptionSelect
                    id="mv-type"
                    value={data.type}
                    options={movementTypes.map(({ value, label }) => ({
                        value,
                        label,
                    }))}
                    onChange={(value) => setData('type', value)}
                />
            </Field>
            <Field
                label={isAdjustment ? 'Counted quantity' : 'Quantity'}
                htmlFor="mv-qty"
                error={errors.quantity}
                required
            >
                <Input
                    id="mv-qty"
                    inputMode="decimal"
                    value={data.quantity}
                    onChange={(event) =>
                        setData('quantity', event.target.value)
                    }
                />
            </Field>
            <Field label="Reference" htmlFor="mv-ref" error={errors.reference}>
                <Input
                    id="mv-ref"
                    value={data.reference}
                    onChange={(event) =>
                        setData('reference', event.target.value)
                    }
                    placeholder="Requisition, department…"
                />
            </Field>
            <Field
                label="Notes"
                htmlFor="mv-notes"
                error={errors.notes}
                required={isAdjustment}
            >
                <Textarea
                    id="mv-notes"
                    rows={2}
                    value={data.notes}
                    onChange={(event) => setData('notes', event.target.value)}
                />
            </Field>
        </FormDialog>
    );
}

function SupplierDialog({
    supplier,
    onClose,
}: {
    supplier: Supplier | null | undefined;
    onClose: () => void;
}) {
    const blank = {
        name: '',
        contact_person: '',
        email: '',
        phone: '',
        address: '',
        tax_id: '',
        notes: '',
        is_active: true,
    };
    const form = useForm(blank);
    const { data, setData, errors } = form;

    useEffect(() => {
        if (supplier !== undefined) {
            form.clearErrors();
            form.setData(
                supplier === null
                    ? blank
                    : {
                          name: supplier.name,
                          contact_person: supplier.contact_person ?? '',
                          email: supplier.email ?? '',
                          phone: supplier.phone ?? '',
                          address: supplier.address ?? '',
                          tax_id: supplier.tax_id ?? '',
                          notes: supplier.notes ?? '',
                          is_active: supplier.is_active,
                      },
            );
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [supplier]);

    function submit() {
        const options = {
            preserveScroll: true,
            onSuccess: onClose,
            onError: notifyValidationFailed,
        };

        if (supplier) {
            form.put(admin.suppliers.update.url(supplier.id), options);
        } else {
            form.post(admin.suppliers.store.url(), options);
        }
    }

    return (
        <FormDialog
            open={supplier !== undefined}
            onOpenChange={(open) => !open && onClose()}
            title={supplier ? `Edit ${supplier.name}` : 'Add supplier'}
            processing={form.processing}
            onSubmit={submit}
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Company"
                    htmlFor="sp-name"
                    error={errors.name}
                    required
                >
                    <Input
                        id="sp-name"
                        value={data.name}
                        onChange={(event) =>
                            setData('name', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Contact person"
                    htmlFor="sp-contact"
                    error={errors.contact_person}
                >
                    <Input
                        id="sp-contact"
                        value={data.contact_person}
                        onChange={(event) =>
                            setData('contact_person', event.target.value)
                        }
                    />
                </Field>
                <Field label="Email" htmlFor="sp-email" error={errors.email}>
                    <Input
                        id="sp-email"
                        type="email"
                        value={data.email}
                        onChange={(event) =>
                            setData('email', event.target.value)
                        }
                    />
                </Field>
                <Field label="Phone" htmlFor="sp-phone" error={errors.phone}>
                    <Input
                        id="sp-phone"
                        value={data.phone}
                        onChange={(event) =>
                            setData('phone', event.target.value)
                        }
                    />
                </Field>
                <Field label="Tax ID" htmlFor="sp-tax" error={errors.tax_id}>
                    <Input
                        id="sp-tax"
                        value={data.tax_id}
                        onChange={(event) =>
                            setData('tax_id', event.target.value)
                        }
                    />
                </Field>
                <div className="flex items-end gap-2 pb-2">
                    <Switch
                        id="sp-active"
                        checked={data.is_active}
                        onCheckedChange={(checked) =>
                            setData('is_active', checked)
                        }
                    />
                    <Label htmlFor="sp-active">Active</Label>
                </div>
            </div>
            <Field label="Address" htmlFor="sp-address" error={errors.address}>
                <Textarea
                    id="sp-address"
                    rows={2}
                    value={data.address}
                    onChange={(event) => setData('address', event.target.value)}
                />
            </Field>
            <Field label="Notes" htmlFor="sp-notes" error={errors.notes}>
                <Textarea
                    id="sp-notes"
                    rows={2}
                    value={data.notes}
                    onChange={(event) => setData('notes', event.target.value)}
                />
            </Field>
        </FormDialog>
    );
}

type OrderLine = {
    inventory_item_id: string;
    quantity: string;
    unit_cost: string;
};

function OrderDialog({
    open,
    onClose,
    suppliers,
    catalog,
    baseCurrency,
}: {
    open: boolean;
    onClose: () => void;
    suppliers: Supplier[];
    catalog: Props['stockCatalog'];
    baseCurrency: string;
}) {
    const form = useForm<{
        supplier_id: string;
        expected_on: string;
        notes: string;
        lines: OrderLine[];
    }>({
        supplier_id: '',
        expected_on: '',
        notes: '',
        lines: [{ inventory_item_id: '', quantity: '', unit_cost: '' }],
    });
    const { data, setData, errors } = form;
    useEffect(() => {
        if (open) {
            form.reset();
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    function updateLine(index: number, key: keyof OrderLine, value: string) {
        setData(
            'lines',
            data.lines.map((line, position) =>
                position === index ? { ...line, [key]: value } : line,
            ),
        );
    }

    return (
        <FormDialog
            open={open}
            onOpenChange={(next) => !next && onClose()}
            title="New purchase order"
            description={`Prices in ${baseCurrency}. The order stays a draft until you mark it as sent.`}
            submitLabel="Create draft"
            size="lg"
            processing={form.processing}
            onSubmit={() =>
                form.post(admin.purchaseOrders.store.url(), {
                    onSuccess: onClose,
                    onError: notifyValidationFailed,
                })
            }
        >
            <FormErrorSummary errors={errors} />
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Supplier"
                    htmlFor="po-supplier"
                    error={errors.supplier_id}
                    required
                >
                    <OptionSelect
                        id="po-supplier"
                        value={data.supplier_id}
                        options={suppliers.map((row) => ({
                            value: String(row.id),
                            label: row.name,
                        }))}
                        onChange={(value) => setData('supplier_id', value)}
                    />
                </Field>
                <Field
                    label="Expected delivery"
                    htmlFor="po-expected"
                    error={errors.expected_on}
                >
                    <Input
                        id="po-expected"
                        type="date"
                        value={data.expected_on}
                        onChange={(event) =>
                            setData('expected_on', event.target.value)
                        }
                    />
                </Field>
            </div>
            <div className="space-y-2">
                <Label>Items</Label>
                {data.lines.map((line, index) => (
                    <div
                        key={index}
                        className="grid grid-cols-[1fr_6rem_7rem_auto] items-start gap-2"
                    >
                        <OptionSelect
                            ariaLabel={`Item ${index + 1}`}
                            value={line.inventory_item_id}
                            placeholder="Choose item"
                            options={catalog.map((item) => ({
                                value: String(item.id),
                                label: `${item.name} (${item.unit})`,
                            }))}
                            onChange={(value) =>
                                updateLine(index, 'inventory_item_id', value)
                            }
                        />
                        <Input
                            aria-label="Quantity"
                            placeholder="Qty"
                            inputMode="decimal"
                            value={line.quantity}
                            onChange={(event) =>
                                updateLine(
                                    index,
                                    'quantity',
                                    event.target.value,
                                )
                            }
                        />
                        <Input
                            aria-label="Unit cost"
                            placeholder="Unit cost"
                            inputMode="decimal"
                            value={line.unit_cost}
                            onChange={(event) =>
                                updateLine(
                                    index,
                                    'unit_cost',
                                    event.target.value,
                                )
                            }
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            aria-label="Remove line"
                            disabled={data.lines.length === 1}
                            onClick={() =>
                                setData(
                                    'lines',
                                    data.lines.filter(
                                        (_, position) => position !== index,
                                    ),
                                )
                            }
                        >
                            <Trash2 />
                        </Button>
                        {['inventory_item_id', 'quantity', 'unit_cost'].map(
                            (key) => {
                                const message = fieldError(
                                    errors,
                                    `lines.${index}.${key}`,
                                );

                                return message ? (
                                    <p
                                        key={key}
                                        className="col-span-4 text-sm text-destructive"
                                    >
                                        {message}
                                    </p>
                                ) : null;
                            },
                        )}
                    </div>
                ))}
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        setData('lines', [
                            ...data.lines,
                            {
                                inventory_item_id: '',
                                quantity: '',
                                unit_cost: '',
                            },
                        ])
                    }
                >
                    <Plus />
                    Add line
                </Button>
            </div>
            <Field label="Notes" htmlFor="po-notes" error={errors.notes}>
                <Textarea
                    id="po-notes"
                    rows={2}
                    value={data.notes}
                    onChange={(event) => setData('notes', event.target.value)}
                />
            </Field>
        </FormDialog>
    );
}

InventoryIndex.layout = {
    breadcrumbs: [{ title: 'Inventory', href: admin.inventory.index() }],
};
