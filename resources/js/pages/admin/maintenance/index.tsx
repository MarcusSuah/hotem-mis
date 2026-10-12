import { Head, Link, useForm } from '@inertiajs/react';
import { CalendarClock, Lock, Plus, Search, Wrench } from 'lucide-react';
import { useEffect, useState } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { Field } from '@/components/admin/field';
import { FormDialog } from '@/components/admin/form-dialog';
import { FormErrorSummary } from '@/components/admin/form-error-summary';
import { ImagePicker } from '@/components/admin/image-picker';
import { OptionSelect } from '@/components/admin/option-select';
import type { Option } from '@/components/admin/option-select';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { formatDate, formatDateTime } from '@/lib/format';
import admin from '@/routes/admin';
import type { Paginated } from '@/types';

type Row = {
    reference: string;
    title: string;
    room: string | null;
    location: string | null;
    category_label: string;
    priority: string;
    status: string;
    status_label: string;
    assignee: string | null;
    blocks_room: boolean;
    created_at: string | null;
};

type Schedule = {
    id: number;
    title: string;
    description: string | null;
    room_id: number | null;
    location: string | null;
    frequency_days: number;
    assigned_to: number | null;
    is_active: boolean;
    category: string;
    priority: string;
    next_due_on: string;
    room: string | null;
    assignee: string | null;
};

export type MaintenanceOptions = {
    options: { categories: Option[]; priorities: Option[]; statuses: Option[] };
    rooms: { id: number; number: string }[];
    technicians: { id: number; name: string }[];
};

type Filters = {
    search: string;
    status: string;
    priority: string;
    category: string;
    mine: string;
};

export const priorityVariant: Record<
    string,
    'danger' | 'warning' | 'secondary' | 'muted'
> = { urgent: 'danger', high: 'warning', normal: 'secondary', low: 'muted' };

export default function MaintenanceIndex(
    props: MaintenanceOptions & {
        requests: Paginated<Row>;
        filters: Filters;
        schedules: Schedule[];
        can: { report: boolean; manage: boolean };
    },
) {
    const { requests, schedules, options, can } = props;
    const { filters, setFilter } = useFilters<Filters>(
        admin.maintenance.index.url(),
        props.filters,
    );
    const [reporting, setReporting] = useState(false);
    const [schedule, setSchedule] = useState<Schedule | null | undefined>(
        undefined,
    );

    return (
        <>
            <Head title="Maintenance" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Maintenance"
                    description="Repairs, preventive work and rooms taken out of service."
                    actions={
                        can.report && (
                            <Button onClick={() => setReporting(true)}>
                                <Plus />
                                Report an issue
                            </Button>
                        )
                    }
                />

                <Tabs defaultValue="requests" className="gap-4">
                    <TabsList>
                        <TabsTrigger value="requests">Requests</TabsTrigger>
                        {can.manage && (
                            <TabsTrigger value="schedules">
                                Preventive schedules
                            </TabsTrigger>
                        )}
                    </TabsList>

                    <TabsContent value="requests">
                        <div className="rounded-xl border bg-card shadow-xs">
                            <div className="grid gap-3 border-b p-4 sm:grid-cols-2 lg:grid-cols-5">
                                <div className="relative sm:col-span-2">
                                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <Input
                                        aria-label="Search"
                                        placeholder="Reference, title or room"
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
                                    ariaLabel="Status"
                                    value={filters.status}
                                    options={[
                                        {
                                            value: 'active',
                                            label: 'Active work',
                                        },
                                        ...options.statuses,
                                    ]}
                                    onChange={(value) =>
                                        setFilter(
                                            'status',
                                            value || 'active',
                                            true,
                                        )
                                    }
                                />
                                <OptionSelect
                                    ariaLabel="Priority"
                                    value={filters.priority}
                                    options={options.priorities}
                                    placeholder="Any priority"
                                    onChange={(value) =>
                                        setFilter('priority', value, true)
                                    }
                                />
                                <label
                                    htmlFor="mine"
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <Checkbox
                                        id="mine"
                                        checked={filters.mine === '1'}
                                        onCheckedChange={(checked) =>
                                            setFilter(
                                                'mine',
                                                checked === true ? '1' : '',
                                                true,
                                            )
                                        }
                                    />
                                    Assigned to me
                                </label>
                            </div>
                            {requests.data.length === 0 ? (
                                <EmptyState
                                    icon={Wrench}
                                    title="No maintenance requests"
                                />
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Request</TableHead>
                                            <TableHead>Where</TableHead>
                                            <TableHead>Priority</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="hidden md:table-cell">
                                                Assigned
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {requests.data.map((row) => (
                                            <TableRow key={row.reference}>
                                                <TableCell className="whitespace-normal">
                                                    <Link
                                                        href={admin.maintenance.show(
                                                            row.reference,
                                                        )}
                                                        className="font-medium hover:underline"
                                                    >
                                                        {row.title}
                                                    </Link>
                                                    <span className="block font-mono text-xs text-muted-foreground">
                                                        {row.reference} ·{' '}
                                                        {row.category_label} ·{' '}
                                                        {formatDateTime(
                                                            row.created_at,
                                                        )}
                                                    </span>
                                                </TableCell>
                                                <TableCell>
                                                    {row.room
                                                        ? `Room ${row.room}`
                                                        : row.location}
                                                    {row.blocks_room && (
                                                        <Badge
                                                            variant="warning"
                                                            className="ml-1"
                                                        >
                                                            <Lock />
                                                            Blocked
                                                        </Badge>
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant={
                                                            priorityVariant[
                                                                row.priority
                                                            ]
                                                        }
                                                        className="capitalize"
                                                    >
                                                        {row.priority}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell>
                                                    {row.status_label}
                                                </TableCell>
                                                <TableCell className="hidden md:table-cell">
                                                    {row.assignee ?? '—'}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            )}
                            <div className="border-t p-4">
                                <Pagination paginator={requests} />
                            </div>
                        </div>
                    </TabsContent>

                    {can.manage && (
                        <TabsContent value="schedules">
                            <div className="rounded-xl border bg-card shadow-xs">
                                <div className="flex justify-end border-b p-3">
                                    <Button
                                        size="sm"
                                        onClick={() => setSchedule(null)}
                                    >
                                        <CalendarClock />
                                        New schedule
                                    </Button>
                                </div>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Work</TableHead>
                                            <TableHead>Every</TableHead>
                                            <TableHead>Next due</TableHead>
                                            <TableHead>Assigned</TableHead>
                                            <TableHead />
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {schedules.map((item) => (
                                            <TableRow key={item.id}>
                                                <TableCell>
                                                    {item.title}{' '}
                                                    {!item.is_active && (
                                                        <Badge variant="muted">
                                                            Paused
                                                        </Badge>
                                                    )}
                                                    <span className="block text-xs text-muted-foreground">
                                                        {item.room
                                                            ? `Room ${item.room}`
                                                            : item.location}
                                                    </span>
                                                </TableCell>
                                                <TableCell>
                                                    {item.frequency_days} days
                                                </TableCell>
                                                <TableCell>
                                                    {formatDate(
                                                        item.next_due_on,
                                                    )}
                                                </TableCell>
                                                <TableCell>
                                                    {item.assignee ?? '—'}
                                                </TableCell>
                                                <TableCell>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() =>
                                                            setSchedule(item)
                                                        }
                                                    >
                                                        Edit
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>
                        </TabsContent>
                    )}
                </Tabs>
            </div>

            <ReportDialog
                open={reporting}
                onClose={() => setReporting(false)}
                {...props}
            />
            <ScheduleDialog
                schedule={schedule}
                onClose={() => setSchedule(undefined)}
                {...props}
            />
        </>
    );
}

function ReportDialog({
    open,
    onClose,
    options,
    rooms,
    technicians,
    can,
}: MaintenanceOptions & {
    open: boolean;
    onClose: () => void;
    can: { manage: boolean };
}) {
    const blank = {
        title: '',
        description: '',
        category: 'other',
        priority: 'normal',
        room_id: '',
        location: '',
        assigned_to: '',
        estimated_cost: '',
        block_room: false,
        block_from: '',
        block_until: '',
        photos: [] as File[],
    };
    const form = useForm(blank);
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
            title="Report a maintenance issue"
            submitLabel="Log request"
            processing={form.processing}
            size="lg"
            onSubmit={() =>
                form.post(admin.maintenance.store.url(), {
                    forceFormData: true,
                    onSuccess: onClose,
                    onError: notifyValidationFailed,
                })
            }
        >
            <FormErrorSummary errors={errors} />
            <Field
                label="Problem"
                htmlFor="m-title"
                error={errors.title}
                required
            >
                <Input
                    id="m-title"
                    value={data.title}
                    onChange={(event) => setData('title', event.target.value)}
                    placeholder="Air conditioner leaking water"
                />
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Category"
                    htmlFor="m-category"
                    error={errors.category}
                    required
                >
                    <OptionSelect
                        id="m-category"
                        value={data.category}
                        options={options.categories}
                        onChange={(value) => setData('category', value)}
                    />
                </Field>
                <Field
                    label="Priority"
                    htmlFor="m-priority"
                    error={errors.priority}
                    required
                >
                    <OptionSelect
                        id="m-priority"
                        value={data.priority}
                        options={options.priorities}
                        onChange={(value) => setData('priority', value)}
                    />
                </Field>
                <Field label="Room" htmlFor="m-room" error={errors.room_id}>
                    <OptionSelect
                        id="m-room"
                        value={data.room_id}
                        options={rooms.map((room) => ({
                            value: String(room.id),
                            label: `Room ${room.number}`,
                        }))}
                        placeholder="Not a guest room"
                        onChange={(value) => setData('room_id', value)}
                    />
                </Field>
                {!data.room_id && (
                    <Field
                        label="Location"
                        htmlFor="m-location"
                        error={errors.location}
                        required
                    >
                        <Input
                            id="m-location"
                            value={data.location}
                            onChange={(event) =>
                                setData('location', event.target.value)
                            }
                            placeholder="Kitchen, pool pump room…"
                        />
                    </Field>
                )}
                {can.manage && (
                    <Field
                        label="Assign to"
                        htmlFor="m-assignee"
                        error={errors.assigned_to}
                    >
                        <OptionSelect
                            id="m-assignee"
                            value={data.assigned_to}
                            options={technicians.map((tech) => ({
                                value: String(tech.id),
                                label: tech.name,
                            }))}
                            placeholder="Unassigned"
                            onChange={(value) => setData('assigned_to', value)}
                        />
                    </Field>
                )}
                <Field
                    label="Estimated cost"
                    htmlFor="m-cost"
                    error={errors.estimated_cost}
                >
                    <Input
                        id="m-cost"
                        inputMode="decimal"
                        value={data.estimated_cost}
                        onChange={(event) =>
                            setData('estimated_cost', event.target.value)
                        }
                    />
                </Field>
            </div>
            <Field
                label="Details"
                htmlFor="m-description"
                error={errors.description}
            >
                <Textarea
                    id="m-description"
                    rows={3}
                    value={data.description}
                    onChange={(event) =>
                        setData('description', event.target.value)
                    }
                />
            </Field>
            {data.room_id && (
                <div className="space-y-3 rounded-lg border border-dashed p-3">
                    <div className="flex items-center gap-2">
                        <Switch
                            id="m-block"
                            checked={data.block_room}
                            onCheckedChange={(checked) =>
                                setData('block_room', checked)
                            }
                        />
                        <Label htmlFor="m-block">
                            Take the room out of service
                        </Label>
                    </div>
                    {data.block_room && (
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                                label="From"
                                htmlFor="m-from"
                                error={errors.block_from}
                                required
                            >
                                <Input
                                    id="m-from"
                                    type="date"
                                    value={data.block_from}
                                    onChange={(event) =>
                                        setData(
                                            'block_from',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                label="Back in service"
                                htmlFor="m-until"
                                error={errors.block_until}
                                required
                            >
                                <Input
                                    id="m-until"
                                    type="date"
                                    min={data.block_from || undefined}
                                    value={data.block_until}
                                    onChange={(event) =>
                                        setData(
                                            'block_until',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>
                        </div>
                    )}
                </div>
            )}
            <ImagePicker
                label="Photos"
                multiple
                files={data.photos}
                onFilesChange={(files) => setData('photos', files)}
                error={fieldError(errors, 'photos')}
            />
        </FormDialog>
    );
}

function ScheduleDialog({
    schedule,
    onClose,
    options,
    rooms,
    technicians,
}: MaintenanceOptions & {
    schedule: Schedule | null | undefined;
    onClose: () => void;
}) {
    const blank = {
        title: '',
        description: '',
        room_id: '',
        location: '',
        category: 'preventive',
        priority: 'normal',
        frequency_days: '30',
        next_due_on: '',
        assigned_to: '',
        is_active: true,
    };
    const form = useForm(blank);
    const { data, setData, errors } = form;

    useEffect(() => {
        if (schedule !== undefined) {
            form.clearErrors();
            form.setData(
                schedule === null
                    ? blank
                    : {
                          title: schedule.title,
                          description: schedule.description ?? '',
                          room_id: schedule.room_id
                              ? String(schedule.room_id)
                              : '',
                          location: schedule.location ?? '',
                          category: schedule.category,
                          priority: schedule.priority,
                          frequency_days: String(schedule.frequency_days),
                          next_due_on: schedule.next_due_on,
                          assigned_to: schedule.assigned_to
                              ? String(schedule.assigned_to)
                              : '',
                          is_active: schedule.is_active,
                      },
            );
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [schedule]);

    function submit() {
        const opts = {
            preserveScroll: true,
            onSuccess: onClose,
            onError: notifyValidationFailed,
        };

        if (schedule) {
            form.put(admin.maintenance.schedules.update.url(schedule.id), opts);
        } else {
            form.post(admin.maintenance.schedules.store.url(), opts);
        }
    }

    return (
        <FormDialog
            open={schedule !== undefined}
            onOpenChange={(open) => !open && onClose()}
            title={schedule ? 'Edit schedule' : 'New preventive schedule'}
            description="A maintenance request is created automatically each time the schedule falls due."
            processing={form.processing}
            onSubmit={submit}
        >
            <Field label="Work" htmlFor="s-title" error={errors.title} required>
                <Input
                    id="s-title"
                    value={data.title}
                    onChange={(event) => setData('title', event.target.value)}
                />
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field label="Room" htmlFor="s-room" error={errors.room_id}>
                    <OptionSelect
                        id="s-room"
                        value={data.room_id}
                        options={rooms.map((room) => ({
                            value: String(room.id),
                            label: `Room ${room.number}`,
                        }))}
                        placeholder="Not a guest room"
                        onChange={(value) => setData('room_id', value)}
                    />
                </Field>
                {!data.room_id && (
                    <Field
                        label="Location"
                        htmlFor="s-location"
                        error={errors.location}
                        required
                    >
                        <Input
                            id="s-location"
                            value={data.location}
                            onChange={(event) =>
                                setData('location', event.target.value)
                            }
                        />
                    </Field>
                )}
                <Field
                    label="Category"
                    htmlFor="s-category"
                    error={errors.category}
                    required
                >
                    <OptionSelect
                        id="s-category"
                        value={data.category}
                        options={options.categories}
                        onChange={(value) => setData('category', value)}
                    />
                </Field>
                <Field
                    label="Priority"
                    htmlFor="s-priority"
                    error={errors.priority}
                    required
                >
                    <OptionSelect
                        id="s-priority"
                        value={data.priority}
                        options={options.priorities}
                        onChange={(value) => setData('priority', value)}
                    />
                </Field>
                <Field
                    label="Every (days)"
                    htmlFor="s-frequency"
                    error={errors.frequency_days}
                    required
                >
                    <Input
                        id="s-frequency"
                        type="number"
                        min={1}
                        value={data.frequency_days}
                        onChange={(event) =>
                            setData('frequency_days', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Next due"
                    htmlFor="s-due"
                    error={errors.next_due_on}
                    required
                >
                    <Input
                        id="s-due"
                        type="date"
                        value={data.next_due_on}
                        onChange={(event) =>
                            setData('next_due_on', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Assign to"
                    htmlFor="s-assignee"
                    error={errors.assigned_to}
                >
                    <OptionSelect
                        id="s-assignee"
                        value={data.assigned_to}
                        options={technicians.map((tech) => ({
                            value: String(tech.id),
                            label: tech.name,
                        }))}
                        placeholder="Unassigned"
                        onChange={(value) => setData('assigned_to', value)}
                    />
                </Field>
                <div className="flex items-end gap-2 pb-2">
                    <Switch
                        id="s-active"
                        checked={data.is_active}
                        onCheckedChange={(checked) =>
                            setData('is_active', checked)
                        }
                    />
                    <Label htmlFor="s-active">Active</Label>
                </div>
            </div>
            <Field
                label="Description"
                htmlFor="s-description"
                error={errors.description}
            >
                <Textarea
                    id="s-description"
                    rows={2}
                    value={data.description}
                    onChange={(event) =>
                        setData('description', event.target.value)
                    }
                />
            </Field>
        </FormDialog>
    );
}

MaintenanceIndex.layout = {
    breadcrumbs: [{ title: 'Maintenance', href: admin.maintenance.index() }],
};
