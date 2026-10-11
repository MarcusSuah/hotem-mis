import { Head, router, useForm } from '@inertiajs/react';
import {
    Accessibility,
    Ban,
    DoorOpen,
    LayoutGrid,
    MoreHorizontal,
    Pencil,
    Plus,
    Rows3,
    Search,
    Trash2,
    Unlock,
    Wrench,
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
import { Pagination } from '@/components/admin/pagination';
import {
    HousekeepingBadge,
    RoomStateBadge,
} from '@/components/admin/status-badges';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { useFilters } from '@/hooks/use-filters';
import { fieldError, notifyValidationFailed } from '@/lib/form';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';
import type { MoneyValue, Paginated } from '@/types';

type Option = { value: string; label: string };

type RoomBlock = {
    id: number;
    type: string;
    type_label: string;
    start_date: string;
    end_date: string;
    reason: string;
};

type Room = {
    id: number;
    number: string;
    floor: number;
    building: string | null;
    description: string | null;
    room_type: { id: number; name: string; max_occupancy: number };
    base_rate: MoneyValue;
    operational_status: string;
    housekeeping_status: string;
    is_accessible: boolean;
    accessibility_notes: string | null;
    notes: string | null;
    state: {
        state: string;
        reservation: string | null;
        block_id: number | null;
    };
    images: ExistingImage[];
    blocks: RoomBlock[];
};

type Filters = {
    search: string;
    room_type: string;
    floor: string;
    operational_status: string;
    housekeeping_status: string;
    available_from: string;
    available_to: string;
    view: string;
};

type Props = {
    rooms: Paginated<Room>;
    filters: Filters;
    roomTypes: { id: number; name: string }[];
    floors: number[];
    options: {
        operational: Option[];
        housekeeping: Option[];
        blockTypes: Option[];
    };
    can: { create: boolean; update: boolean; delete: boolean; block: boolean };
};

const stateAccent: Record<string, string> = {
    available: 'border-l-success',
    reserved: 'border-l-highlight',
    occupied: 'border-l-primary',
    maintenance: 'border-l-warning',
    out_of_service: 'border-l-destructive',
    blocked: 'border-l-muted-foreground',
};

export default function RoomsIndex({
    rooms,
    filters: initialFilters,
    roomTypes,
    floors,
    options,
    can,
}: Props) {
    const { filters, setFilter, reset } = useFilters<Filters>(
        admin.rooms.index.url(),
        initialFilters,
    );
    const [editing, setEditing] = useState<Room | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [blocking, setBlocking] = useState<Room | null>(null);
    const [deleting, setDeleting] = useState<Room | null>(null);
    const [releasing, setReleasing] = useState<RoomBlock | null>(null);
    const [processing, setProcessing] = useState(false);

    const hasFilters = Object.entries(filters).some(
        ([key, value]) => key !== 'view' && value !== '',
    );
    const view = filters.view || 'grid';

    function openEdit(room: Room | null) {
        setEditing(room);
        setFormOpen(true);
    }

    function runConfirmed(action: () => void) {
        setProcessing(true);
        action();
    }

    const finish = {
        preserveScroll: true,
        onFinish: () => {
            setProcessing(false);
            setDeleting(null);
            setReleasing(null);
        },
    };

    const actions = (room: Room) => (
        <RoomActions
            room={room}
            can={can}
            onEdit={() => openEdit(room)}
            onBlock={() => setBlocking(room)}
            onRelease={(block) => setReleasing(block)}
            onDelete={() => setDeleting(room)}
        />
    );

    return (
        <>
            <Head title="Rooms" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Rooms"
                    description="Today's state of every room. Reserved and occupied states are derived from bookings; maintenance is scheduled with dated blocks."
                    actions={
                        can.create && (
                            <Button onClick={() => openEdit(null)}>
                                <Plus />
                                New room
                            </Button>
                        )
                    }
                />

                <div className="rounded-xl border bg-card p-4 shadow-xs">
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-8">
                        <div className="relative sm:col-span-2 lg:col-span-1 xl:col-span-2">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                aria-label="Search room number"
                                placeholder="Room number"
                                className="pl-9"
                                value={filters.search}
                                onChange={(event) =>
                                    setFilter('search', event.target.value)
                                }
                            />
                        </div>
                        <FilterSelect
                            label="Room type"
                            value={filters.room_type}
                            onChange={(value) =>
                                setFilter('room_type', value, true)
                            }
                            options={roomTypes.map((type) => ({
                                value: String(type.id),
                                label: type.name,
                            }))}
                        />
                        <FilterSelect
                            label="Floor"
                            value={filters.floor}
                            onChange={(value) =>
                                setFilter('floor', value, true)
                            }
                            options={floors.map((floor) => ({
                                value: String(floor),
                                label: `Floor ${floor}`,
                            }))}
                        />
                        <FilterSelect
                            label="Condition"
                            value={filters.operational_status}
                            onChange={(value) =>
                                setFilter('operational_status', value, true)
                            }
                            options={options.operational}
                        />
                        <FilterSelect
                            label="Housekeeping"
                            value={filters.housekeeping_status}
                            onChange={(value) =>
                                setFilter('housekeeping_status', value, true)
                            }
                            options={options.housekeeping}
                        />
                        <Input
                            type="date"
                            aria-label="Available from"
                            value={filters.available_from}
                            onChange={(event) =>
                                setFilter(
                                    'available_from',
                                    event.target.value,
                                    Boolean(filters.available_to),
                                )
                            }
                        />
                        <Input
                            type="date"
                            aria-label="Available until"
                            value={filters.available_to}
                            min={filters.available_from || undefined}
                            onChange={(event) =>
                                setFilter(
                                    'available_to',
                                    event.target.value,
                                    Boolean(filters.available_from),
                                )
                            }
                        />
                    </div>
                    <div className="mt-3 flex flex-wrap items-center justify-between gap-2">
                        <p className="text-xs text-muted-foreground">
                            {filters.available_from && filters.available_to
                                ? `Showing rooms free from ${formatDate(filters.available_from)} to ${formatDate(filters.available_to)} (check-out day).`
                                : 'Pick two dates to list rooms free for that stay.'}
                        </p>
                        <div className="flex items-center gap-2">
                            {hasFilters && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={reset}
                                >
                                    Clear filters
                                </Button>
                            )}
                            <div
                                className="flex rounded-md border p-0.5"
                                role="group"
                                aria-label="View"
                            >
                                <Button
                                    variant={
                                        view === 'grid' ? 'secondary' : 'ghost'
                                    }
                                    size="sm"
                                    aria-pressed={view === 'grid'}
                                    onClick={() =>
                                        setFilter('view', 'grid', true)
                                    }
                                >
                                    <LayoutGrid />
                                    Cards
                                </Button>
                                <Button
                                    variant={
                                        view === 'table' ? 'secondary' : 'ghost'
                                    }
                                    size="sm"
                                    aria-pressed={view === 'table'}
                                    onClick={() =>
                                        setFilter('view', 'table', true)
                                    }
                                >
                                    <Rows3 />
                                    Table
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                {rooms.data.length === 0 ? (
                    <div className="rounded-xl border bg-card">
                        <EmptyState
                            icon={DoorOpen}
                            title="No rooms match"
                            description={
                                hasFilters
                                    ? 'Try different filters or dates.'
                                    : 'Add rooms to start taking reservations.'
                            }
                        />
                    </div>
                ) : view === 'grid' ? (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                        {rooms.data.map((room) => (
                            <article
                                key={room.id}
                                className={cn(
                                    'flex flex-col gap-3 rounded-xl border border-l-4 bg-card p-4 shadow-xs',
                                    stateAccent[room.state.state],
                                )}
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div>
                                        <p className="font-display text-3xl leading-none font-semibold">
                                            {room.number}
                                        </p>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {room.room_type.name} · Floor{' '}
                                            {room.floor}
                                        </p>
                                    </div>
                                    {actions(room)}
                                </div>
                                <div className="flex flex-wrap gap-1.5">
                                    <RoomStateBadge state={room.state.state} />
                                    <HousekeepingBadge
                                        status={room.housekeeping_status}
                                    />
                                    {room.is_accessible && (
                                        <Badge
                                            variant="secondary"
                                            title={
                                                room.accessibility_notes ??
                                                'Accessible room'
                                            }
                                        >
                                            <Accessibility />
                                            Accessible
                                        </Badge>
                                    )}
                                </div>
                                {room.state.reservation && (
                                    <p className="text-xs text-muted-foreground">
                                        Booking {room.state.reservation}
                                    </p>
                                )}
                                {room.blocks.length > 0 && (
                                    <ul className="space-y-1 text-xs">
                                        {room.blocks.map((block) => (
                                            <li
                                                key={block.id}
                                                className="flex items-center gap-1.5 text-warning"
                                            >
                                                <Wrench className="size-3.5" />
                                                {block.type_label}:{' '}
                                                {formatDate(block.start_date)} –{' '}
                                                {formatDate(block.end_date)}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                <p className="mt-auto text-sm font-medium">
                                    {room.base_rate.formatted} / night
                                </p>
                            </article>
                        ))}
                    </div>
                ) : (
                    <div className="rounded-xl border bg-card shadow-xs">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Room</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Today</TableHead>
                                    <TableHead>Housekeeping</TableHead>
                                    <TableHead className="hidden lg:table-cell">
                                        Upcoming blocks
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Rate
                                    </TableHead>
                                    <TableHead className="w-12">
                                        <span className="sr-only">Actions</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rooms.data.map((room) => (
                                    <TableRow key={room.id}>
                                        <TableCell>
                                            <span className="font-semibold">
                                                {room.number}
                                            </span>
                                            <span className="block text-xs text-muted-foreground">
                                                Floor {room.floor}
                                                {room.building
                                                    ? ` · ${room.building}`
                                                    : ''}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            {room.room_type.name}
                                        </TableCell>
                                        <TableCell>
                                            <RoomStateBadge
                                                state={room.state.state}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <HousekeepingBadge
                                                status={
                                                    room.housekeeping_status
                                                }
                                            />
                                        </TableCell>
                                        <TableCell className="hidden text-xs text-muted-foreground lg:table-cell">
                                            {room.blocks.length === 0
                                                ? '—'
                                                : room.blocks
                                                      .map(
                                                          (block) =>
                                                              `${block.type_label} ${formatDate(block.start_date)}`,
                                                      )
                                                      .join(', ')}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {room.base_rate.formatted}
                                        </TableCell>
                                        <TableCell>{actions(room)}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <Pagination paginator={rooms} />
            </div>

            <RoomFormDialog
                open={formOpen}
                onOpenChange={setFormOpen}
                room={editing}
                roomTypes={roomTypes}
                options={options}
            />
            <BlockDialog
                room={blocking}
                onClose={() => setBlocking(null)}
                blockTypes={options.blockTypes}
            />

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                title={`Delete room ${deleting?.number}?`}
                description="Rooms with upcoming reservations cannot be deleted. Past stays keep their history."
                confirmLabel="Delete room"
                destructive
                processing={processing}
                onConfirm={() =>
                    deleting &&
                    runConfirmed(() =>
                        router.delete(
                            admin.rooms.destroy.url(deleting.id),
                            finish,
                        ),
                    )
                }
            />

            <ConfirmDialog
                open={releasing !== null}
                onOpenChange={(open) => !open && setReleasing(null)}
                title="Release this block?"
                description="The room becomes bookable again for these dates."
                confirmLabel="Release block"
                processing={processing}
                onConfirm={() =>
                    releasing &&
                    runConfirmed(() =>
                        router.delete(
                            admin.roomBlocks.destroy.url(releasing.id),
                            finish,
                        ),
                    )
                }
            />
        </>
    );
}

function FilterSelect({
    label,
    value,
    onChange,
    options,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    options: Option[];
}) {
    return (
        <Select
            value={value || 'all'}
            onValueChange={(next) => onChange(next === 'all' ? '' : next)}
        >
            <SelectTrigger aria-label={label} className="w-full">
                <SelectValue placeholder={label} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">Any {label.toLowerCase()}</SelectItem>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function RoomActions({
    room,
    can,
    onEdit,
    onBlock,
    onRelease,
    onDelete,
}: {
    room: Room;
    can: Props['can'];
    onEdit: () => void;
    onBlock: () => void;
    onRelease: (block: RoomBlock) => void;
    onDelete: () => void;
}) {
    if (!can.update && !can.block && !can.delete) {
        return null;
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label={`Actions for room ${room.number}`}
                >
                    <MoreHorizontal />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end">
                {can.update && (
                    <DropdownMenuItem onSelect={onEdit}>
                        <Pencil />
                        Edit room
                    </DropdownMenuItem>
                )}
                {can.block && (
                    <DropdownMenuItem onSelect={onBlock}>
                        <Ban />
                        Block dates
                    </DropdownMenuItem>
                )}
                {can.block &&
                    room.blocks.map((block) => (
                        <DropdownMenuItem
                            key={block.id}
                            onSelect={() => onRelease(block)}
                        >
                            <Unlock />
                            Release {block.type_label.toLowerCase()} (
                            {formatDate(block.start_date)})
                        </DropdownMenuItem>
                    ))}
                {can.delete && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            variant="destructive"
                            onSelect={onDelete}
                        >
                            <Trash2 />
                            Delete
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

type RoomForm = {
    number: string;
    room_type_id: string;
    floor: string;
    building: string;
    description: string;
    operational_status: string;
    is_accessible: boolean;
    accessibility_notes: string;
    notes: string;
    images: File[];
    remove_image_ids: number[];
};

const blankRoom: RoomForm = {
    number: '',
    room_type_id: '',
    floor: '1',
    building: '',
    description: '',
    operational_status: 'in_service',
    is_accessible: false,
    accessibility_notes: '',
    notes: '',
    images: [],
    remove_image_ids: [],
};

function RoomFormDialog({
    open,
    onOpenChange,
    room,
    roomTypes,
    options,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    room: Room | null;
    roomTypes: { id: number; name: string }[];
    options: Props['options'];
}) {
    const form = useForm<RoomForm>(blankRoom);
    const { data, setData, errors } = form;

    useEffect(() => {
        if (!open) {
            return;
        }

        form.clearErrors();
        form.setData(
            room
                ? {
                      ...blankRoom,
                      number: room.number,
                      room_type_id: String(room.room_type.id),
                      floor: String(room.floor),
                      building: room.building ?? '',
                      description: room.description ?? '',
                      operational_status: room.operational_status,
                      is_accessible: room.is_accessible,
                      accessibility_notes: room.accessibility_notes ?? '',
                      notes: room.notes ?? '',
                  }
                : blankRoom,
        );
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, room?.id]);

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

        if (room) {
            form.transform((values) => ({ ...values, _method: 'put' }));
            form.post(admin.rooms.update.url(room.id), options);
        } else {
            form.transform((values) => values);
            form.post(admin.rooms.store.url(), options);
        }
    }

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title={room ? `Edit room ${room.number}` : 'New room'}
            submitLabel={room ? 'Save changes' : 'Create room'}
            processing={form.processing}
            onSubmit={submit}
            size="lg"
        >
            <FormErrorSummary errors={errors} />
            <div className="grid gap-4 sm:grid-cols-3">
                <Field
                    label="Room number"
                    htmlFor="room-number"
                    error={errors.number}
                    required
                >
                    <Input
                        id="room-number"
                        value={data.number}
                        onChange={(event) =>
                            setData('number', event.target.value)
                        }
                        aria-invalid={Boolean(errors.number)}
                    />
                </Field>
                <Field
                    label="Floor"
                    htmlFor="room-floor"
                    error={errors.floor}
                    required
                >
                    <Input
                        id="room-floor"
                        type="number"
                        value={data.floor}
                        onChange={(event) =>
                            setData('floor', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Building / wing"
                    htmlFor="room-building"
                    error={errors.building}
                >
                    <Input
                        id="room-building"
                        value={data.building}
                        onChange={(event) =>
                            setData('building', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Room type"
                    htmlFor="room-type"
                    error={errors.room_type_id}
                    required
                    className="sm:col-span-2"
                >
                    <Select
                        value={data.room_type_id}
                        onValueChange={(value) =>
                            setData('room_type_id', value)
                        }
                    >
                        <SelectTrigger
                            id="room-type"
                            aria-invalid={Boolean(errors.room_type_id)}
                        >
                            <SelectValue placeholder="Choose a room type" />
                        </SelectTrigger>
                        <SelectContent>
                            {roomTypes.map((type) => (
                                <SelectItem
                                    key={type.id}
                                    value={String(type.id)}
                                >
                                    {type.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>
                <Field
                    label="Condition"
                    htmlFor="room-status"
                    error={errors.operational_status}
                    required
                >
                    <Select
                        value={data.operational_status}
                        onValueChange={(value) =>
                            setData('operational_status', value)
                        }
                    >
                        <SelectTrigger id="room-status">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {options.operational.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>
                <Field
                    label="Description"
                    htmlFor="room-description"
                    error={errors.description}
                    className="sm:col-span-3"
                >
                    <Textarea
                        id="room-description"
                        rows={3}
                        value={data.description}
                        onChange={(event) =>
                            setData('description', event.target.value)
                        }
                    />
                </Field>
                <div className="flex items-center gap-2 sm:col-span-3">
                    <Switch
                        id="room-accessible"
                        checked={data.is_accessible}
                        onCheckedChange={(checked) =>
                            setData('is_accessible', checked)
                        }
                    />
                    <Label htmlFor="room-accessible">Accessible room</Label>
                </div>
                {data.is_accessible && (
                    <Field
                        label="Accessibility features"
                        htmlFor="room-access-notes"
                        error={errors.accessibility_notes}
                        className="sm:col-span-3"
                    >
                        <Input
                            id="room-access-notes"
                            value={data.accessibility_notes}
                            onChange={(event) =>
                                setData(
                                    'accessibility_notes',
                                    event.target.value,
                                )
                            }
                            placeholder="Roll-in shower, grab bars, lowered fixtures"
                        />
                    </Field>
                )}
                <Field
                    label="Internal notes"
                    htmlFor="room-notes"
                    error={errors.notes}
                    className="sm:col-span-3"
                >
                    <Textarea
                        id="room-notes"
                        rows={2}
                        value={data.notes}
                        onChange={(event) =>
                            setData('notes', event.target.value)
                        }
                    />
                </Field>
            </div>
            <ImagePicker
                label="Room photos"
                multiple
                files={data.images}
                onFilesChange={(files) => setData('images', files)}
                existing={room?.images ?? []}
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
                error={fieldError(errors, 'images')}
            />
        </FormDialog>
    );
}

function BlockDialog({
    room,
    onClose,
    blockTypes,
}: {
    room: Room | null;
    onClose: () => void;
    blockTypes: Option[];
}) {
    const form = useForm({
        type: 'maintenance',
        start_date: '',
        end_date: '',
        reason: '',
    });
    const { data, setData, errors } = form;

    useEffect(() => {
        if (room) {
            form.reset();
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [room?.id]);

    function submit() {
        if (!room) {
            return;
        }

        form.post(admin.rooms.blocks.store.url(room.id), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
            onError: notifyValidationFailed,
        });
    }

    return (
        <FormDialog
            open={room !== null}
            onOpenChange={(open) => !open && onClose()}
            title={`Block room ${room?.number ?? ''}`}
            description="The room cannot be booked from the start date until the end date (the end date itself stays bookable)."
            submitLabel="Block room"
            processing={form.processing}
            onSubmit={submit}
        >
            <Field
                label="Reason for block"
                htmlFor="block-type"
                error={errors.type}
                required
            >
                <Select
                    value={data.type}
                    onValueChange={(value) => setData('type', value)}
                >
                    <SelectTrigger id="block-type">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {blockTypes.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </Field>
            <div className="grid gap-4 sm:grid-cols-2">
                <Field
                    label="From"
                    htmlFor="block-start"
                    error={errors.start_date}
                    required
                >
                    <Input
                        id="block-start"
                        type="date"
                        value={data.start_date}
                        onChange={(event) =>
                            setData('start_date', event.target.value)
                        }
                        aria-invalid={Boolean(errors.start_date)}
                    />
                </Field>
                <Field
                    label="Until"
                    htmlFor="block-end"
                    error={errors.end_date}
                    required
                >
                    <Input
                        id="block-end"
                        type="date"
                        min={data.start_date || undefined}
                        value={data.end_date}
                        onChange={(event) =>
                            setData('end_date', event.target.value)
                        }
                        aria-invalid={Boolean(errors.end_date)}
                    />
                </Field>
            </div>
            <Field
                label="Details"
                htmlFor="block-reason"
                error={errors.reason}
                required
            >
                <Textarea
                    id="block-reason"
                    rows={3}
                    value={data.reason}
                    onChange={(event) => setData('reason', event.target.value)}
                    placeholder="Replace air-conditioning compressor"
                />
            </Field>
        </FormDialog>
    );
}

RoomsIndex.layout = {
    breadcrumbs: [{ title: 'Rooms', href: admin.rooms.index() }],
};
