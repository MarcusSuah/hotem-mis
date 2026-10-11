import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { Field } from '@/components/admin/field';
import { FormDialog } from '@/components/admin/form-dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { notifyValidationFailed } from '@/lib/form';
import admin from '@/routes/admin';
import type { MoneyValue } from '@/types';

export type PaymentMethodOption = {
    value: string;
    label: string;
    requires_reference: boolean;
};

/**
 * Records a payment against a folio. A fresh idempotency key per opening
 * guarantees a double click never records the payment twice.
 */
export function RecordPaymentDialog({
    open,
    onOpenChange,
    folioNumber,
    suggested,
    methods,
    currencies,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    folioNumber: string;
    suggested: MoneyValue | null;
    methods: PaymentMethodOption[];
    currencies: string[];
}) {
    const form = useForm({
        method: 'cash',
        amount: '',
        currency: (suggested?.currency ?? 'USD') as string,
        external_reference: '',
        notes: '',
        idempotency_key: '',
    });
    const { data, setData, errors } = form;
    const method = methods.find((option) => option.value === data.method);

    useEffect(() => {
        if (open) {
            form.clearErrors();
            form.setData({
                method: 'cash',
                amount:
                    suggested && suggested.minor > 0 ? suggested.amount : '',
                currency: (suggested?.currency ?? 'USD') as string,
                external_reference: '',
                notes: '',
                idempotency_key: crypto.randomUUID(),
            });
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    return (
        <FormDialog
            open={open}
            onOpenChange={onOpenChange}
            title="Record payment"
            description={`Folio ${folioNumber}. Payments in another currency are converted at today's rate, which is stored with the payment.`}
            submitLabel="Record payment"
            processing={form.processing}
            onSubmit={() =>
                form.post(admin.folios.payments.store.url(folioNumber), {
                    preserveScroll: true,
                    onSuccess: () => onOpenChange(false),
                    onError: notifyValidationFailed,
                })
            }
        >
            <div className="grid gap-4 sm:grid-cols-3">
                <Field
                    label="Method"
                    htmlFor="pay-method"
                    error={errors.method}
                    required
                    className="sm:col-span-3"
                >
                    <Select
                        value={data.method}
                        onValueChange={(value) => setData('method', value)}
                    >
                        <SelectTrigger id="pay-method">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {methods.map((option) => (
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
                    label="Amount"
                    htmlFor="pay-amount"
                    error={errors.amount}
                    required
                    className="sm:col-span-2"
                >
                    <Input
                        id="pay-amount"
                        inputMode="decimal"
                        value={data.amount}
                        onChange={(event) =>
                            setData('amount', event.target.value)
                        }
                        aria-invalid={Boolean(errors.amount)}
                    />
                </Field>
                <Field
                    label="Currency"
                    htmlFor="pay-currency"
                    error={errors.currency}
                    required
                >
                    <Select
                        value={data.currency}
                        onValueChange={(value) => setData('currency', value)}
                    >
                        <SelectTrigger id="pay-currency">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {currencies.map((currency) => (
                                <SelectItem key={currency} value={currency}>
                                    {currency}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </Field>
                {method?.requires_reference && (
                    <Field
                        label="Transaction reference"
                        htmlFor="pay-ref"
                        error={errors.external_reference}
                        required
                        className="sm:col-span-3"
                    >
                        <Input
                            id="pay-ref"
                            value={data.external_reference}
                            onChange={(event) =>
                                setData(
                                    'external_reference',
                                    event.target.value,
                                )
                            }
                            placeholder="Terminal slip, bank or mobile money reference"
                        />
                    </Field>
                )}
                <Field
                    label="Notes"
                    htmlFor="pay-notes"
                    error={errors.notes}
                    className="sm:col-span-3"
                >
                    <Input
                        id="pay-notes"
                        value={data.notes}
                        onChange={(event) =>
                            setData('notes', event.target.value)
                        }
                    />
                </Field>
            </div>
        </FormDialog>
    );
}
