import { router, usePage } from '@inertiajs/react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';
import { update } from '@/routes/currency';

/**
 * Chooses the currency prices are displayed in. Agreed amounts on bookings,
 * folios and invoices keep their original currency.
 */
export function CurrencySwitcher({ className }: { className?: string }) {
    const { currency } = usePage().props;

    if (currency.available.length < 2) {
        return null;
    }

    return (
        <Select
            value={currency.display}
            onValueChange={(value) =>
                router.post(
                    update.url(),
                    { currency: value },
                    { preserveScroll: true, preserveState: true },
                )
            }
        >
            <SelectTrigger
                aria-label="Display currency"
                size="sm"
                className={cn('w-20', className)}
            >
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {currency.available.map((code) => (
                    <SelectItem key={code} value={code}>
                        {code}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
