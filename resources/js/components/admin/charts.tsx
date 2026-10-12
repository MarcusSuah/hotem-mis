import type { ReactNode } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    ComposedChart,
    Legend,
    Line,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { formatNumber } from '@/lib/format';

export const chartColors = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
];

const axis = {
    stroke: 'var(--muted-foreground)',
    fontSize: 12,
    tickLine: false,
    axisLine: false,
} as const;
const tooltipStyle = {
    backgroundColor: 'var(--popover)',
    border: '1px solid var(--border)',
    borderRadius: 8,
    color: 'var(--popover-foreground)',
    fontSize: 12,
};

export function shortDate(value: string): string {
    return new Date(`${value}T00:00:00`).toLocaleDateString(undefined, {
        day: 'numeric',
        month: 'short',
    });
}

export function ChartCard({
    title,
    description,
    children,
    className,
}: {
    title: string;
    description?: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section
            className={`rounded-xl border bg-card p-5 shadow-xs ${className ?? ''}`}
        >
            <h2 className="font-display text-xl font-semibold">{title}</h2>
            {description && (
                <p className="text-xs text-muted-foreground">{description}</p>
            )}
            <div className="mt-4">{children}</div>
        </section>
    );
}

export function ChartSkeleton({ height = 260 }: { height?: number }) {
    return (
        <div
            className="animate-pulse rounded-lg bg-muted"
            style={{ height }}
            aria-hidden
        />
    );
}

/**
 * Occupancy bars with the average daily rate as a line on a second axis.
 */
export function OccupancyChart({
    data,
    currency,
}: {
    data: { date: string; occupancy: number; adr: number }[];
    currency: string;
}) {
    return (
        <ResponsiveContainer width="100%" height={280}>
            <ComposedChart
                data={data}
                margin={{ top: 4, right: 4, left: -12, bottom: 0 }}
            >
                <CartesianGrid
                    strokeDasharray="3 3"
                    stroke="var(--border)"
                    vertical={false}
                />
                <XAxis
                    dataKey="date"
                    tickFormatter={shortDate}
                    {...axis}
                    minTickGap={16}
                />
                <YAxis
                    yAxisId="occ"
                    domain={[0, 100]}
                    tickFormatter={(value: number) => `${value}%`}
                    {...axis}
                />
                <YAxis yAxisId="adr" orientation="right" {...axis} />
                <Tooltip
                    contentStyle={tooltipStyle}
                    labelFormatter={(label) =>
                        typeof label === 'string' ? shortDate(label) : ''
                    }
                    formatter={(value, name) =>
                        name === 'Occupancy'
                            ? [`${Number(value)}%`, name]
                            : [
                                  `${currency} ${formatNumber(Number(value), 2)}`,
                                  name,
                              ]
                    }
                />
                <Legend wrapperStyle={{ fontSize: 12 }} />
                <Bar
                    yAxisId="occ"
                    dataKey="occupancy"
                    name="Occupancy"
                    fill="var(--chart-1)"
                    radius={[3, 3, 0, 0]}
                />
                <Line
                    yAxisId="adr"
                    dataKey="adr"
                    name="ADR"
                    stroke="var(--chart-2)"
                    strokeWidth={2}
                    dot={false}
                    type="monotone"
                />
            </ComposedChart>
        </ResponsiveContainer>
    );
}

export function HorizontalBars({
    data,
    currency,
}: {
    data: { label: string; value: number }[];
    currency: string;
}) {
    return (
        <ResponsiveContainer
            width="100%"
            height={Math.max(180, data.length * 34)}
        >
            <BarChart
                data={data}
                layout="vertical"
                margin={{ top: 0, right: 12, left: 8, bottom: 0 }}
            >
                <XAxis type="number" hide />
                <YAxis type="category" dataKey="label" width={120} {...axis} />
                <Tooltip
                    contentStyle={tooltipStyle}
                    cursor={{ fill: 'var(--muted)' }}
                    formatter={(value) => [
                        `${currency} ${formatNumber(Number(value), 2)}`,
                        'Amount',
                    ]}
                />
                <Bar
                    dataKey="value"
                    fill="var(--chart-2)"
                    radius={[0, 4, 4, 0]}
                />
            </BarChart>
        </ResponsiveContainer>
    );
}

export function DonutChart({
    data,
}: {
    data: { label: string; value: number }[];
}) {
    return (
        <ResponsiveContainer width="100%" height={240}>
            <PieChart>
                <Pie
                    data={data}
                    dataKey="value"
                    nameKey="label"
                    innerRadius={55}
                    outerRadius={90}
                    paddingAngle={2}
                >
                    {data.map((entry, index) => (
                        <Cell
                            key={entry.label}
                            fill={chartColors[index % chartColors.length]}
                        />
                    ))}
                </Pie>
                <Tooltip contentStyle={tooltipStyle} />
                <Legend wrapperStyle={{ fontSize: 12 }} />
            </PieChart>
        </ResponsiveContainer>
    );
}

/**
 * Upcoming nights: confirmed and pending rooms stacked against availability.
 */
export function ForecastChart({
    data,
}: {
    data: {
        date: string;
        confirmed: number;
        pending: number;
        available: number;
    }[];
}) {
    return (
        <ResponsiveContainer width="100%" height={260}>
            <ComposedChart
                data={data}
                margin={{ top: 4, right: 4, left: -12, bottom: 0 }}
            >
                <CartesianGrid
                    strokeDasharray="3 3"
                    stroke="var(--border)"
                    vertical={false}
                />
                <XAxis
                    dataKey="date"
                    tickFormatter={shortDate}
                    {...axis}
                    minTickGap={16}
                />
                <YAxis allowDecimals={false} {...axis} />
                <Tooltip
                    contentStyle={tooltipStyle}
                    labelFormatter={(label) =>
                        typeof label === 'string' ? shortDate(label) : ''
                    }
                />
                <Legend wrapperStyle={{ fontSize: 12 }} />
                <Bar
                    dataKey="confirmed"
                    name="Confirmed"
                    stackId="rooms"
                    fill="var(--chart-1)"
                />
                <Bar
                    dataKey="pending"
                    name="Pending"
                    stackId="rooms"
                    fill="var(--chart-2)"
                    radius={[3, 3, 0, 0]}
                />
                <Line
                    dataKey="available"
                    name="Available rooms"
                    stroke="var(--muted-foreground)"
                    strokeDasharray="4 4"
                    dot={false}
                    type="stepAfter"
                />
            </ComposedChart>
        </ResponsiveContainer>
    );
}
