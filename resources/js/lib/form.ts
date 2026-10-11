import { toast } from 'sonner';

/**
 * Single general message for a failed submission; field-level errors are
 * rendered inline. Success toasts come from server flash data so they are
 * never shown twice.
 */
export function notifyValidationFailed(): void {
    toast.error('Please correct the highlighted errors.');
}
