import { Head, router } from '@inertiajs/react';
import { Download, FileSpreadsheet } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { PageHeader } from '@/components/admin/page-header';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';
import type { MoneyValue } from '@/types';

type Column = {
    key: string;
    label: string;
    type: 'text' | 'number' | 'money' | 'percent' | 'date';
};

type Report = {
    key: string;
    title: string;
    description: string;
    periodic: boolean;
    columns: Column[];
    rows: Record<string, unknown>[];
    totals: Record<string, unknown> | null;
};

type Props = {
    reports: {
        key: string;
        title: string;
        description: string;
        periodic: boolean;
    }[];
    report: Report;
    filters: { report: string; from: string; to: string };
    can: { export: boolean };
};

function isMoney(value: unknown): value is MoneyValue {
    return typeof value === 'object' && value !== null && 'formatted' in value;
}

function renderCell(column: Column, value: unknown): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (isMoney(value)) {
        return value.formatted;
    }

    switch (column.type) {
        case 'percent':
            return `${formatNumber(Number(value), 1)}%`;
        case 'number':
            return formatNumber(
                Number(value),
                Number.isInteger(Number(value)) ? 0 : 2,
            );
        case 'date':
            return typeof value === 'string' &&
                /^\d{4}-\d{2}-\d{2}$/.test(value)
                ? formatDate(value)
                : String(value as string);
        default:
            return String(value as string | number);
    }
}

export default function Reports({ reports, report, filters, can }: Props) {
    const [period, setPeriod] = useState({
        from: filters.from,
        to: filters.to,
    });
    const numeric = (column: Column) =>
        column.type !== 'text' && column.type !== 'date';

    function visit(query: Record<string, string>) {
        router.get(admin.reports.index.url(), query, { preserveScroll: true });
    }

    function apply(event: FormEvent) {
        event.preventDefault();
        visit({ report: filters.report, ...period });
    }

    const exportUrl = (format: 'csv' | 'xlsx') =>
        admin.reports.export.url({
            query: {
                report: filters.report,
                ...(report.periodic ? period : {}),
                format,
            },
        });

    return (
        <>
            <Head title="Reports" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Reports"
                    description="Performance, revenue and operational reports. Money is shown in the base currency unless a column says otherwise."
                />
                <div className="grid gap-6 lg:grid-cols-[16rem_1fr]">
                    <nav
                        aria-label="Reports"
                        className="h-fit rounded-xl border bg-card p-2 shadow-xs"
                    >
                        <ul className="space-y-0.5">
                            {reports.map((item) => (
                                <li key={item.key}>
                                    <button
                                        type="button"
                                        onClick={() =>
                                            visit({ report: item.key })
                                        }
                                        aria-current={
                                            item.key === report.key
                                                ? 'page'
                                                : undefined
                                        }
                                        className={cn(
                                            'w-full rounded-md px-3 py-2 text-left text-sm hover:bg-muted',
                                            item.key === report.key &&
                                                'bg-primary text-primary-foreground hover:bg-primary',
                                        )}
                                    >
                                        {item.title}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </nav>

                    <section className="min-w-0 space-y-4">
                        <div className="flex flex-wrap items-end justify-between gap-4 rounded-xl border bg-card p-4 shadow-xs">
                            <div>
                                <h2 className="font-display text-2xl font-semibold">
                                    {report.title}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {report.description}
                                </p>
                            </div>
                            <div className="flex flex-wrap items-end gap-3">
                                {report.periodic && (
                                    <form
                                        onSubmit={apply}
                                        className="flex flex-wrap items-end gap-2"
                                    >
                                        <div className="space-y-1">
                                            <Label htmlFor="r-from">From</Label>
                                            <Input
                                                id="r-from"
                                                type="date"
                                                value={period.from}
                                                onChange={(event) =>
                                                    setPeriod((current) => ({
                                                        ...current,
                                                        from: event.target
                                                            .value,
                                                    }))
                                                }
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label htmlFor="r-to">To</Label>
                                            <Input
                                                id="r-to"
                                                type="date"
                                                min={period.from}
                                                value={period.to}
                                                onChange={(event) =>
                                                    setPeriod((current) => ({
                                                        ...current,
                                                        to: event.target.value,
                                                    }))
                                                }
                                            />
                                        </div>
                                        <Button type="submit" variant="outline">
                                            Apply
                                        </Button>
                                    </form>
                                )}
                                {can.export && (
                                    <div className="flex gap-2">
                                        <Button asChild variant="outline">
                                            <a href={exportUrl('csv')}>
                                                <Download />
                                                CSV
                                            </a>
                                        </Button>
                                        <Button asChild variant="outline">
                                            <a href={exportUrl('xlsx')}>
                                                <FileSpreadsheet />
                                                Excel
                                            </a>
                                        </Button>
                                    </div>
                                )}
                            </div>
                        </div>

                        <div className="overflow-hidden rounded-xl border bg-card shadow-xs">
                            {report.rows.length === 0 ? (
                                <p className="p-10 text-center text-sm text-muted-foreground">
                                    No data for this period.
                                </p>
                            ) : (
                                <div className="max-h-[65vh] overflow-auto">
                                    <Table>
                                        <TableHeader className="sticky top-0 bg-card">
                                            <TableRow>
                                                {report.columns.map(
                                                    (column) => (
                                                        <TableHead
                                                            key={column.key}
                                                            className={
                                                                numeric(column)
                                                                    ? 'text-right'
                                                                    : undefined
                                                            }
                                                        >
                                                            {column.label}
                                                        </TableHead>
                                                    ),
                                                )}
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {report.rows.map((row, index) => (
                                                <TableRow key={index}>
                                                    {report.columns.map(
                                                        (column) => (
                                                            <TableCell
                                                                key={column.key}
                                                                className={
                                                                    numeric(
                                                                        column,
                                                                    )
                                                                        ? 'text-right tabular-nums'
                                                                        : undefined
                                                                }
                                                            >
                                                                {renderCell(
                                                                    column,
                                                                    row[
                                                                        column
                                                                            .key
                                                                    ],
                                                                )}
                                                            </TableCell>
                                                        ),
                                                    )}
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                        {report.totals && (
                                            <TableFooter className="sticky bottom-0">
                                                <TableRow>
                                                    {report.columns.map(
                                                        (column) => (
                                                            <TableCell
                                                                key={column.key}
                                                                className={cn(
                                                                    'font-semibold',
                                                                    numeric(
                                                                        column,
                                                                    ) &&
                                                                        'text-right tabular-nums',
                                                                )}
                                                            >
                                                                {report.totals &&
                                                                column.key in
                                                                    report.totals
                                                                    ? renderCell(
                                                                          {
                                                                              ...column,
                                                                              type:
                                                                                  column.type ===
                                                                                  'date'
                                                                                      ? 'text'
                                                                                      : column.type,
                                                                          },
                                                                          report
                                                                              .totals[
                                                                              column
                                                                                  .key
                                                                          ],
                                                                      )
                                                                    : ''}
                                                            </TableCell>
                                                        ),
                                                    )}
                                                </TableRow>
                                            </TableFooter>
                                        )}
                                    </Table>
                                </div>
                            )}
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}

Reports.layout = {
    breadcrumbs: [{ title: 'Reports', href: admin.reports.index() }],
};
