import { Head, Link, useForm } from '@inertiajs/react';
import { MessageSquareWarning, Plus } from 'lucide-react';
import { useEffect, useState } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { Field } from '@/components/admin/field';
import { FormDialog } from '@/components/admin/form-dialog';
import { FormErrorSummary } from '@/components/admin/form-error-summary';
import { OptionSelect } from '@/components/admin/option-select';
import type { Option } from '@/components/admin/option-select';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { useFilters } from '@/hooks/use-filters';
import { notifyValidationFailed } from '@/lib/form';
import { formatDateTime } from '@/lib/format';
import admin from '@/routes/admin';
import type { Paginated } from '@/types';
import { priorityVariant } from '../maintenance/index';

type Complaint = {
    reference: string;
    subject: string;
    description: string;
    resolution: string | null;
    assigned_to: number | null;
    guest: { id: number; full_name: string } | null;
    reservation: string | null;
    category_label: string;
    severity: string;
    status: string;
    status_label: string;
    assignee: string | null;
    created_at: string | null;
    resolved_at: string | null;
};

type Props = {
    complaints: Paginated<Complaint>;
    filters: { status: string };
    options: { categories: Option[]; severities: Option[]; statuses: Option[] };
    staff: { id: number; name: string }[];
    recentStays: { id: number; guest_id: number; label: string }[];
    can: { manage: boolean };
};

const statusVariant: Record<
    string,
    'warning' | 'secondary' | 'success' | 'muted'
> = {
    open: 'warning',
    in_progress: 'secondary',
    resolved: 'success',
    closed: 'muted',
};

export default function Complaints(props: Props) {
    const { complaints, options, staff, can } = props;
    const { filters, setFilter } = useFilters<Props['filters']>(
        admin.complaints.index.url(),
        props.filters,
    );
    const [logging, setLogging] = useState(false);
    const [handling, setHandling] = useState<Complaint | null>(null);

    return (
        <>
            <Head title="Complaints" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Guest complaints"
                    description="Log, assign and resolve guest concerns. Every change is audited."
                    actions={
                        can.manage && (
                            <Button onClick={() => setLogging(true)}>
                                <Plus />
                                Log complaint
                            </Button>
                        )
                    }
                />
                <div className="rounded-xl border bg-card shadow-xs">
                    <div className="flex justify-end border-b p-4">
                        <OptionSelect
                            ariaLabel="Status"
                            className="w-48"
                            value={filters.status}
                            placeholder="All statuses"
                            options={options.statuses}
                            onChange={(value) =>
                                setFilter('status', value, true)
                            }
                        />
                    </div>
                    {complaints.data.length === 0 ? (
                        <EmptyState
                            icon={MessageSquareWarning}
                            title="No complaints"
                            description="Nothing matches this filter."
                        />
                    ) : (
                        <ul className="divide-y">
                            {complaints.data.map((complaint) => (
                                <li
                                    key={complaint.reference}
                                    className="flex flex-wrap items-start justify-between gap-3 p-4 text-sm"
                                >
                                    <div className="min-w-0 flex-1 space-y-1">
                                        <p className="font-medium">
                                            {complaint.subject}
                                        </p>
                                        <p className="line-clamp-2 text-muted-foreground">
                                            {complaint.description}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            <span className="font-mono">
                                                {complaint.reference}
                                            </span>{' '}
                                            · {complaint.category_label} ·{' '}
                                            {formatDateTime(
                                                complaint.created_at,
                                            )}
                                            {complaint.guest && (
                                                <>
                                                    {' · '}
                                                    <Link
                                                        href={admin.guests.show(
                                                            complaint.guest.id,
                                                        )}
                                                        className="hover:underline"
                                                    >
                                                        {
                                                            complaint.guest
                                                                .full_name
                                                        }
                                                    </Link>
                                                </>
                                            )}
                                            {complaint.reservation && (
                                                <>
                                                    {' · '}
                                                    <Link
                                                        href={admin.reservations.show(
                                                            complaint.reservation,
                                                        )}
                                                        className="hover:underline"
                                                    >
                                                        {complaint.reservation}
                                                    </Link>
                                                </>
                                            )}
                                            {complaint.assignee &&
                                                ` · ${complaint.assignee}`}
                                        </p>
                                        {complaint.resolution && (
                                            <p className="rounded-md bg-muted/60 p-2">
                                                Resolution:{' '}
                                                {complaint.resolution}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Badge
                                            variant={
                                                priorityVariant[
                                                    complaint.severity
                                                ]
                                            }
                                            className="capitalize"
                                        >
                                            {complaint.severity}
                                        </Badge>
                                        <Badge
                                            variant={
                                                statusVariant[complaint.status]
                                            }
                                        >
                                            {complaint.status_label}
                                        </Badge>
                                        {can.manage &&
                                            complaint.status !== 'closed' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        setHandling(complaint)
                                                    }
                                                >
                                                    Update
                                                </Button>
                                            )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                    <div className="border-t p-4">
                        <Pagination paginator={complaints} />
                    </div>
                </div>
            </div>

            <LogDialog
                open={logging}
                onClose={() => setLogging(false)}
                {...props}
            />
            <HandleDialog
                complaint={handling}
                onClose={() => setHandling(null)}
                statuses={options.statuses}
                staff={staff}
            />
        </>
    );
}

function LogDialog({
    open,
    onClose,
    options,
    staff,
    recentStays,
}: Props & { open: boolean; onClose: () => void }) {
    const form = useForm({
        reservation_id: '',
        guest_id: '',
        category: 'other',
        severity: 'normal',
        subject: '',
        description: '',
        assigned_to: '',
    });
    const { data, setData, errors } = form;

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
            title="Log a complaint"
            size="lg"
            submitLabel="Log complaint"
            processing={form.processing}
            onSubmit={() =>
                form.post(admin.complaints.store.url(), {
                    preserveScroll: true,
                    onSuccess: onClose,
                    onError: notifyValidationFailed,
                })
            }
        >
            <FormErrorSummary errors={errors} />
            <Field label="Stay" htmlFor="cp-stay" error={errors.reservation_id}>
                <OptionSelect
                    id="cp-stay"
                    value={data.reservation_id}
                    placeholder="Not linked to a recent stay"
                    options={recentStays.map((stay) => ({
                        value: String(stay.id),
                        label: stay.label,
                    }))}
                    onChange={(value) => {
                        const stay = recentStays.find(
                            (row) => String(row.id) === value,
                        );
                        form.setData({
                            ...data,
                            reservation_id: value,
                            guest_id: stay ? String(stay.guest_id) : '',
                        });
                    }}
                />
            </Field>
            <div className="grid gap-4 sm:grid-cols-3">
                <Field
                    label="Category"
                    htmlFor="cp-category"
                    error={errors.category}
                    required
                >
                    <OptionSelect
                        id="cp-category"
                        value={data.category}
                        options={options.categories}
                        onChange={(value) => setData('category', value)}
                    />
                </Field>
                <Field
                    label="Severity"
                    htmlFor="cp-severity"
                    error={errors.severity}
                    required
                >
                    <OptionSelect
                        id="cp-severity"
                        value={data.severity}
                        options={options.severities}
                        onChange={(value) => setData('severity', value)}
                    />
                </Field>
                <Field
                    label="Assign to"
                    htmlFor="cp-assignee"
                    error={errors.assigned_to}
                >
                    <OptionSelect
                        id="cp-assignee"
                        value={data.assigned_to}
                        placeholder="Unassigned"
                        options={staff.map((user) => ({
                            value: String(user.id),
                            label: user.name,
                        }))}
                        onChange={(value) => setData('assigned_to', value)}
                    />
                </Field>
            </div>
            <Field
                label="Subject"
                htmlFor="cp-subject"
                error={errors.subject}
                required
            >
                <Input
                    id="cp-subject"
                    value={data.subject}
                    onChange={(event) => setData('subject', event.target.value)}
                />
            </Field>
            <Field
                label="What happened"
                htmlFor="cp-description"
                error={errors.description}
                required
            >
                <Textarea
                    id="cp-description"
                    rows={4}
                    value={data.description}
                    onChange={(event) =>
                        setData('description', event.target.value)
                    }
                />
            </Field>
        </FormDialog>
    );
}

function HandleDialog({
    complaint,
    onClose,
    statuses,
    staff,
}: {
    complaint: Complaint | null;
    onClose: () => void;
    statuses: Option[];
    staff: Props['staff'];
}) {
    const form = useForm({ status: '', assigned_to: '', resolution: '' });
    const { data, setData, errors } = form;
    const closing = ['resolved', 'closed'].includes(data.status);

    useEffect(() => {
        if (complaint) {
            form.clearErrors();
            form.setData({
                status: complaint.status,
                assigned_to: complaint.assigned_to
                    ? String(complaint.assigned_to)
                    : '',
                resolution: complaint.resolution ?? '',
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [complaint]);

    return (
        <FormDialog
            open={complaint !== null}
            onOpenChange={(open) => !open && onClose()}
            title={
                complaint ? `Update ${complaint.reference}` : 'Update complaint'
            }
            description={complaint?.subject}
            processing={form.processing}
            onSubmit={() =>
                complaint &&
                form.put(admin.complaints.update.url(complaint.reference), {
                    preserveScroll: true,
                    onSuccess: onClose,
                    onError: notifyValidationFailed,
                })
            }
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Status"
                    htmlFor="ch-status"
                    error={errors.status}
                    required
                >
                    <OptionSelect
                        id="ch-status"
                        value={data.status}
                        options={statuses}
                        onChange={(value) => setData('status', value)}
                    />
                </Field>
                <Field
                    label="Assigned to"
                    htmlFor="ch-assignee"
                    error={errors.assigned_to}
                >
                    <OptionSelect
                        id="ch-assignee"
                        value={data.assigned_to}
                        placeholder="Unassigned"
                        options={staff.map((user) => ({
                            value: String(user.id),
                            label: user.name,
                        }))}
                        onChange={(value) => setData('assigned_to', value)}
                    />
                </Field>
            </div>
            <Field
                label="Resolution"
                htmlFor="ch-resolution"
                error={errors.resolution}
                required={closing}
            >
                <Textarea
                    id="ch-resolution"
                    rows={3}
                    value={data.resolution}
                    onChange={(event) =>
                        setData('resolution', event.target.value)
                    }
                />
            </Field>
        </FormDialog>
    );
}

Complaints.layout = {
    breadcrumbs: [{ title: 'Complaints', href: admin.complaints.index() }],
};
