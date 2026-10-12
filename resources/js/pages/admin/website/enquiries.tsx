import { Head, useForm } from '@inertiajs/react';
import { Inbox, Mail, Phone } from 'lucide-react';
import { useEffect, useState } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { Field } from '@/components/admin/field';
import { FormDialog } from '@/components/admin/form-dialog';
import { OptionSelect } from '@/components/admin/option-select';
import type { Option } from '@/components/admin/option-select';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { useFilters } from '@/hooks/use-filters';
import { notifyValidationFailed } from '@/lib/form';
import { formatDateTime } from '@/lib/format';
import admin from '@/routes/admin';
import type { Paginated } from '@/types';

type Enquiry = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    subject: string;
    message: string;
    notes: string | null;
    status: string;
    status_label: string;
    handler: string | null;
    created_at: string | null;
};

const statusVariant: Record<string, 'warning' | 'success' | 'muted'> = {
    new: 'warning',
    replied: 'success',
    closed: 'muted',
};

export default function Enquiries(props: {
    enquiries: Paginated<Enquiry>;
    filters: { status: string };
    statuses: Option[];
}) {
    const { enquiries, statuses } = props;
    const { filters, setFilter } = useFilters<{ status: string }>(
        admin.website.enquiries.index.url(),
        props.filters,
    );
    const [handling, setHandling] = useState<Enquiry | null>(null);

    return (
        <>
            <Head title="Website enquiries" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Website enquiries"
                    description="Messages from the contact form. Reply by email, then record the outcome here."
                />
                <div className="rounded-xl border bg-card shadow-xs">
                    <div className="flex justify-end border-b p-4">
                        <OptionSelect
                            ariaLabel="Status"
                            className="w-44"
                            value={filters.status}
                            placeholder="All"
                            options={statuses}
                            onChange={(value) =>
                                setFilter('status', value, true)
                            }
                        />
                    </div>
                    {enquiries.data.length === 0 ? (
                        <EmptyState icon={Inbox} title="No enquiries" />
                    ) : (
                        <ul className="divide-y">
                            {enquiries.data.map((enquiry) => (
                                <li
                                    key={enquiry.id}
                                    className="space-y-2 p-5 text-sm"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <p className="font-medium">
                                            {enquiry.subject}
                                        </p>
                                        <Badge
                                            variant={
                                                statusVariant[enquiry.status]
                                            }
                                        >
                                            {enquiry.status_label}
                                        </Badge>
                                    </div>
                                    <p className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                        <span>{enquiry.name}</span>
                                        <a
                                            href={`mailto:${enquiry.email}?subject=${encodeURIComponent(`Re: ${enquiry.subject}`)}`}
                                            className="flex items-center gap-1 hover:underline"
                                        >
                                            <Mail className="size-3" />{' '}
                                            {enquiry.email}
                                        </a>
                                        {enquiry.phone && (
                                            <a
                                                href={`tel:${enquiry.phone}`}
                                                className="flex items-center gap-1 hover:underline"
                                            >
                                                <Phone className="size-3" />{' '}
                                                {enquiry.phone}
                                            </a>
                                        )}
                                        <span>
                                            {formatDateTime(enquiry.created_at)}
                                        </span>
                                    </p>
                                    <p className="whitespace-pre-line">
                                        {enquiry.message}
                                    </p>
                                    {enquiry.notes && (
                                        <p className="rounded-lg bg-muted/60 p-3">
                                            Notes
                                            {enquiry.handler &&
                                                ` (${enquiry.handler})`}
                                            : {enquiry.notes}
                                        </p>
                                    )}
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => setHandling(enquiry)}
                                    >
                                        Update
                                    </Button>
                                </li>
                            ))}
                        </ul>
                    )}
                    <div className="border-t p-4">
                        <Pagination paginator={enquiries} />
                    </div>
                </div>
            </div>
            <HandleDialog
                enquiry={handling}
                onClose={() => setHandling(null)}
                statuses={statuses}
            />
        </>
    );
}

function HandleDialog({
    enquiry,
    onClose,
    statuses,
}: {
    enquiry: Enquiry | null;
    onClose: () => void;
    statuses: Option[];
}) {
    const form = useForm({ status: '', notes: '' });

    useEffect(() => {
        if (enquiry) {
            form.clearErrors();
            form.setData({
                status: enquiry.status === 'new' ? 'replied' : enquiry.status,
                notes: enquiry.notes ?? '',
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [enquiry]);

    return (
        <FormDialog
            open={enquiry !== null}
            onOpenChange={(open) => !open && onClose()}
            title="Update enquiry"
            description={enquiry?.subject}
            processing={form.processing}
            onSubmit={() =>
                enquiry &&
                form.put(admin.website.enquiries.update.url(enquiry.id), {
                    preserveScroll: true,
                    onSuccess: onClose,
                    onError: notifyValidationFailed,
                })
            }
        >
            <Field
                label="Status"
                htmlFor="eq-status"
                error={form.errors.status}
                required
            >
                <OptionSelect
                    id="eq-status"
                    value={form.data.status}
                    options={statuses}
                    onChange={(value) => form.setData('status', value)}
                />
            </Field>
            <Field
                label="Internal notes"
                htmlFor="eq-notes"
                error={form.errors.notes}
            >
                <Textarea
                    id="eq-notes"
                    rows={3}
                    value={form.data.notes}
                    onChange={(event) =>
                        form.setData('notes', event.target.value)
                    }
                />
            </Field>
        </FormDialog>
    );
}

Enquiries.layout = {
    breadcrumbs: [
        { title: 'Website enquiries', href: admin.website.enquiries.index() },
    ],
};
