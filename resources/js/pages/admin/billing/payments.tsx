import { Head, Link } from '@inertiajs/react';
import { Banknote, Receipt, Search } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatCard } from '@/components/admin/stat-card';
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

type PaymentRow = {
    reference: string;
    folio: string;
    reservation: string | null;
    guest: string;
    method_label: string;
    status: string;
    amount: MoneyValue;
    external_reference: string | null;
    received_by: string | null;
    received_at: string;
};

type Filters = {
    search: string;
    method: string;
    currency: string;
    from: string;
    to: string;
};

export default function Payments({
    payments,
    totals,
    filters: initialFilters,
    methods,
    currencies,
}: {
    payments: Paginated<PaymentRow>;
    totals: MoneyValue[];
    filters: Filters;
    methods: { value: string; label: string }[];
    currencies: string[];
}) {
    const { filters, setFilter, reset } = useFilters<Filters>(
        admin.payments.index.url(),
        initialFilters,
    );
    const hasFilters = Object.values(filters).some((value) => value !== '');

    return (
        <>
            <Head title="Payments" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Payments"
                    description="Money received from guests. Totals are shown per currency and never added across currencies."
                />

                <div className="grid gap-4 sm:grid-cols-2">
                    {totals.length === 0 ? (
                        <StatCard
                            label="Received (filtered)"
                            value="—"
                            icon={Banknote}
                        />
                    ) : (
                        totals.map((total) => (
                            <StatCard
                                key={total.currency}
                                label={`Received in ${total.currency}`}
                                value={total.formatted}
                                icon={Banknote}
                            />
                        ))
                    )}
                </div>

                <div className="rounded-xl border bg-card shadow-xs">
                    <div className="grid gap-3 border-b p-4 sm:grid-cols-2 lg:grid-cols-6">
                        <div className="relative sm:col-span-2">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                aria-label="Search payments"
                                placeholder="Receipt, transaction ref, booking or guest"
                                className="pl-9"
                                value={filters.search}
                                onChange={(event) =>
                                    setFilter('search', event.target.value)
                                }
                            />
                        </div>
                        <Select
                            value={filters.method || 'all'}
                            onValueChange={(value) =>
                                setFilter(
                                    'method',
                                    value === 'all' ? '' : value,
                                    true,
                                )
                            }
                        >
                            <SelectTrigger aria-label="Method">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Any method</SelectItem>
                                {methods.map((method) => (
                                    <SelectItem
                                        key={method.value}
                                        value={method.value}
                                    >
                                        {method.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Select
                            value={filters.currency || 'all'}
                            onValueChange={(value) =>
                                setFilter(
                                    'currency',
                                    value === 'all' ? '' : value,
                                    true,
                                )
                            }
                        >
                            <SelectTrigger aria-label="Currency">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    Any currency
                                </SelectItem>
                                {currencies.map((currency) => (
                                    <SelectItem key={currency} value={currency}>
                                        {currency}
                                    </SelectItem>
                                ))}
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
                    {payments.data.length === 0 ? (
                        <EmptyState icon={Banknote} title="No payments found" />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Received</TableHead>
                                    <TableHead>Guest</TableHead>
                                    <TableHead>Method</TableHead>
                                    <TableHead className="hidden lg:table-cell">
                                        Reference
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Amount
                                    </TableHead>
                                    <TableHead className="w-10" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {payments.data.map((payment) => (
                                    <TableRow key={payment.reference}>
                                        <TableCell>
                                            {formatDateTime(
                                                payment.received_at,
                                            )}
                                            <span className="block text-xs text-muted-foreground">
                                                {payment.received_by ??
                                                    'System'}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            {payment.guest}
                                            <Link
                                                href={admin.folios.show(
                                                    payment.folio,
                                                )}
                                                className="block font-mono text-xs text-muted-foreground hover:underline"
                                            >
                                                {payment.reservation ??
                                                    payment.folio}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            {payment.method_label}
                                        </TableCell>
                                        <TableCell className="hidden font-mono text-xs lg:table-cell">
                                            {payment.reference}
                                            {payment.external_reference && (
                                                <span className="block text-muted-foreground">
                                                    {payment.external_reference}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right font-medium">
                                            {payment.amount.formatted}
                                        </TableCell>
                                        <TableCell>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                asChild
                                                aria-label={`Receipt ${payment.reference}`}
                                            >
                                                <a
                                                    href={admin.payments.receipt.url(
                                                        payment.reference,
                                                    )}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    <Receipt />
                                                </a>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    )}
                    <div className="border-t p-4">
                        <Pagination paginator={payments} />
                    </div>
                </div>
            </div>
        </>
    );
}

Payments.layout = {
    breadcrumbs: [{ title: 'Payments', href: admin.payments.index() }],
};
