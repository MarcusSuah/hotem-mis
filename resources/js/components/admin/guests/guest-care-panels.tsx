import { Link, router, useForm } from '@inertiajs/react';
import { Award, MessageSquareWarning, Plus, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Field } from '@/components/admin/field';
import { FormDialog } from '@/components/admin/form-dialog';
import { OptionSelect } from '@/components/admin/option-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { notifyValidationFailed } from '@/lib/form';
import { formatDate, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';
import admin from '@/routes/admin';

export type GuestPreference = {
    id: number;
    category: string;
    preference: string;
};

export type GuestLoyalty = {
    enabled: boolean;
    balance: number;
    transactions: {
        id: number;
        points: number;
        reason: string;
        reservation: string | null;
        created_at: string | null;
    }[];
};

export type GuestComplaint = {
    reference: string;
    subject: string;
    status: string;
    status_label: string;
    severity: string;
    created_at: string | null;
};

const preferenceCategories = [
    { value: 'room', label: 'Room' },
    { value: 'dining', label: 'Dining' },
    { value: 'amenities', label: 'Amenities' },
    { value: 'communication', label: 'Communication' },
    { value: 'accessibility', label: 'Accessibility' },
    { value: 'other', label: 'Other' },
];

/**
 * Preferences, loyalty balance and complaint history shown on the guest profile.
 */
export function GuestCarePanels({
    guestId,
    preferences,
    loyalty,
    complaints,
    canUpdate,
}: {
    guestId: number;
    preferences: GuestPreference[];
    loyalty: GuestLoyalty;
    complaints: GuestComplaint[];
    canUpdate: boolean;
}) {
    const preference = useForm({ category: 'room', preference: '' });
    const [adjusting, setAdjusting] = useState(false);

    return (
        <div className="grid gap-6 lg:grid-cols-3">
            <section className="space-y-3 rounded-xl border bg-card p-5 text-sm shadow-xs">
                <h2 className="font-display text-xl font-semibold">
                    Preferences
                </h2>
                {preferences.length === 0 && (
                    <p className="text-muted-foreground">
                        No preferences recorded.
                    </p>
                )}
                <ul className="flex flex-wrap gap-2">
                    {preferences.map((row) => (
                        <li key={row.id}>
                            <Badge variant="secondary" className="gap-1">
                                <span className="text-muted-foreground capitalize">
                                    {row.category}:
                                </span>{' '}
                                {row.preference}
                                {canUpdate && (
                                    <button
                                        type="button"
                                        aria-label={`Remove ${row.preference}`}
                                        onClick={() =>
                                            router.delete(
                                                admin.guests.preferences.destroy.url(
                                                    {
                                                        guest: guestId,
                                                        preference: row.id,
                                                    },
                                                ),
                                                { preserveScroll: true },
                                            )
                                        }
                                    >
                                        <X className="size-3" />
                                    </button>
                                )}
                            </Badge>
                        </li>
                    ))}
                </ul>
                {canUpdate && (
                    <form
                        className="flex gap-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            preference.post(
                                admin.guests.preferences.store.url(guestId),
                                {
                                    preserveScroll: true,
                                    onSuccess: () =>
                                        preference.reset('preference'),
                                },
                            );
                        }}
                    >
                        <OptionSelect
                            ariaLabel="Preference category"
                            className="w-36"
                            value={preference.data.category}
                            options={preferenceCategories}
                            onChange={(value) =>
                                preference.setData('category', value)
                            }
                        />
                        <Input
                            aria-label="Preference"
                            placeholder="High floor, feather-free pillows…"
                            value={preference.data.preference}
                            onChange={(event) =>
                                preference.setData(
                                    'preference',
                                    event.target.value,
                                )
                            }
                        />
                        <Button
                            type="submit"
                            size="icon"
                            aria-label="Add preference"
                            disabled={
                                preference.processing ||
                                preference.data.preference.trim() === ''
                            }
                        >
                            <Plus />
                        </Button>
                    </form>
                )}
                {preference.errors.preference && (
                    <p className="text-destructive">
                        {preference.errors.preference}
                    </p>
                )}
            </section>

            {loyalty.enabled && (
                <section className="space-y-3 rounded-xl border bg-card p-5 text-sm shadow-xs">
                    <div className="flex items-center justify-between">
                        <h2 className="font-display text-xl font-semibold">
                            Loyalty
                        </h2>
                        {canUpdate && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setAdjusting(true)}
                            >
                                Adjust
                            </Button>
                        )}
                    </div>
                    <p className="flex items-center gap-2 text-2xl font-semibold">
                        <Award className="size-5 text-highlight" />
                        {formatNumber(loyalty.balance)}{' '}
                        <span className="text-sm font-normal text-muted-foreground">
                            points
                        </span>
                    </p>
                    <ul className="space-y-1">
                        {loyalty.transactions.map((row) => (
                            <li
                                key={row.id}
                                className="flex justify-between gap-2"
                            >
                                <span className="truncate">
                                    {row.reason}
                                    <span className="block text-xs text-muted-foreground">
                                        {formatDate(row.created_at)}
                                        {row.reservation &&
                                            ` · ${row.reservation}`}
                                    </span>
                                </span>
                                <span
                                    className={cn(
                                        'font-medium',
                                        row.points < 0
                                            ? 'text-destructive'
                                            : 'text-emerald-700 dark:text-emerald-400',
                                    )}
                                >
                                    {row.points > 0 ? '+' : ''}
                                    {formatNumber(row.points)}
                                </span>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <section className="space-y-3 rounded-xl border bg-card p-5 text-sm shadow-xs">
                <div className="flex items-center justify-between">
                    <h2 className="font-display text-xl font-semibold">
                        Complaints
                    </h2>
                    <Link
                        href={admin.complaints.index()}
                        className="text-xs text-primary hover:underline"
                    >
                        All complaints
                    </Link>
                </div>
                {complaints.length === 0 ? (
                    <p className="text-muted-foreground">
                        No complaints on record.
                    </p>
                ) : (
                    <ul className="space-y-2">
                        {complaints.map((row) => (
                            <li
                                key={row.reference}
                                className="flex items-start gap-2"
                            >
                                <MessageSquareWarning className="mt-0.5 size-4 shrink-0 text-highlight" />
                                <span className="flex-1">
                                    {row.subject}
                                    <span className="block text-xs text-muted-foreground">
                                        {row.reference} ·{' '}
                                        {formatDate(row.created_at)}
                                    </span>
                                </span>
                                <Badge
                                    variant={
                                        ['resolved', 'closed'].includes(
                                            row.status,
                                        )
                                            ? 'muted'
                                            : 'warning'
                                    }
                                >
                                    {row.status_label}
                                </Badge>
                            </li>
                        ))}
                    </ul>
                )}
            </section>

            <LoyaltyDialog
                open={adjusting}
                onClose={() => setAdjusting(false)}
                guestId={guestId}
                balance={loyalty.balance}
            />
        </div>
    );
}

function LoyaltyDialog({
    open,
    onClose,
    guestId,
    balance,
}: {
    open: boolean;
    onClose: () => void;
    guestId: number;
    balance: number;
}) {
    const form = useForm({ points: '', reason: '' });

    useEffect(() => {
        if (open) {
            form.reset();
            form.clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    return (
        <FormDialog
            open={open}
            onOpenChange={(next) => !next && onClose()}
            title="Adjust loyalty points"
            description={`Current balance ${formatNumber(balance)}. Use a negative number to deduct. Adjustments are audited.`}
            processing={form.processing}
            onSubmit={() =>
                form.post(admin.guests.loyalty.store.url(guestId), {
                    preserveScroll: true,
                    onSuccess: onClose,
                    onError: notifyValidationFailed,
                })
            }
        >
            <Field
                label="Points"
                htmlFor="lp-points"
                error={form.errors.points}
                required
            >
                <Input
                    id="lp-points"
                    type="number"
                    value={form.data.points}
                    onChange={(event) =>
                        form.setData('points', event.target.value)
                    }
                />
            </Field>
            <Field
                label="Reason"
                htmlFor="lp-reason"
                error={form.errors.reason}
                required
            >
                <Input
                    id="lp-reason"
                    value={form.data.reason}
                    onChange={(event) =>
                        form.setData('reason', event.target.value)
                    }
                    placeholder="Goodwill gesture for delayed check-in"
                />
            </Field>
        </FormDialog>
    );
}
