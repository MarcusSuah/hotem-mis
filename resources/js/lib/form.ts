import { toast } from 'sonner';

/**
 * Single general message for a failed submission; field-level errors are
 * rendered inline. Success toasts come from server flash data so they are
 * never shown twice.
 */
export function notifyValidationFailed(): void {
    toast.error('Please correct the highlighted errors.');
}

/**
 * First error for a field or any of its nested entries (e.g. "gallery.2").
 */
export function fieldError(
    errors: Partial<Record<string, string>>,
    field: string,
): string | undefined {
    const all = errors as Record<string, string | undefined>;

    return (
        all[field] ??
        Object.entries(all).find(([key]) => key.startsWith(`${field}.`))?.[1]
    );
}
