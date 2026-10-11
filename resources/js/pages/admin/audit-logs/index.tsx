import { Head } from '@inertiajs/react';
import { History } from 'lucide-react';
import { Fragment, useState } from 'react';
import { EmptyState } from '@/components/admin/empty-state';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useFilters } from '@/hooks/use-filters';
import { formatDateTime } from '@/lib/format';
import admin from '@/routes/admin';
import type { Paginated } from '@/types';

type AuditEntry = {
    id: number;
    action: string;
    actor: { id: number; name: string; email: string } | null;
    subject: string | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string | null;
    created_at: string;
};

type Filters = { action: string; user: string; from: string; to: string };

function actionVariant(action: string) {
    if (action.startsWith('super_admin.protection')) {
        return 'danger' as const;
    }

    if (action.endsWith('deleted') || action.endsWith('deactivated')) {
        return 'warning' as const;
    }

    return 'secondary' as const;
}

export default function AuditLogsIndex({
    logs,
    filters: initialFilters,
}: {
    logs: Paginated<AuditEntry>;
    filters: Filters;
}) {
    const { filters, setFilter, reset } = useFilters<Filters>(
        admin.auditLogs.index.url(),
        initialFilters,
    );
    const [expanded, setExpanded] = useState<number | null>(null);
    const hasFilters = Object.values(filters).some((value) => value !== '');

    return (
        <>
            <Head title="Audit log" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Audit log"
                    description="A tamper-evident trail of sensitive actions. Passwords, tokens and payment data are never recorded."
                />

                <div className="rounded-xl border bg-card shadow-xs">
                    <div className="grid gap-3 border-b p-4 sm:grid-cols-2 lg:grid-cols-5">
                        <div className="grid gap-1.5">
                            <Label htmlFor="filter-action">Action</Label>
                            <Input
                                id="filter-action"
                                placeholder="e.g. user. or booking."
                                value={filters.action}
                                onChange={(event) =>
                                    setFilter('action', event.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="filter-user">Actor</Label>
                            <Input
                                id="filter-user"
                                placeholder="Name or email"
                                value={filters.user}
                                onChange={(event) =>
                                    setFilter('user', event.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="filter-from">From</Label>
                            <Input
                                id="filter-from"
                                type="date"
                                value={filters.from}
                                onChange={(event) =>
                                    setFilter('from', event.target.value, true)
                                }
                            />
                        </div>
                        <div className="grid gap-1.5">
                            <Label htmlFor="filter-to">To</Label>
                            <Input
                                id="filter-to"
                                type="date"
                                value={filters.to}
                                onChange={(event) =>
                                    setFilter('to', event.target.value, true)
                                }
                            />
                        </div>
                        <div className="flex items-end">
                            {hasFilters && (
                                <Button variant="ghost" onClick={reset}>
                                    Clear filters
                                </Button>
                            )}
                        </div>
                    </div>

                    {logs.data.length === 0 ? (
                        <EmptyState
                            icon={History}
                            title="No audit entries"
                            description="Matching activity will appear here."
                        />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>When</TableHead>
                                    <TableHead>Action</TableHead>
                                    <TableHead>Actor</TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Subject
                                    </TableHead>
                                    <TableHead className="hidden lg:table-cell">
                                        IP
                                    </TableHead>
                                    <TableHead>
                                        <span className="sr-only">Details</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {logs.data.map((log) => (
                                    <Fragment key={log.id}>
                                        <TableRow>
                                            <TableCell className="text-muted-foreground">
                                                {formatDateTime(log.created_at)}
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={actionVariant(
                                                        log.action,
                                                    )}
                                                    className="font-mono"
                                                >
                                                    {log.action}
                                                </Badge>
                                            </TableCell>
                                            <TableCell>
                                                {log.actor?.name ?? 'System'}
                                            </TableCell>
                                            <TableCell className="hidden md:table-cell">
                                                {log.subject ?? '—'}
                                            </TableCell>
                                            <TableCell className="hidden font-mono text-xs lg:table-cell">
                                                {log.ip_address ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {(log.old_values ||
                                                    log.new_values) && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        aria-expanded={
                                                            expanded === log.id
                                                        }
                                                        onClick={() =>
                                                            setExpanded(
                                                                expanded ===
                                                                    log.id
                                                                    ? null
                                                                    : log.id,
                                                            )
                                                        }
                                                    >
                                                        {expanded === log.id
                                                            ? 'Hide'
                                                            : 'Details'}
                                                    </Button>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                        {expanded === log.id && (
                                            <TableRow className="bg-muted/30 hover:bg-muted/30">
                                                <TableCell
                                                    colSpan={6}
                                                    className="whitespace-normal"
                                                >
                                                    <div className="grid gap-4 md:grid-cols-2">
                                                        <ValuesBlock
                                                            title="Before"
                                                            values={
                                                                log.old_values
                                                            }
                                                        />
                                                        <ValuesBlock
                                                            title="After"
                                                            values={
                                                                log.new_values
                                                            }
                                                        />
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </Fragment>
                                ))}
                            </TableBody>
                        </Table>
                    )}

                    <div className="border-t p-4">
                        <Pagination paginator={logs} />
                    </div>
                </div>
            </div>
        </>
    );
}

function ValuesBlock({
    title,
    values,
}: {
    title: string;
    values: Record<string, unknown> | null;
}) {
    return (
        <div>
            <p className="mb-1 text-xs font-semibold tracking-wide text-muted-foreground uppercase">
                {title}
            </p>
            <pre className="max-h-64 overflow-auto rounded-md bg-background p-3 text-xs">
                {values ? JSON.stringify(values, null, 2) : '—'}
            </pre>
        </div>
    );
}

AuditLogsIndex.layout = {
    breadcrumbs: [{ title: 'Audit log', href: admin.auditLogs.index() }],
};
