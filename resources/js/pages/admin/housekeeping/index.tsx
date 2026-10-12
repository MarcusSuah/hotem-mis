import { Head, router, useForm } from '@inertiajs/react';
import {
    ClipboardCheck,
    PackageSearch,
    Play,
    Plus,
    Sparkles,
    Wrench,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { Field } from '@/components/admin/field';
import { OptionSelect } from '@/components/admin/option-select';
import type { Option } from '@/components/admin/option-select';
import { FormDialog } from '@/components/admin/form-dialog';
import { PageHeader } from '@/components/admin/page-header';
import { StatCard } from '@/components/admin/stat-card';
import { HousekeepingBadge } from '@/components/admin/status-badges';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import { notifyValidationFailed } from '@/lib/form';
import { formatDate, formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';

type ChecklistLine = { item: string; done: boolean };

type Task = {
    id: number;
    room: string;
    floor: number;
    room_status: string;
    type: string;
    type_label: string;
    priority: string;
    status: string;
    status_label: string;
    scheduled_for: string;
    assignee: { id: number; name: string } | null;
    checklist: ChecklistLine[];
    notes: string | null;
    can_work: boolean;
};

type LostItem = {
    id: number;
    reference: string;
    description: string;
    location_found: string | null;
    storage_location: string | null;
    claimed_by_name: string | null;
    notes: string | null;
    room_id: number | null;
    room: string | null;
    status: string;
    status_label: string;
    found_at: string;
};

type Props = {
    date: string;
    filters: { status: string };
    tasks: Task[];
    rooms: { id: number; number: string; floor: number; status: string }[];
    staff: { id: number; name: string }[];
    lostFound: LostItem[];
    options: {
        types: Option[];
        priorities: Option[];
        statuses: Option[];
        roomStatuses: Option[];
        lostFoundStatuses: Option[];
        maintenanceCategories: Option[];
    };
    can: { manage: boolean; reportIssue: boolean };
};

const priorityVariant: Record<
    string,
    'danger' | 'warning' | 'secondary' | 'muted'
> = { urgent: 'danger', high: 'warning', normal: 'secondary', low: 'muted' };

export default function Housekeeping({
    date,
    filters,
    tasks,
    rooms,
    staff,
    lostFound,
    options,
    can,
}: Props) {
    const [working, setWorking] = useState<Task | null>(null);
    const [inspecting, setInspecting] = useState<Task | null>(null);
    const [reporting, setReporting] = useState<Task | null>(null);
    const [creating, setCreating] = useState(false);
    const [lostItem, setLostItem] = useState<LostItem | null | undefined>(
        undefined,
    );

    const open = tasks.filter((task) =>
        ['pending', 'assigned', 'in_progress', 'reopened'].includes(
            task.status,
        ),
    );
    const toInspect = tasks.filter((task) => task.status === 'completed');

    function visit(params: Record<string, string>) {
        router.get(
            admin.housekeeping.index.url(),
            { date, status: filters.status, ...params },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    }

    function start(task: Task) {
        router.post(
            admin.housekeeping.tasks.start.url(task.id),
            {},
            { preserveScroll: true },
        );
    }

    function assign(task: Task, userId: string) {
        router.put(
            admin.housekeeping.tasks.assign.url(task.id),
            { assigned_to: userId === 'none' ? null : Number(userId) },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Housekeeping" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Housekeeping"
                    description={`Work for ${formatDate(date)}. Rooms become ready for arrivals once their task is inspected.`}
                    actions={
                        <>
                            <Input
                                type="date"
                                aria-label="Work date"
                                className="w-40"
                                value={date}
                                onChange={(event) =>
                                    event.target.value &&
                                    visit({ date: event.target.value })
                                }
                            />
                            {can.manage && (
                                <Button onClick={() => setCreating(true)}>
                                    <Plus />
                                    New task
                                </Button>
                            )}
                        </>
                    }
                />

                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard
                        label="Open tasks"
                        value={open.length}
                        icon={Sparkles}
                    />
                    <StatCard
                        label="Awaiting inspection"
                        value={toInspect.length}
                        icon={ClipboardCheck}
                        tone="gold"
                    />
                    <StatCard
                        label="Dirty rooms"
                        value={
                            rooms.filter((room) => room.status === 'dirty')
                                .length
                        }
                    />
                </div>

                <Tabs defaultValue="tasks" className="gap-4">
                    <TabsList>
                        <TabsTrigger value="tasks">Tasks</TabsTrigger>
                        {can.manage && (
                            <TabsTrigger value="rooms">Room status</TabsTrigger>
                        )}
                        <TabsTrigger value="lost">Lost & found</TabsTrigger>
                    </TabsList>

                    <TabsContent value="tasks" className="space-y-3">
                        <Select
                            value={filters.status || 'all'}
                            onValueChange={(value) =>
                                visit({ status: value === 'all' ? '' : value })
                            }
                        >
                            <SelectTrigger className="w-48" aria-label="Status">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    All statuses
                                </SelectItem>
                                {options.statuses.map((option) => (
                                    <SelectItem
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {tasks.length === 0 && (
                            <p className="rounded-xl border bg-card p-8 text-center text-sm text-muted-foreground">
                                No housekeeping work for this day.
                            </p>
                        )}
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            {tasks.map((task) => {
                                const done = task.checklist.filter(
                                    (line) => line.done,
                                ).length;

                                return (
                                    <article
                                        key={task.id}
                                        className={cn(
                                            'flex flex-col gap-3 rounded-xl border bg-card p-4 shadow-xs',
                                            task.priority === 'urgent' &&
                                                'border-l-4 border-l-destructive',
                                        )}
                                    >
                                        <div className="flex items-start justify-between">
                                            <div>
                                                <p className="font-display text-2xl font-semibold">
                                                    Room {task.room}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {task.type_label}
                                                </p>
                                            </div>
                                            <div className="flex flex-col items-end gap-1">
                                                <Badge
                                                    variant={
                                                        priorityVariant[
                                                            task.priority
                                                        ]
                                                    }
                                                    className="capitalize"
                                                >
                                                    {task.priority}
                                                </Badge>
                                                <HousekeepingBadge
                                                    status={task.room_status}
                                                />
                                            </div>
                                        </div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span>{task.status_label}</span>
                                            <span className="text-muted-foreground">
                                                Checklist {done}/
                                                {task.checklist.length}
                                            </span>
                                        </div>
                                        {task.scheduled_for < date && (
                                            <p className="text-xs text-warning">
                                                Carried over from{' '}
                                                {formatDate(task.scheduled_for)}
                                            </p>
                                        )}
                                        {task.notes && (
                                            <p className="rounded-md bg-muted/60 p-2 text-xs">
                                                {task.notes}
                                            </p>
                                        )}
                                        {can.manage ? (
                                            <Select
                                                value={
                                                    task.assignee
                                                        ? String(
                                                              task.assignee.id,
                                                          )
                                                        : 'none'
                                                }
                                                onValueChange={(value) =>
                                                    assign(task, value)
                                                }
                                                disabled={
                                                    ![
                                                        'pending',
                                                        'assigned',
                                                        'in_progress',
                                                        'reopened',
                                                    ].includes(task.status)
                                                }
                                            >
                                                <SelectTrigger
                                                    aria-label={`Assignee for room ${task.room}`}
                                                >
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="none">
                                                        Unassigned
                                                    </SelectItem>
                                                    {staff.map((member) => (
                                                        <SelectItem
                                                            key={member.id}
                                                            value={String(
                                                                member.id,
                                                            )}
                                                        >
                                                            {member.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        ) : (
                                            <p className="text-sm text-muted-foreground">
                                                {task.assignee
                                                    ? `Assigned to ${task.assignee.name}`
                                                    : 'Unassigned — you can pick it up'}
                                            </p>
                                        )}
                                        <div className="mt-auto flex flex-wrap gap-2">
                                            {task.can_work &&
                                                [
                                                    'pending',
                                                    'assigned',
                                                    'reopened',
                                                ].includes(task.status) && (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() =>
                                                            start(task)
                                                        }
                                                    >
                                                        <Play />
                                                        Start
                                                    </Button>
                                                )}
                                            {task.can_work &&
                                                [
                                                    'pending',
                                                    'assigned',
                                                    'in_progress',
                                                    'reopened',
                                                ].includes(task.status) && (
                                                    <Button
                                                        size="sm"
                                                        onClick={() =>
                                                            setWorking(task)
                                                        }
                                                    >
                                                        <ClipboardCheck />
                                                        Checklist
                                                    </Button>
                                                )}
                                            {can.manage &&
                                                task.status === 'completed' && (
                                                    <Button
                                                        size="sm"
                                                        variant="gold"
                                                        onClick={() =>
                                                            setInspecting(task)
                                                        }
                                                    >
                                                        Inspect
                                                    </Button>
                                                )}
                                            {can.reportIssue && (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        setReporting(task)
                                                    }
                                                >
                                                    <Wrench />
                                                    Report issue
                                                </Button>
                                            )}
                                        </div>
                                    </article>
                                );
                            })}
                        </div>
                    </TabsContent>

                    {can.manage && (
                        <TabsContent value="rooms">
                            <div className="grid grid-cols-3 gap-2 sm:grid-cols-6 lg:grid-cols-10">
                                {rooms.map((room) => (
                                    <div
                                        key={room.id}
                                        className="rounded-lg border bg-card p-2 text-center"
                                    >
                                        <p className="font-semibold">
                                            {room.number}
                                        </p>
                                        <HousekeepingBadge
                                            status={room.status}
                                        />
                                    </div>
                                ))}
                            </div>
                        </TabsContent>
                    )}

                    <TabsContent value="lost">
                        <div className="rounded-xl border bg-card shadow-xs">
                            <div className="flex justify-end border-b p-3">
                                <Button
                                    size="sm"
                                    onClick={() => setLostItem(null)}
                                >
                                    <PackageSearch />
                                    Log item
                                </Button>
                            </div>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Item</TableHead>
                                        <TableHead>Found</TableHead>
                                        <TableHead>Stored at</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {lostFound.length === 0 && (
                                        <TableRow>
                                            <TableCell
                                                colSpan={5}
                                                className="text-center text-muted-foreground"
                                            >
                                                Nothing logged.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                    {lostFound.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell className="whitespace-normal">
                                                {item.description}
                                                <span className="block font-mono text-xs text-muted-foreground">
                                                    {item.reference}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                {formatDateTime(item.found_at)}
                                                <span className="block text-xs text-muted-foreground">
                                                    {item.room
                                                        ? `Room ${item.room}`
                                                        : item.location_found}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                {item.storage_location ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        item.status === 'stored'
                                                            ? 'gold'
                                                            : 'muted'
                                                    }
                                                >
                                                    {item.status_label}
                                                </Badge>
                                                {item.claimed_by_name && (
                                                    <span className="block text-xs text-muted-foreground">
                                                        to{' '}
                                                        {item.claimed_by_name}
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        setLostItem(item)
                                                    }
                                                >
                                                    Update
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    </TabsContent>
                </Tabs>
            </div>

            <ChecklistDialog task={working} onClose={() => setWorking(null)} />
            <InspectDialog
                task={inspecting}
                onClose={() => setInspecting(null)}
            />
            <IssueDialog
                task={reporting}
                onClose={() => setReporting(null)}
                categories={options.maintenanceCategories}
                priorities={options.priorities}
            />
            <TaskDialog
                open={creating}
                onClose={() => setCreating(false)}
                rooms={rooms}
                staff={staff}
                options={options}
                date={date}
            />
            <LostFoundDialog
                item={lostItem}
                onClose={() => setLostItem(undefined)}
                rooms={rooms}
                statuses={options.lostFoundStatuses}
            />
        </>
    );
}

function ChecklistDialog({
    task,
    onClose,
}: {
    task: Task | null;
    onClose: () => void;
}) {
    const form = useForm<{ checklist: ChecklistLine[]; notes: string }>({
        checklist: [],
        notes: '',
    });

    useEffect(() => {
        if (task) {
            form.clearErrors();
            form.setData({
                checklist: task.checklist,
                notes: task.notes ?? '',
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [task?.id]);

    const allDone = form.data.checklist.every((line) => line.done);

    return (
        <FormDialog
            open={task !== null}
            onOpenChange={(open) => !open && onClose()}
            title={`Room ${task?.room} — ${task?.type_label}`}
            description="Tick each item as you finish it. The room is marked clean when everything is done."
            submitLabel={allDone ? 'Mark room clean' : 'Finish all items first'}
            processing={form.processing}
            onSubmit={() =>
                task &&
                allDone &&
                form.post(admin.housekeeping.tasks.complete.url(task.id), {
                    preserveScroll: true,
                    onSuccess: onClose,
                    onError: notifyValidationFailed,
                })
            }
        >
            <ul className="space-y-2">
                {form.data.checklist.map((line, index) => (
                    <li key={line.item}>
                        <label
                            htmlFor={`check-${index}`}
                            className="flex items-center gap-3 rounded-md border p-3 text-sm has-data-[state=checked]:bg-success/10"
                        >
                            <Checkbox
                                id={`check-${index}`}
                                checked={line.done}
                                onCheckedChange={(checked) =>
                                    form.setData(
                                        'checklist',
                                        form.data.checklist.map(
                                            (item, position) =>
                                                position === index
                                                    ? {
                                                          ...item,
                                                          done:
                                                              checked === true,
                                                      }
                                                    : item,
                                        ),
                                    )
                                }
                            />
                            {line.item}
                        </label>
                    </li>
                ))}
            </ul>
            {form.errors.checklist && (
                <p className="text-sm text-destructive">
                    {form.errors.checklist}
                </p>
            )}
            <Field label="Notes" htmlFor="task-notes" error={form.errors.notes}>
                <Textarea
                    id="task-notes"
                    rows={2}
                    value={form.data.notes}
                    onChange={(event) =>
                        form.setData('notes', event.target.value)
                    }
                />
            </Field>
        </FormDialog>
    );
}

function InspectDialog({
    task,
    onClose,
}: {
    task: Task | null;
    onClose: () => void;
}) {
    const form = useForm({ passed: true, notes: '' });

    useEffect(() => {
        if (task) {
            form.reset();
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [task?.id]);

    return (
        <FormDialog
            open={task !== null}
            onOpenChange={(open) => !open && onClose()}
            title={`Inspect room ${task?.room}`}
            submitLabel={
                form.data.passed
                    ? 'Pass inspection'
                    : 'Send back for re-cleaning'
            }
            processing={form.processing}
            onSubmit={() =>
                task &&
                form.post(admin.housekeeping.tasks.inspect.url(task.id), {
                    preserveScroll: true,
                    onSuccess: onClose,
                    onError: notifyValidationFailed,
                })
            }
        >
            <div className="grid grid-cols-2 gap-2">
                <Button
                    type="button"
                    variant={form.data.passed ? 'default' : 'outline'}
                    onClick={() => form.setData('passed', true)}
                >
                    Passed
                </Button>
                <Button
                    type="button"
                    variant={!form.data.passed ? 'destructive' : 'outline'}
                    onClick={() => form.setData('passed', false)}
                >
                    Failed
                </Button>
            </div>
            <Field
                label={
                    form.data.passed
                        ? 'Notes (optional)'
                        : 'What needs redoing?'
                }
                htmlFor="inspect-notes"
                error={form.errors.notes}
                required={!form.data.passed}
            >
                <Textarea
                    id="inspect-notes"
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

function IssueDialog({
    task,
    onClose,
    categories,
    priorities,
}: {
    task: Task | null;
    onClose: () => void;
    categories: Option[];
    priorities: Option[];
}) {
    const form = useForm({
        title: '',
        category: 'other',
        priority: 'normal',
        description: '',
    });

    useEffect(() => {
        if (task) {
            form.reset();
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [task?.id]);

    return (
        <FormDialog
            open={task !== null}
            onOpenChange={(open) => !open && onClose()}
            title={`Report an issue in room ${task?.room}`}
            description="Creates a maintenance request linked to this task."
            submitLabel="Send to maintenance"
            processing={form.processing}
            onSubmit={() =>
                task &&
                form.post(admin.housekeeping.tasks.escalate.url(task.id), {
                    preserveScroll: true,
                    onSuccess: onClose,
                    onError: notifyValidationFailed,
                })
            }
        >
            <Field
                label="What's wrong?"
                htmlFor="issue-title"
                error={form.errors.title}
                required
            >
                <Input
                    id="issue-title"
                    value={form.data.title}
                    onChange={(event) =>
                        form.setData('title', event.target.value)
                    }
                    placeholder="Shower drain blocked"
                />
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Category"
                    htmlFor="issue-category"
                    error={form.errors.category}
                    required
                >
                    <OptionSelect
                        id="issue-category"
                        value={form.data.category}
                        options={categories}
                        onChange={(value) => form.setData('category', value)}
                    />
                </Field>
                <Field
                    label="Priority"
                    htmlFor="issue-priority"
                    error={form.errors.priority}
                    required
                >
                    <OptionSelect
                        id="issue-priority"
                        value={form.data.priority}
                        options={priorities}
                        onChange={(value) => form.setData('priority', value)}
                    />
                </Field>
            </div>
            <Field
                label="Details"
                htmlFor="issue-description"
                error={form.errors.description}
            >
                <Textarea
                    id="issue-description"
                    rows={3}
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                />
            </Field>
        </FormDialog>
    );
}

function TaskDialog({
    open,
    onClose,
    rooms,
    staff,
    options,
    date,
}: {
    open: boolean;
    onClose: () => void;
    rooms: Props['rooms'];
    staff: Props['staff'];
    options: Props['options'];
    date: string;
}) {
    const form = useForm({
        room_id: '',
        type: 'deep_clean',
        priority: 'normal',
        scheduled_for: date,
        assigned_to: '',
        notes: '',
    });

    useEffect(() => {
        if (open) {
            form.reset();
            form.clearErrors();
            form.setData('scheduled_for', date);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    return (
        <FormDialog
            open={open}
            onOpenChange={(next) => !next && onClose()}
            title="New housekeeping task"
            processing={form.processing}
            onSubmit={() =>
                form.post(admin.housekeeping.tasks.store.url(), {
                    preserveScroll: true,
                    onSuccess: onClose,
                    onError: notifyValidationFailed,
                })
            }
        >
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Room"
                    htmlFor="task-room"
                    error={form.errors.room_id}
                    required
                >
                    <OptionSelect
                        id="task-room"
                        value={form.data.room_id}
                        options={rooms.map((room) => ({
                            value: String(room.id),
                            label: `Room ${room.number}`,
                        }))}
                        onChange={(value) => form.setData('room_id', value)}
                        placeholder="Choose a room"
                    />
                </Field>
                <Field
                    label="Type"
                    htmlFor="task-type"
                    error={form.errors.type}
                    required
                >
                    <OptionSelect
                        id="task-type"
                        value={form.data.type}
                        options={options.types}
                        onChange={(value) => form.setData('type', value)}
                    />
                </Field>
                <Field
                    label="Priority"
                    htmlFor="task-priority"
                    error={form.errors.priority}
                    required
                >
                    <OptionSelect
                        id="task-priority"
                        value={form.data.priority}
                        options={options.priorities}
                        onChange={(value) => form.setData('priority', value)}
                    />
                </Field>
                <Field
                    label="Date"
                    htmlFor="task-date"
                    error={form.errors.scheduled_for}
                    required
                >
                    <Input
                        id="task-date"
                        type="date"
                        value={form.data.scheduled_for}
                        onChange={(event) =>
                            form.setData('scheduled_for', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Assign to"
                    htmlFor="task-assignee"
                    error={form.errors.assigned_to}
                    className="sm:col-span-2"
                >
                    <OptionSelect
                        id="task-assignee"
                        value={form.data.assigned_to}
                        options={staff.map((member) => ({
                            value: String(member.id),
                            label: member.name,
                        }))}
                        onChange={(value) => form.setData('assigned_to', value)}
                        placeholder="Unassigned"
                    />
                </Field>
                <Field
                    label="Notes"
                    htmlFor="task-notes-new"
                    error={form.errors.notes}
                    className="sm:col-span-2"
                >
                    <Textarea
                        id="task-notes-new"
                        rows={2}
                        value={form.data.notes}
                        onChange={(event) =>
                            form.setData('notes', event.target.value)
                        }
                    />
                </Field>
            </div>
        </FormDialog>
    );
}

function LostFoundDialog({
    item,
    onClose,
    rooms,
    statuses,
}: {
    item: LostItem | null | undefined;
    onClose: () => void;
    rooms: Props['rooms'];
    statuses: Option[];
}) {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    const blank = {
        description: '',
        room_id: '',
        location_found: '',
        storage_location: '',
        found_at: now.toISOString().slice(0, 16),
        status: 'stored',
        claimed_by_name: '',
        notes: '',
    };
    const form = useForm(blank);

    useEffect(() => {
        if (item !== undefined) {
            form.clearErrors();
            form.setData(
                item === null
                    ? blank
                    : {
                          description: item.description,
                          room_id: item.room_id ? String(item.room_id) : '',
                          location_found: item.location_found ?? '',
                          storage_location: item.storage_location ?? '',
                          found_at: item.found_at.slice(0, 16),
                          status: item.status,
                          claimed_by_name: item.claimed_by_name ?? '',
                          notes: item.notes ?? '',
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
            form.put(admin.housekeeping.lostFound.update.url(item.id), options);
        } else {
            form.post(admin.housekeeping.lostFound.store.url(), options);
        }
    }

    return (
        <FormDialog
            open={item !== undefined}
            onOpenChange={(open) => !open && onClose()}
            title={item ? `Update ${item.reference}` : 'Log a found item'}
            processing={form.processing}
            onSubmit={submit}
        >
            <Field
                label="Description"
                htmlFor="lf-description"
                error={form.errors.description}
                required
            >
                <Input
                    id="lf-description"
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                />
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="Room"
                    htmlFor="lf-room"
                    error={form.errors.room_id}
                >
                    <OptionSelect
                        id="lf-room"
                        value={form.data.room_id}
                        options={rooms.map((room) => ({
                            value: String(room.id),
                            label: `Room ${room.number}`,
                        }))}
                        onChange={(value) => form.setData('room_id', value)}
                        placeholder="Public area"
                    />
                </Field>
                <Field
                    label="Where exactly"
                    htmlFor="lf-location"
                    error={form.errors.location_found}
                >
                    <Input
                        id="lf-location"
                        value={form.data.location_found}
                        onChange={(event) =>
                            form.setData('location_found', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Found at"
                    htmlFor="lf-found"
                    error={form.errors.found_at}
                    required
                >
                    <Input
                        id="lf-found"
                        type="datetime-local"
                        value={form.data.found_at}
                        onChange={(event) =>
                            form.setData('found_at', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Stored at"
                    htmlFor="lf-storage"
                    error={form.errors.storage_location}
                >
                    <Input
                        id="lf-storage"
                        value={form.data.storage_location}
                        onChange={(event) =>
                            form.setData('storage_location', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Status"
                    htmlFor="lf-status"
                    error={form.errors.status}
                    required
                >
                    <OptionSelect
                        id="lf-status"
                        value={form.data.status}
                        options={statuses}
                        onChange={(value) => form.setData('status', value)}
                    />
                </Field>
                {form.data.status === 'claimed' && (
                    <Field
                        label="Collected by"
                        htmlFor="lf-claimed"
                        error={form.errors.claimed_by_name}
                        required
                    >
                        <Input
                            id="lf-claimed"
                            value={form.data.claimed_by_name}
                            onChange={(event) =>
                                form.setData(
                                    'claimed_by_name',
                                    event.target.value,
                                )
                            }
                        />
                    </Field>
                )}
            </div>
            <Field label="Notes" htmlFor="lf-notes" error={form.errors.notes}>
                <Textarea
                    id="lf-notes"
                    rows={2}
                    value={form.data.notes}
                    onChange={(event) =>
                        form.setData('notes', event.target.value)
                    }
                />
            </Field>
        </FormDialog>
    );
}

Housekeeping.layout = {
    breadcrumbs: [{ title: 'Housekeeping', href: admin.housekeeping.index() }],
};
