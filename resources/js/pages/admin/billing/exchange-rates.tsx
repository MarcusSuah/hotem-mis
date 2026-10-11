import { Head, useForm } from '@inertiajs/react';
import { ArrowRightLeft } from 'lucide-react';
import { useState } from 'react';
import { Field } from '@/components/admin/field';
import { FormDialog } from '@/components/admin/form-dialog';
import { PageHeader } from '@/components/admin/page-header';
import { StatCard } from '@/components/admin/stat-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { notifyValidationFailed } from '@/lib/form';
import { formatDateTime } from '@/lib/format';
import admin from '@/routes/admin';

type Props = {
    baseCurrency: string;
    quoteCurrency: string;
    current: { rate: string; effective_at: string } | null;
    history: {
        id: number;
        base_currency: string;
        quote_currency: string;
        rate: string;
        effective_at: string;
        created_by: string | null;
        notes: string | null;
        is_future: boolean;
    }[];
    can: { manage: boolean };
};

const trim = (rate: string) => rate.replace(/(\.\d*?[1-9])0+$|\.0+$/, '$1');

export default function ExchangeRates({
    baseCurrency,
    quoteCurrency,
    current,
    history,
    can,
}: Props) {
    const [open, setOpen] = useState(false);
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    const form = useForm({
        base_currency: baseCurrency,
        quote_currency: quoteCurrency,
        rate: '',
        effective_at: now.toISOString().slice(0, 16),
        notes: '',
    });

    return (
        <>
            <Head title="Exchange rates" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Exchange rates"
                    description="New rates apply from their effective time. Bookings, payments and invoices keep the rate recorded when they were made."
                    actions={
                        can.manage && (
                            <Button onClick={() => setOpen(true)}>
                                <ArrowRightLeft />
                                Set new rate
                            </Button>
                        )
                    }
                />

                <div className="grid gap-4 sm:grid-cols-2">
                    <StatCard
                        label={`Current rate (${baseCurrency} → ${quoteCurrency})`}
                        value={
                            current
                                ? `1 ${baseCurrency} = ${trim(current.rate)} ${quoteCurrency}`
                                : 'Not set'
                        }
                        hint={
                            current
                                ? `Effective ${formatDateTime(current.effective_at)}`
                                : 'Bookings in the other currency are disabled until a rate is set.'
                        }
                        icon={ArrowRightLeft}
                        tone="gold"
                    />
                </div>

                <section className="rounded-xl border bg-card shadow-xs">
                    <h2 className="border-b p-4 font-display text-xl font-semibold">
                        History
                    </h2>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Effective</TableHead>
                                <TableHead>Rate</TableHead>
                                <TableHead>Set by</TableHead>
                                <TableHead className="hidden md:table-cell">
                                    Notes
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {history.map((rate) => (
                                <TableRow key={rate.id}>
                                    <TableCell>
                                        {formatDateTime(rate.effective_at)}{' '}
                                        {rate.is_future && (
                                            <Badge variant="gold">
                                                Scheduled
                                            </Badge>
                                        )}
                                    </TableCell>
                                    <TableCell className="font-mono">
                                        1 {rate.base_currency} ={' '}
                                        {trim(rate.rate)} {rate.quote_currency}
                                    </TableCell>
                                    <TableCell>
                                        {rate.created_by ?? 'System'}
                                    </TableCell>
                                    <TableCell className="hidden text-muted-foreground md:table-cell">
                                        {rate.notes ?? '—'}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </section>
            </div>

            <FormDialog
                open={open}
                onOpenChange={setOpen}
                title="Set a new exchange rate"
                description="Rates cannot be back-dated or edited; each change is kept in the history."
                submitLabel="Save rate"
                processing={form.processing}
                onSubmit={() =>
                    form.post(admin.exchangeRates.store.url(), {
                        preserveScroll: true,
                        onSuccess: () => {
                            form.reset('rate', 'notes');
                            setOpen(false);
                        },
                        onError: notifyValidationFailed,
                    })
                }
            >
                <Field
                    label={`${form.data.quote_currency} per 1 ${form.data.base_currency}`}
                    htmlFor="rate"
                    error={form.errors.rate}
                    required
                >
                    <Input
                        id="rate"
                        inputMode="decimal"
                        value={form.data.rate}
                        onChange={(event) =>
                            form.setData('rate', event.target.value)
                        }
                        placeholder="192.50"
                    />
                </Field>
                <Field
                    label="Effective from"
                    htmlFor="effective"
                    error={form.errors.effective_at}
                    required
                >
                    <Input
                        id="effective"
                        type="datetime-local"
                        value={form.data.effective_at}
                        onChange={(event) =>
                            form.setData('effective_at', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label="Notes"
                    htmlFor="rate-notes"
                    error={form.errors.notes}
                >
                    <Input
                        id="rate-notes"
                        value={form.data.notes}
                        onChange={(event) =>
                            form.setData('notes', event.target.value)
                        }
                        placeholder="Central Bank of Liberia mid-rate"
                    />
                </Field>
                {form.errors.quote_currency && (
                    <p className="text-sm text-destructive">
                        {form.errors.quote_currency}
                    </p>
                )}
            </FormDialog>
        </>
    );
}

ExchangeRates.layout = {
    breadcrumbs: [
        { title: 'Exchange rates', href: admin.exchangeRates.index() },
    ],
};
