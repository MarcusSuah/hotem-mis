import { DocumentPreview } from '@/components/admin/document-preview';
import { Head, Link } from '@inertiajs/react';
import { FileText, Printer, Search } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
import { useFilters } from '@/hooks/use-filters';
import { formatDateTime } from '@/lib/format';
import admin from '@/routes/admin';
import type { MoneyValue, Paginated } from '@/types';

type InvoiceRow = {
    number: string;
    status: string;
    guest: string;
    reservation: string | null;
    folio: string;
    total: MoneyValue;
    paid: MoneyValue;
    balance: MoneyValue;
    issued_at: string;
    void_reason: string | null;
};

type Filters = {
    search: string;
    status: string;
    balance: string;
    from: string;
    to: string;
};

export default function Invoices({
    invoices,
    filters: initialFilters,
}: {
    invoices: Paginated<InvoiceRow>;
    filters: Filters;
}) {
    const { filters, setFilter, reset } = useFilters<Filters>(
        admin.invoices.index.url(),
        initialFilters,
    );
    const hasFilters = Object.values(filters).some((value) => value !== '');

    return (
        <>
            <Head title="Invoices" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Invoices"
                    description="Numbered, permanent invoice snapshots. Corrections are made by voiding and reissuing."
                />

                <div className="rounded-xl border bg-card shadow-xs">
                    <div className="grid gap-3 border-b p-4 sm:grid-cols-2 lg:grid-cols-6">
                        <div className="relative sm:col-span-2">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                aria-label="Search invoices"
                                placeholder="Invoice number, booking or guest"
                                className="pl-9"
                                value={filters.search}
                                onChange={(event) =>
                                    setFilter('search', event.target.value)
                                }
                            />
                        </div>
                        <Select
                            value={filters.status || 'all'}
                            onValueChange={(value) =>
                                setFilter(
                                    'status',
                                    value === 'all' ? '' : value,
                                    true,
                                )
                            }
                        >
                            <SelectTrigger aria-label="Status">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Any status</SelectItem>
                                <SelectItem value="issued">Issued</SelectItem>
                                <SelectItem value="void">Void</SelectItem>
                            </SelectContent>
                        </Select>
                        <Select
                            value={filters.balance || 'all'}
                            onValueChange={(value) =>
                                setFilter(
                                    'balance',
                                    value === 'all' ? '' : value,
                                    true,
                                )
                            }
                        >
                            <SelectTrigger aria-label="Balance">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Any balance</SelectItem>
                                <SelectItem value="outstanding">
                                    Outstanding
                                </SelectItem>
                                <SelectItem value="settled">Settled</SelectItem>
                            </SelectContent>
                        </Select>
                        <Input
                            type="date"
                            aria-label="From"
                            value={filters.from}
                            onChange={(event) =>
                                setFilter('from', event.target.value, true)
                            }
                        />
                        <Input
                            type="date"
                            aria-label="To"
                            value={filters.to}
                            onChange={(event) =>
                                setFilter('to', event.target.value, true)
                            }
                        />
                    </div>
                    {hasFilters && (
                        <div className="flex justify-end border-b px-4 py-2">
                            <Button variant="ghost" size="sm" onClick={reset}>
                                Clear filters
                            </Button>
                        </div>
                    )}
                    {invoices.data.length === 0 ? (
                        <EmptyState icon={FileText} title="No invoices found" />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Invoice</TableHead>
                                    <TableHead>Guest</TableHead>
                                    <TableHead className="text-right">
                                        Total
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Balance
                                    </TableHead>
                                    <TableHead className="w-10" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {invoices.data.map((invoice) => (
                                    <TableRow key={invoice.number}>
                                        <TableCell>
                                            <span className="font-mono font-medium">
                                                {invoice.number}
                                            </span>{' '}
                                            {invoice.status === 'void' && (
                                                <Badge variant="danger">
                                                    Void
                                                </Badge>
                                            )}
                                            <span className="block text-xs text-muted-foreground">
                                                {formatDateTime(
                                                    invoice.issued_at,
                                                )}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            {invoice.guest}
                                            <Link
                                                href={admin.folios.show(
                                                    invoice.folio,
                                                )}
                                                className="block font-mono text-xs text-muted-foreground hover:underline"
                                            >
                                                {invoice.reservation ??
                                                    invoice.folio}
                                            </Link>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {invoice.total.formatted}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {invoice.balance.minor > 0 &&
                                            invoice.status === 'issued' ? (
                                                <span className="font-medium text-destructive">
                                                    {invoice.balance.formatted}
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    {invoice.balance.formatted}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <DocumentPreview
                                                url={admin.invoices.show.url(
                                                    invoice.number,
                                                )}
                                                title={`Invoice ${invoice.number}`}
                                                filename={`${invoice.number}.pdf`}
                                                trigger={(open) => (
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        onClick={open}
                                                        aria-label={`Preview invoice ${invoice.number}`}
                                                    >
                                                        <Printer />
                                                    </Button>
                                                )}
                                            />
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                    <div className="border-t p-4">
                        <Pagination paginator={invoices} />
                    </div>
                </div>
            </div>
        </>
    );
}

Invoices.layout = {
    breadcrumbs: [{ title: 'Invoices', href: admin.invoices.index() }],
};
