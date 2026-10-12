import { Head, router, useForm } from '@inertiajs/react';
import { ImagePlus, Lock, Package, Send } from 'lucide-react';
import { useState } from 'react';
import { Field } from '@/components/admin/field';
import { FormDialog } from '@/components/admin/form-dialog';
import { ImagePicker } from '@/components/admin/image-picker';
import { OptionSelect } from '@/components/admin/option-select';
import { PageHeader } from '@/components/admin/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { fieldError, notifyValidationFailed } from '@/lib/form';
import { formatDate, formatDateTime } from '@/lib/format';
import admin from '@/routes/admin';
import type { MoneyValue } from '@/types';
import { priorityVariant } from './index';
import type { MaintenanceOptions } from './index';

type Request = {
    reference: string;
    title: string;
    description: string | null;
    room: string | null;
    location: string | null;
    category_label: string;
    priority: string;
    status: string;
    status_label: string;
    reporter: string | null;
    assignee: { id: number; name: string } | null;
    schedule: string | null;
    estimated_cost: MoneyValue | null;
    actual_cost: MoneyValue | null;
    block: { start_date: string; end_date: string; released: boolean } | null;
    created_at: string | null;
    started_at: string | null;
    completed_at: string | null;
    comments: {
        id: number;
        body: string;
        event: string | null;
        user: string | null;
        created_at: string;
    }[];
    attachments: { id: number; name: string; url: string }[];
    parts: {
        id: number;
        description: string;
        quantity: string;
        unit: string | null;
        unit_cost: MoneyValue;
        from_stock: boolean;
    }[];
};

type Props = MaintenanceOptions & {
    request: Request;
    stockItems: { id: number; name: string; unit: string; quantity: string }[];
    can: { work: boolean; assign: boolean; close: boolean };
};

const nextStatuses: Record<string, { value: string; label: string }[]> = {
    open: [
        { value: 'in_progress', label: 'Start work' },
        { value: 'on_hold', label: 'Put on hold' },
        { value: 'resolved', label: 'Resolve' },
    ],
    assigned: [
        { value: 'in_progress', label: 'Start work' },
        { value: 'on_hold', label: 'Put on hold' },
        { value: 'resolved', label: 'Resolve' },
    ],
    in_progress: [
        { value: 'on_hold', label: 'Put on hold' },
        { value: 'resolved', label: 'Resolve' },
    ],
    on_hold: [
        { value: 'in_progress', label: 'Resume' },
        { value: 'resolved', label: 'Resolve' },
    ],
    resolved: [
        { value: 'closed', label: 'Close' },
        { value: 'in_progress', label: 'Reopen' },
    ],
};

export default function MaintenanceShow({
    request,
    technicians,
    stockItems,
    can,
}: Props) {
    const [statusTarget, setStatusTarget] = useState<string | null>(null);
    const [addingPart, setAddingPart] = useState(false);
    const [uploading, setUploading] = useState(false);
    const comment = useForm({ body: '' });
    const actions = (nextStatuses[request.status] ?? []).filter((action) =>
        action.value === 'closed' ? can.close : can.work,
    );

    return (
        <>
            <Head title={request.title} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={request.title}
                    description={`${request.reference} · ${request.category_label} · ${request.room ? `Room ${request.room}` : request.location}`}
                    actions={
                        <>
                            {actions.map((action) => (
                                <Button
                                    key={action.value}
                                    variant={
                                        action.value === 'resolved'
                                            ? 'default'
                                            : 'outline'
                                    }
                                    onClick={() =>
                                        setStatusTarget(action.value)
                                    }
                                >
                                    {action.label}
                                </Button>
                            ))}
                            {can.close &&
                                ['open', 'assigned', 'on_hold'].includes(
                                    request.status,
                                ) && (
                                    <Button
                                        variant="ghost"
                                        className="text-destructive"
                                        onClick={() =>
                                            setStatusTarget('cancelled')
                                        }
                                    >
                                        Cancel request
                                    </Button>
                                )}
                        </>
                    }
                />
                <div className="flex flex-wrap gap-2">
                    <Badge
                        variant={priorityVariant[request.priority]}
                        className="capitalize"
                    >
                        {request.priority} priority
                    </Badge>
                    <Badge variant="secondary">{request.status_label}</Badge>
                    {request.block && (
                        <Badge
                            variant={
                                request.block.released ? 'muted' : 'warning'
                            }
                        >
                            <Lock />
                            Room {request.block.released ? 'was' : 'is'} out of
                            service {formatDate(request.block.start_date)} –{' '}
                            {formatDate(request.block.end_date)}
                        </Badge>
                    )}
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <section className="rounded-xl border bg-card p-5 shadow-xs">
                            <h2 className="mb-2 font-display text-xl font-semibold">
                                Details
                            </h2>
                            <p className="text-sm whitespace-pre-line">
                                {request.description ?? 'No further details.'}
                            </p>
                            {request.attachments.length > 0 && (
                                <div className="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-5">
                                    {request.attachments.map((attachment) => (
                                        <a
                                            key={attachment.id}
                                            href={attachment.url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="aspect-square overflow-hidden rounded-lg border"
                                        >
                                            <img
                                                src={attachment.url}
                                                alt={attachment.name}
                                                className="size-full object-cover"
                                                loading="lazy"
                                            />
                                        </a>
                                    ))}
                                </div>
                            )}
                            {can.work && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="mt-3"
                                    onClick={() => setUploading(true)}
                                >
                                    <ImagePlus />
                                    Add photos
                                </Button>
                            )}
                        </section>

                        <section className="rounded-xl border bg-card p-5 shadow-xs">
                            <h2 className="mb-3 font-display text-xl font-semibold">
                                Work log
                            </h2>
                            <ol className="space-y-3 text-sm">
                                {request.comments.map((entry) => (
                                    <li key={entry.id} className="flex gap-3">
                                        <span
                                            className={`mt-1.5 size-2 shrink-0 rounded-full ${entry.event ? 'bg-highlight' : 'bg-primary'}`}
                                        />
                                        <div>
                                            <p className="whitespace-pre-line">
                                                {entry.body}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {entry.user ?? 'System'} ·{' '}
                                                {formatDateTime(
                                                    entry.created_at,
                                                )}
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ol>
                            {can.work && (
                                <form
                                    className="mt-4 flex gap-2"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        comment.post(
                                            admin.maintenance.comments.url(
                                                request.reference,
                                            ),
                                            {
                                                preserveScroll: true,
                                                onSuccess: () =>
                                                    comment.reset(),
                                            },
                                        );
                                    }}
                                >
                                    <Input
                                        aria-label="Add a note"
                                        placeholder="Add a note to the work log"
                                        value={comment.data.body}
                                        onChange={(event) =>
                                            comment.setData(
                                                'body',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <Button
                                        type="submit"
                                        disabled={
                                            comment.processing ||
                                            comment.data.body.trim() === ''
                                        }
                                        aria-label="Add note"
                                    >
                                        <Send />
                                    </Button>
                                </form>
                            )}
                            {comment.errors.body && (
                                <p className="mt-1 text-sm text-destructive">
                                    {comment.errors.body}
                                </p>
                            )}
                        </section>
                    </div>

                    <aside className="space-y-6">
                        <section className="space-y-3 rounded-xl border bg-card p-5 text-sm shadow-xs">
                            <h2 className="font-display text-xl font-semibold">
                                Assignment
                            </h2>
                            <p>
                                Reported by {request.reporter ?? 'System'} on{' '}
                                {formatDateTime(request.created_at)}
                            </p>
                            {request.schedule && (
                                <p>From schedule: {request.schedule}</p>
                            )}
                            {can.assign ? (
                                <OptionSelect
                                    ariaLabel="Assigned technician"
                                    value={
                                        request.assignee
                                            ? String(request.assignee.id)
                                            : ''
                                    }
                                    placeholder="Unassigned"
                                    options={technicians.map((tech) => ({
                                        value: String(tech.id),
                                        label: tech.name,
                                    }))}
                                    onChange={(value) =>
                                        router.put(
                                            admin.maintenance.assign.url(
                                                request.reference,
                                            ),
                                            {
                                                assigned_to: value
                                                    ? Number(value)
                                                    : null,
                                            },
                                            { preserveScroll: true },
                                        )
                                    }
                                />
                            ) : (
                                <p>
                                    Assigned to{' '}
                                    {request.assignee?.name ?? 'nobody yet'}
                                </p>
                            )}
                            {request.started_at && (
                                <p>
                                    Started {formatDateTime(request.started_at)}
                                </p>
                            )}
                            {request.completed_at && (
                                <p>
                                    Completed{' '}
                                    {formatDateTime(request.completed_at)}
                                </p>
                            )}
                        </section>

                        <section className="space-y-3 rounded-xl border bg-card p-5 text-sm shadow-xs">
                            <div className="flex items-center justify-between">
                                <h2 className="font-display text-xl font-semibold">
                                    Parts & cost
                                </h2>
                                {can.work && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setAddingPart(true)}
                                    >
                                        <Package />
                                        Add part
                                    </Button>
                                )}
                            </div>
                            <ul className="space-y-1">
                                {request.parts.length === 0 && (
                                    <li className="text-muted-foreground">
                                        No parts recorded.
                                    </li>
                                )}
                                {request.parts.map((part) => (
                                    <li
                                        key={part.id}
                                        className="flex justify-between gap-2"
                                    >
                                        <span>
                                            {Number(part.quantity)}{' '}
                                            {part.unit ?? '×'}{' '}
                                            {part.description}
                                            {part.from_stock && (
                                                <Badge
                                                    variant="muted"
                                                    className="ml-1"
                                                >
                                                    stock
                                                </Badge>
                                            )}
                                        </span>
                                        <span>{part.unit_cost.formatted}</span>
                                    </li>
                                ))}
                            </ul>
                            <dl className="space-y-1 border-t pt-2">
                                <div className="flex justify-between">
                                    <dt>Estimated</dt>
                                    <dd>
                                        {request.estimated_cost?.formatted ??
                                            '—'}
                                    </dd>
                                </div>
                                <div className="flex justify-between font-medium">
                                    <dt>Actual</dt>
                                    <dd>
                                        {request.actual_cost?.formatted ?? '—'}
                                    </dd>
                                </div>
                            </dl>
                        </section>
                    </aside>
                </div>
            </div>

            <StatusDialog
                target={statusTarget}
                onClose={() => setStatusTarget(null)}
                reference={request.reference}
            />
            <PartDialog
                open={addingPart}
                onClose={() => setAddingPart(false)}
                reference={request.reference}
                stockItems={stockItems}
            />
            <UploadDialog
                open={uploading}
                onClose={() => setUploading(false)}
                reference={request.reference}
            />
        </>
    );
}

function StatusDialog({
    target,
    onClose,
    reference,
}: {
    target: string | null;
    onClose: () => void;
    reference: string;
}) {
    const form = useForm({ status: '', note: '', actual_cost: '' });

    return (
        <FormDialog
            open={target !== null}
            onOpenChange={(open) => !open && onClose()}
            title={
                target === 'resolved'
                    ? 'Resolve request'
                    : target === 'cancelled'
                      ? 'Cancel request'
                      : 'Update status'
            }
            description={
                target === 'resolved'
                    ? 'Resolving releases any room block and sends the room to housekeeping for a deep clean.'
                    : undefined
            }
            processing={form.processing}
            onSubmit={() => {
                form.transform((data) => ({ ...data, status: target }));
                form.post(admin.maintenance.status.url(reference), {
                    preserveScroll: true,
                    onSuccess: () => {
                        form.reset();
                        onClose();
                    },
                    onError: notifyValidationFailed,
                });
            }}
        >
            <Field label="Note" htmlFor="status-note" error={form.errors.note}>
                <Textarea
                    id="status-note"
                    rows={3}
                    value={form.data.note}
                    onChange={(event) =>
                        form.setData('note', event.target.value)
                    }
                />
            </Field>
            {target === 'resolved' && (
                <Field
                    label="Actual cost (blank = sum of parts)"
                    htmlFor="actual-cost"
                    error={form.errors.actual_cost}
                >
                    <Input
                        id="actual-cost"
                        inputMode="decimal"
                        value={form.data.actual_cost}
                        onChange={(event) =>
                            form.setData('actual_cost', event.target.value)
                        }
                    />
                </Field>
            )}
            {form.errors.status && (
                <p className="text-sm text-destructive">{form.errors.status}</p>
            )}
        </FormDialog>
    );
}

function PartDialog({
    open,
    onClose,
    reference,
    stockItems,
}: {
    open: boolean;
    onClose: () => void;
    reference: string;
    stockItems: Props['stockItems'];
}) {
    const form = useForm({
        inventory_item_id: '',
        description: '',
        quantity: '1',
        unit_cost: '',
    });

    return (
        <FormDialog
            open={open}
            onOpenChange={(next) => !next && onClose()}
            title="Record a part used"
            description="Parts taken from stock are deducted from inventory at their average cost."
            processing={form.processing}
            onSubmit={() =>
                form.post(admin.maintenance.parts.url(reference), {
                    preserveScroll: true,
                    onSuccess: () => {
                        form.reset();
                        onClose();
                    },
                    onError: notifyValidationFailed,
                })
            }
        >
            <Field
                label="From stock"
                htmlFor="part-item"
                error={form.errors.inventory_item_id}
            >
                <OptionSelect
                    id="part-item"
                    value={form.data.inventory_item_id}
                    placeholder="Bought separately"
                    options={stockItems.map((item) => ({
                        value: String(item.id),
                        label: `${item.name} (${Number(item.quantity)} ${item.unit} in stock)`,
                    }))}
                    onChange={(value) =>
                        form.setData('inventory_item_id', value)
                    }
                />
            </Field>
            {!form.data.inventory_item_id && (
                <Field
                    label="Description"
                    htmlFor="part-description"
                    error={form.errors.description}
                    required
                >
                    <Input
                        id="part-description"
                        value={form.data.description}
                        onChange={(event) =>
                            form.setData('description', event.target.value)
                        }
                    />
                </Field>
            )}
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Quantity"
                    htmlFor="part-qty"
                    error={form.errors.quantity}
                    required
                >
                    <Input
                        id="part-qty"
                        inputMode="decimal"
                        value={form.data.quantity}
                        onChange={(event) =>
                            form.setData('quantity', event.target.value)
                        }
                    />
                </Field>
                {!form.data.inventory_item_id && (
                    <Field
                        label="Unit cost"
                        htmlFor="part-cost"
                        error={form.errors.unit_cost}
                        required
                    >
                        <Input
                            id="part-cost"
                            inputMode="decimal"
                            value={form.data.unit_cost}
                            onChange={(event) =>
                                form.setData('unit_cost', event.target.value)
                            }
                        />
                    </Field>
                )}
            </div>
        </FormDialog>
    );
}

function UploadDialog({
    open,
    onClose,
    reference,
}: {
    open: boolean;
    onClose: () => void;
    reference: string;
}) {
    const form = useForm<{ photos: File[] }>({ photos: [] });

    return (
        <FormDialog
            open={open}
            onOpenChange={(next) => !next && onClose()}
            title="Add photos"
            description="Photos are stored privately and visible only to staff."
            submitLabel="Upload"
            processing={form.processing}
            onSubmit={() =>
                form.post(admin.maintenance.attachments.url(reference), {
                    forceFormData: true,
                    preserveScroll: true,
                    onSuccess: () => {
                        form.reset();
                        onClose();
                    },
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
        </FormDialog>
    );
}

MaintenanceShow.layout = {
    breadcrumbs: [{ title: 'Maintenance', href: admin.maintenance.index() }],
};
