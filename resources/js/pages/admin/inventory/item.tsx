import { Head } from '@inertiajs/react';
import { History } from 'lucide-react';
import { EmptyState } from '@/components/admin/empty-state';
import { PageHeader } from '@/components/admin/page-header';
import { Pagination } from '@/components/admin/pagination';
import { StatCard } from '@/components/admin/stat-card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDate, formatDateTime } from '@/lib/format';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';
import type { MoneyValue, Paginated } from '@/types';

type Movement = {
    id: number;
    type_label: string;
    quantity: string;
    balance_after: string;
    unit_cost: MoneyValue | null;
    reference: string | null;
    expires_on: string | null;
    notes: string | null;
    user: string | null;
    created_at: string;
};

type Props = {
    item: {
        id: number;
        sku: string;
        name: string;
        unit: string;
        quantity: string;
        reorder_level: string;
        average_cost: MoneyValue;
    };
    movements: Paginated<Movement>;
};

export default function InventoryItem({ item, movements }: Props) {
    return (
        <>
            <Head title={item.name} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={item.name}
                    description={`${item.sku} · stock movement history`}
                />
                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard
                        label="On hand"
                        value={`${Number(item.quantity)} ${item.unit}`}
                        tone="gold"
                    />
                    <StatCard
                        label="Reorder level"
                        value={`${Number(item.reorder_level)} ${item.unit}`}
                    />
                    <StatCard
                        label="Average cost"
                        value={item.average_cost.formatted}
                    />
                </div>
                <div className="rounded-xl border bg-card shadow-xs">
                    {movements.data.length === 0 ? (
                        <EmptyState icon={History} title="No movements yet" />
                    ) : (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>When</TableHead>
                                    <TableHead>Movement</TableHead>
                                    <TableHead className="text-right">
                                        Quantity
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Balance
                                    </TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Details
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {movements.data.map((movement) => {
                                    const quantity = Number(movement.quantity);

                                    return (
                                        <TableRow key={movement.id}>
                                            <TableCell>
                                                {formatDateTime(
                                                    movement.created_at,
                                                )}
                                                <span className="block text-xs text-muted-foreground">
                                                    {movement.user ?? 'System'}
                                                </span>
                                            </TableCell>
                                            <TableCell>
                                                {movement.type_label}
                                            </TableCell>
                                            <TableCell
                                                className={cn(
                                                    'text-right font-medium',
                                                    quantity < 0
                                                        ? 'text-destructive'
                                                        : 'text-emerald-700 dark:text-emerald-400',
                                                )}
                                            >
                                                {quantity > 0 ? '+' : ''}
                                                {quantity}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {Number(movement.balance_after)}
                                            </TableCell>
                                            <TableCell className="hidden text-sm whitespace-normal md:table-cell">
                                                {[
                                                    movement.reference,
                                                    movement.unit_cost &&
                                                        `@ ${movement.unit_cost.formatted}`,
                                                    movement.expires_on &&
                                                        `expires ${formatDate(movement.expires_on)}`,
                                                    movement.notes,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ') || '—'}
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    )}
                    <div className="border-t p-4">
                        <Pagination paginator={movements} />
                    </div>
                </div>
            </div>
        </>
    );
}

InventoryItem.layout = {
    breadcrumbs: [{ title: 'Inventory', href: admin.inventory.index() }],
};
