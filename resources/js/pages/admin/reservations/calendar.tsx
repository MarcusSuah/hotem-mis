import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import { PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';

type Segment = {
    room_id: number;
    reference: string;
    status: string;
    guest: string;
    party: number;
    start: string;
    end: string;
    arrival: string;
    departure: string;
};

type Block = {
    room_id: number;
    type: string;
    label: string;
    reason: string;
    start: string;
    end: string;
};

type Props = {
    start: string;
    days: number;
    dates: string[];
    rooms: {
        id: number;
        number: string;
        floor: number;
        room_type: string;
        out_of_service: boolean;
    }[];
    segments: Segment[];
    blocks: Block[];
    roomTypes: { id: number; name: string }[];
    filters: { room_type: string };
    can: { create: boolean };
};

const segmentColors: Record<string, string> = {
    pending: 'bg-warning/85 text-white',
    confirmed: 'bg-highlight text-highlight-foreground',
    checked_in: 'bg-primary text-primary-foreground',
};

const DAY = 86_400_000;

function dayIndex(start: string, date: string): number {
    return Math.round(
        (new Date(`${date}T00:00:00`).getTime() -
            new Date(`${start}T00:00:00`).getTime()) /
            DAY,
    );
}

function shift(date: string, days: number): string {
    const next = new Date(`${date}T00:00:00`);
    next.setDate(next.getDate() + days);

    return [
        next.getFullYear(),
        String(next.getMonth() + 1).padStart(2, '0'),
        String(next.getDate()).padStart(2, '0'),
    ].join('-');
}

export default function AvailabilityCalendar({
    start,
    days,
    dates,
    rooms,
    segments,
    blocks,
    roomTypes,
    filters,
    can,
}: Props) {
    const now = new Date();
    const today = [
        now.getFullYear(),
        String(now.getMonth() + 1).padStart(2, '0'),
        String(now.getDate()).padStart(2, '0'),
    ].join('-');

    function visit(params: Record<string, string | number>) {
        router.get(
            admin.availability.index.url(),
            { start, days, room_type: filters.room_type, ...params },
            { preserveScroll: true, preserveState: true, replace: true },
        );
    }

    const occupancy = dates.map((date) => {
        const held = segments.filter(
            (segment) => segment.start <= date && date < segment.end,
        ).length;
        const sellable = rooms.filter((room) => !room.out_of_service).length;

        return {
            date,
            held,
            free: Math.max(
                0,
                sellable -
                    held -
                    blocks.filter(
                        (block) => block.start <= date && date < block.end,
                    ).length,
            ),
        };
    });

    return (
        <>
            <Head title="Availability" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Availability calendar"
                    description="Held room-nights by room. Each bar runs from arrival night to the night before departure."
                    actions={
                        can.create && (
                            <Button asChild>
                                <Link href={admin.reservations.create()}>
                                    <Plus />
                                    New reservation
                                </Link>
                            </Button>
                        )
                    }
                />

                <div className="flex flex-wrap items-center gap-2">
                    <Button
                        variant="outline"
                        size="icon"
                        aria-label="Previous period"
                        onClick={() => visit({ start: shift(start, -days) })}
                    >
                        <ChevronLeft />
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() => visit({ start: today })}
                    >
                        Today
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        aria-label="Next period"
                        onClick={() => visit({ start: shift(start, days) })}
                    >
                        <ChevronRight />
                    </Button>
                    <input
                        type="date"
                        aria-label="Start date"
                        className="h-9 rounded-md border bg-transparent px-3 text-sm"
                        value={start}
                        onChange={(event) =>
                            event.target.value &&
                            visit({ start: event.target.value })
                        }
                    />
                    <Select
                        value={String(days)}
                        onValueChange={(value) =>
                            visit({ days: Number(value) })
                        }
                    >
                        <SelectTrigger
                            className="w-32"
                            aria-label="Period length"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {[7, 14, 21, 31].map((length) => (
                                <SelectItem key={length} value={String(length)}>
                                    {length} days
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Select
                        value={filters.room_type || 'all'}
                        onValueChange={(value) =>
                            visit({ room_type: value === 'all' ? '' : value })
                        }
                    >
                        <SelectTrigger className="w-52" aria-label="Room type">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All room types</SelectItem>
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
                    <div className="ml-auto flex flex-wrap gap-3 text-xs text-muted-foreground">
                        <Legend className="bg-warning/85" label="Pending" />
                        <Legend className="bg-highlight" label="Confirmed" />
                        <Legend className="bg-primary" label="In house" />
                        <Legend
                            className="bg-[repeating-linear-gradient(45deg,var(--muted-foreground)_0_2px,transparent_2px_6px)] opacity-60"
                            label="Blocked"
                        />
                    </div>
                </div>

                <div className="overflow-x-auto rounded-xl border bg-card shadow-xs">
                    <div
                        className="grid min-w-max"
                        style={{
                            gridTemplateColumns: `9rem repeat(${days}, minmax(3rem, 1fr))`,
                        }}
                        role="grid"
                        aria-label="Room availability by night"
                    >
                        <div className="sticky left-0 z-20 border-r border-b bg-card p-2 text-xs font-semibold text-muted-foreground uppercase">
                            Room
                        </div>
                        {occupancy.map(({ date, free }) => {
                            const day = new Date(`${date}T00:00:00`);
                            const weekend =
                                day.getDay() === 5 || day.getDay() === 6;

                            return (
                                <div
                                    key={date}
                                    className={cn(
                                        'border-b border-l p-1.5 text-center text-xs',
                                        weekend &&
                                            'bg-sage/50 dark:bg-muted/40',
                                        date === today && 'bg-highlight/20',
                                    )}
                                >
                                    <div className="font-medium">
                                        {day.toLocaleDateString('en-GB', {
                                            weekday: 'short',
                                        })}
                                    </div>
                                    <div className="text-muted-foreground">
                                        {day.getDate()}/{day.getMonth() + 1}
                                    </div>
                                    <div
                                        className={cn(
                                            'mt-0.5 text-[10px] font-semibold',
                                            free === 0
                                                ? 'text-destructive'
                                                : 'text-success',
                                        )}
                                    >
                                        {free} free
                                    </div>
                                </div>
                            );
                        })}

                        {rooms.map((room) => {
                            const roomSegments = segments.filter(
                                (segment) => segment.room_id === room.id,
                            );
                            const roomBlocks = blocks.filter(
                                (block) => block.room_id === room.id,
                            );

                            return (
                                <div
                                    key={room.id}
                                    className="contents"
                                    role="row"
                                >
                                    <div
                                        className="sticky left-0 z-10 border-r border-b bg-card px-2 py-1.5"
                                        role="rowheader"
                                    >
                                        <p className="text-sm font-semibold">
                                            {room.number}
                                        </p>
                                        <p className="truncate text-[11px] text-muted-foreground">
                                            {room.room_type}
                                        </p>
                                    </div>
                                    <div
                                        className="relative border-b"
                                        style={{ gridColumn: `span ${days}` }}
                                    >
                                        <div
                                            className="absolute inset-0 grid"
                                            style={{
                                                gridTemplateColumns: `repeat(${days}, minmax(3rem, 1fr))`,
                                            }}
                                        >
                                            {dates.map((date) => (
                                                <div
                                                    key={date}
                                                    className={cn(
                                                        'border-l',
                                                        date === today &&
                                                            'bg-highlight/10',
                                                        room.out_of_service &&
                                                            'bg-destructive/5',
                                                    )}
                                                />
                                            ))}
                                        </div>
                                        <div
                                            className="relative grid h-11 items-center"
                                            style={{
                                                gridTemplateColumns: `repeat(${days}, minmax(3rem, 1fr))`,
                                            }}
                                        >
                                            {roomBlocks.map((block) => {
                                                const from = Math.max(
                                                    0,
                                                    dayIndex(
                                                        start,
                                                        block.start,
                                                    ),
                                                );
                                                const to = Math.min(
                                                    days,
                                                    dayIndex(start, block.end),
                                                );

                                                return (
                                                    <Tooltip
                                                        key={`${block.start}-${block.type}`}
                                                    >
                                                        <TooltipTrigger asChild>
                                                            <div
                                                                className="mx-0.5 h-7 rounded bg-[repeating-linear-gradient(45deg,var(--muted-foreground)_0_2px,transparent_2px_6px)] opacity-60"
                                                                style={{
                                                                    gridColumn: `${from + 1} / ${to + 1}`,
                                                                    gridRow: 1,
                                                                }}
                                                                aria-label={`${block.label}: ${block.reason}`}
                                                            />
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            {block.label}:{' '}
                                                            {block.reason}
                                                        </TooltipContent>
                                                    </Tooltip>
                                                );
                                            })}
                                            {roomSegments.map((segment) => {
                                                const from = Math.max(
                                                    0,
                                                    dayIndex(
                                                        start,
                                                        segment.start,
                                                    ),
                                                );
                                                const to = Math.min(
                                                    days,
                                                    dayIndex(
                                                        start,
                                                        segment.end,
                                                    ),
                                                );

                                                return (
                                                    <Tooltip
                                                        key={segment.reference}
                                                    >
                                                        <TooltipTrigger asChild>
                                                            <Link
                                                                href={admin.reservations.show(
                                                                    segment.reference,
                                                                )}
                                                                className={cn(
                                                                    'mx-0.5 flex h-7 items-center truncate rounded px-2 text-xs font-medium shadow-xs hover:brightness-95',
                                                                    segmentColors[
                                                                        segment
                                                                            .status
                                                                    ] ??
                                                                        'bg-muted',
                                                                )}
                                                                style={{
                                                                    gridColumn: `${from + 1} / ${to + 1}`,
                                                                    gridRow: 1,
                                                                }}
                                                            >
                                                                {segment.guest}
                                                            </Link>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            {segment.reference}{' '}
                                                            · {segment.guest} ·{' '}
                                                            {segment.party}{' '}
                                                            guest
                                                            {segment.party === 1
                                                                ? ''
                                                                : 's'}
                                                            <br />
                                                            {formatDate(
                                                                segment.arrival,
                                                            )}{' '}
                                                            →{' '}
                                                            {formatDate(
                                                                segment.departure,
                                                            )}
                                                        </TooltipContent>
                                                    </Tooltip>
                                                );
                                            })}
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                    {rooms.length === 0 && (
                        <p className="p-6 text-center text-sm text-muted-foreground">
                            No rooms to show.
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}

function Legend({ className, label }: { className: string; label: string }) {
    return (
        <span className="flex items-center gap-1.5">
            <span className={cn('inline-block size-3 rounded-sm', className)} />
            {label}
        </span>
    );
}

AvailabilityCalendar.layout = {
    breadcrumbs: [{ title: 'Availability', href: admin.availability.index() }],
};
