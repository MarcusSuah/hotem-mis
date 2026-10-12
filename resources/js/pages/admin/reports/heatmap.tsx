import { Head, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';

type Cell = 'occupied' | 'reserved' | 'pending' | 'blocked' | 'free';

type Props = {
    month: string;
    heatmap: {
        dates: string[];
        rooms: {
            id: number;
            number: string;
            room_type: string;
            cells: Cell[];
            utilisation: number;
        }[];
        summary: Record<Cell, number>;
    };
};

const legend: { key: Cell; label: string; className: string }[] = [
    {
        key: 'occupied',
        label: 'Occupied',
        className: 'bg-teal dark:bg-[var(--chart-1)]',
    },
    {
        key: 'reserved',
        label: 'Reserved',
        className: 'bg-teal/45 dark:bg-[var(--chart-1)]/50',
    },
    { key: 'pending', label: 'Pending request', className: 'bg-gold' },
    { key: 'blocked', label: 'Out of service', className: 'bg-destructive/70' },
    { key: 'free', label: 'Free', className: 'bg-muted' },
];

const cellClass = Object.fromEntries(
    legend.map((item) => [item.key, item.className]),
) as Record<Cell, string>;
const cellLabel = Object.fromEntries(
    legend.map((item) => [item.key, item.label]),
) as Record<Cell, string>;

function shiftMonth(month: string, delta: number): string {
    const [year, value] = month.split('-').map(Number);
    const date = new Date(year, value - 1 + delta, 1);

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

function utilisationTone(value: number): string {
    if (value >= 80) {
        return 'text-emerald-700 dark:text-emerald-400';
    }

    return value < 40 ? 'text-destructive' : '';
}

export default function RoomUsageHeatmap({ month, heatmap }: Props) {
    const label = new Date(`${month}-01T00:00:00`).toLocaleDateString(
        undefined,
        { month: 'long', year: 'numeric' },
    );
    const total = Object.values(heatmap.summary).reduce(
        (sum, value) => sum + value,
        0,
    );
    const today = new Date().toISOString().slice(0, 10);
    const go = (target: string) =>
        router.get(
            admin.reports.heatmap.url(),
            { month: target },
            { preserveScroll: true },
        );

    return (
        <>
            <Head title="Room usage" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Room usage heatmap"
                    description="How each room is used, night by night. Spot rooms that sit empty or are often out of service."
                />

                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-2">
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={() => go(shiftMonth(month, -1))}
                            aria-label="Previous month"
                        >
                            <ChevronLeft />
                        </Button>
                        <p className="w-40 text-center font-display text-xl font-semibold">
                            {label}
                        </p>
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={() => go(shiftMonth(month, 1))}
                            aria-label="Next month"
                        >
                            <ChevronRight />
                        </Button>
                    </div>
                    <ul
                        className="flex flex-wrap gap-3 text-xs"
                        aria-label="Legend"
                    >
                        {legend.map((item) => (
                            <li
                                key={item.key}
                                className="flex items-center gap-1.5"
                            >
                                <span
                                    className={cn(
                                        'size-3 rounded-sm',
                                        item.className,
                                    )}
                                />
                                {item.label} (
                                {total > 0
                                    ? formatNumber(
                                          (heatmap.summary[item.key] / total) *
                                              100,
                                          0,
                                      )
                                    : 0}
                                %)
                            </li>
                        ))}
                    </ul>
                </div>

                <div className="overflow-x-auto rounded-xl border bg-card p-4 shadow-xs">
                    {heatmap.rooms.length === 0 ? (
                        <p className="p-6 text-center text-sm text-muted-foreground">
                            No rooms have been set up yet.
                        </p>
                    ) : (
                        <table className="border-separate border-spacing-0.5 text-xs">
                            <thead>
                                <tr>
                                    <th className="sticky left-0 z-10 bg-card pr-3 text-left font-medium">
                                        Room
                                    </th>
                                    {heatmap.dates.map((date) => (
                                        <th
                                            key={date}
                                            scope="col"
                                            className={cn(
                                                'w-6 text-center font-normal text-muted-foreground',
                                                date === today &&
                                                    'font-semibold text-foreground',
                                            )}
                                        >
                                            {Number(date.slice(8))}
                                        </th>
                                    ))}
                                    <th className="pl-3 text-right font-medium">
                                        Used
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {heatmap.rooms.map((room) => (
                                    <tr key={room.id}>
                                        <th
                                            scope="row"
                                            className="sticky left-0 z-10 bg-card pr-3 text-left font-normal whitespace-nowrap"
                                        >
                                            <span className="font-medium">
                                                {room.number}
                                            </span>{' '}
                                            <span className="text-muted-foreground">
                                                {room.room_type}
                                            </span>
                                        </th>
                                        {room.cells.map((cell, index) => (
                                            <td
                                                key={heatmap.dates[index]}
                                                title={`Room ${room.number}, ${heatmap.dates[index]}: ${cellLabel[cell]}`}
                                                className={cn(
                                                    'h-6 w-6 rounded-sm',
                                                    cellClass[cell],
                                                )}
                                            >
                                                <span className="sr-only">
                                                    {cellLabel[cell]}
                                                </span>
                                            </td>
                                        ))}
                                        <td
                                            className={cn(
                                                'pl-3 text-right font-medium tabular-nums',
                                                utilisationTone(
                                                    room.utilisation,
                                                ),
                                            )}
                                        >
                                            {formatNumber(room.utilisation, 0)}%
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
            </div>
        </>
    );
}

RoomUsageHeatmap.layout = {
    breadcrumbs: [{ title: 'Room usage', href: admin.reports.heatmap() }],
};
