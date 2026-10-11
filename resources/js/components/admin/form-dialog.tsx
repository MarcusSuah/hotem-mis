import type { FormEvent, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

const sizes = {
    md: 'sm:max-w-lg',
    lg: 'sm:max-w-2xl',
    xl: 'sm:max-w-4xl',
};

/**
 * Accessible modal wrapping a form. The parent controls `open` and closes the
 * dialog only after a successful submission, so validation errors keep it open.
 */
export function FormDialog({
    open,
    onOpenChange,
    title,
    description,
    submitLabel = 'Save',
    processing = false,
    onSubmit,
    size = 'md',
    children,
    footerStart,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    submitLabel?: string;
    processing?: boolean;
    onSubmit: () => void;
    size?: keyof typeof sizes;
    children: ReactNode;
    footerStart?: ReactNode;
}) {
    function handleSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!processing) {
            onSubmit();
        }
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => !processing && onOpenChange(next)}
        >
            <DialogContent
                className={cn(
                    'flex max-h-[calc(100dvh-2rem)] flex-col gap-0 p-0',
                    sizes[size],
                )}
            >
                <form
                    onSubmit={handleSubmit}
                    className="flex min-h-0 flex-1 flex-col"
                    noValidate
                >
                    <DialogHeader className="border-b px-6 pt-6 pb-4">
                        <DialogTitle className="font-display text-2xl text-primary dark:text-foreground">
                            {title}
                        </DialogTitle>
                        {description && (
                            <DialogDescription>{description}</DialogDescription>
                        )}
                    </DialogHeader>

                    <div className="min-h-0 flex-1 space-y-5 overflow-y-auto px-6 py-5">
                        {children}
                    </div>

                    <DialogFooter className="items-center border-t px-6 py-4">
                        {footerStart && (
                            <div className="mr-auto text-sm text-muted-foreground">
                                {footerStart}
                            </div>
                        )}
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing && <Spinner />}
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
