import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import site from '@/routes/site';

export type SearchCriteria = {
    check_in_date: string;
    check_out_date: string;
    adults: number;
    children: number;
    offer?: string | null;
};

function isoDate(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function addDays(value: string, days: number): string {
    const date = new Date(`${value}T00:00:00`);
    date.setDate(date.getDate() + days);

    return isoDate(date);
}

/**
 * Dates and party size; submits to the availability search.
 */
export function BookingSearchForm({
    initial,
    errors = {},
    variant = 'card',
    className,
}: {
    initial?: Partial<SearchCriteria>;
    errors?: Partial<Record<string, string>>;
    variant?: 'card' | 'hero';
    className?: string;
}) {
    const today = isoDate(new Date());
    const [data, setData] = useState<SearchCriteria>({
        check_in_date: initial?.check_in_date || today,
        check_out_date:
            initial?.check_out_date ||
            addDays(initial?.check_in_date || today, 1),
        adults: initial?.adults ?? 2,
        children: initial?.children ?? 0,
        offer: initial?.offer ?? null,
    });
    const [submitting, setSubmitting] = useState(false);

    function submit(event: FormEvent) {
        event.preventDefault();
        const query: Record<string, string | number> = {
            check_in_date: data.check_in_date,
            check_out_date: data.check_out_date,
            adults: data.adults,
            children: data.children,
        };

        if (data.offer) {
            query.offer = data.offer;
        }

        router.get(site.booking.availability.url(), query, {
            onStart: () => setSubmitting(true),
            onFinish: () => setSubmitting(false),
        });
    }

    const fieldClass =
        variant === 'hero' ? 'bg-white text-charcoal' : undefined;

    return (
        <form
            onSubmit={submit}
            className={cn(
                'grid gap-3 rounded-xl p-4 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_7rem_7rem_auto] lg:items-end',
                variant === 'hero'
                    ? 'bg-white/95 shadow-xl dark:bg-card'
                    : 'border bg-card shadow-xs',
                className,
            )}
            aria-label="Check availability"
        >
            <div className="space-y-1.5">
                <Label htmlFor="bs-in">Arrival</Label>
                <Input
                    id="bs-in"
                    type="date"
                    min={today}
                    className={fieldClass}
                    value={data.check_in_date}
                    onChange={(event) => {
                        const checkIn = event.target.value;
                        setData((current) => ({
                            ...current,
                            check_in_date: checkIn,
                            check_out_date:
                                current.check_out_date > checkIn
                                    ? current.check_out_date
                                    : addDays(checkIn, 1),
                        }));
                    }}
                    aria-invalid={errors.check_in_date ? true : undefined}
                    required
                />
                {errors.check_in_date && (
                    <p className="text-xs text-destructive">
                        {errors.check_in_date}
                    </p>
                )}
            </div>
            <div className="space-y-1.5">
                <Label htmlFor="bs-out">Departure</Label>
                <Input
                    id="bs-out"
                    type="date"
                    min={addDays(data.check_in_date, 1)}
                    className={fieldClass}
                    value={data.check_out_date}
                    onChange={(event) =>
                        setData((current) => ({
                            ...current,
                            check_out_date: event.target.value,
                        }))
                    }
                    aria-invalid={errors.check_out_date ? true : undefined}
                    required
                />
                {errors.check_out_date && (
                    <p className="text-xs text-destructive">
                        {errors.check_out_date}
                    </p>
                )}
            </div>
            <div className="space-y-1.5">
                <Label htmlFor="bs-adults">Adults</Label>
                <Input
                    id="bs-adults"
                    type="number"
                    min={1}
                    max={10}
                    className={fieldClass}
                    value={data.adults}
                    onChange={(event) =>
                        setData((current) => ({
                            ...current,
                            adults: Number(event.target.value),
                        }))
                    }
                    required
                />
                {errors.adults && (
                    <p className="text-xs text-destructive">{errors.adults}</p>
                )}
            </div>
            <div className="space-y-1.5">
                <Label htmlFor="bs-children">Children</Label>
                <Input
                    id="bs-children"
                    type="number"
                    min={0}
                    max={10}
                    className={fieldClass}
                    value={data.children}
                    onChange={(event) =>
                        setData((current) => ({
                            ...current,
                            children: Number(event.target.value),
                        }))
                    }
                />
            </div>
            <Button
                type="submit"
                variant="gold"
                size="lg"
                disabled={submitting}
                className="sm:col-span-2 lg:col-span-1"
            >
                <Search />
                Check availability
            </Button>
        </form>
    );
}
