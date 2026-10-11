import { AlertCircle } from 'lucide-react';

/**
 * Form-level summary of validation errors, useful on long forms where an
 * inline error may be scrolled out of view.
 */
export function FormErrorSummary({
    errors,
}: {
    errors: Partial<Record<string, string>>;
}) {
    const messages = Object.values(errors).filter(
        (message): message is string => Boolean(message),
    );

    if (messages.length === 0) {
        return null;
    }

    return (
        <div
            role="alert"
            className="flex gap-3 rounded-md border border-destructive/30 bg-destructive/5 p-3 text-sm text-destructive"
        >
            <AlertCircle className="mt-0.5 size-4 shrink-0" />
            <div>
                <p className="font-medium">
                    Please correct{' '}
                    {messages.length === 1
                        ? 'the error'
                        : `${messages.length} errors`}{' '}
                    below.
                </p>
                {messages.length > 1 && (
                    <ul className="mt-1 list-inside list-disc space-y-0.5">
                        {[...new Set(messages)].slice(0, 6).map((message) => (
                            <li key={message}>{message}</li>
                        ))}
                    </ul>
                )}
            </div>
        </div>
    );
}
