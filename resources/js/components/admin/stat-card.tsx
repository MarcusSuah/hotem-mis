import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export function StatCard({
    label,
    value,
    icon: Icon,
    hint,
    tone = 'default',
    className,
}: {
    label: string;
    value: ReactNode;
    icon?: LucideIcon;
    hint?: ReactNode;
    tone?: 'default' | 'gold';
    className?: string;
}) {
    return (
        <div
            className={cn(
                'relative overflow-hidden rounded-xl border bg-card p-5 shadow-xs',
                tone === 'gold' && 'border-highlight/50',
                className,
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <p className="text-sm font-medium text-muted-foreground">
                    {label}
                </p>
                {Icon && (
                    <span
                        className={cn(
                            'flex size-9 items-center justify-center rounded-lg bg-secondary text-primary dark:text-foreground',
                            tone === 'gold' &&
                                'bg-highlight/20 text-highlight-foreground dark:text-highlight',
                        )}
                    >
                        <Icon className="size-4" />
                    </span>
                )}
            </div>
            <p className="mt-2 font-display text-3xl font-semibold tracking-tight">
                {value}
            </p>
            {hint && (
                <div className="mt-1 text-xs text-muted-foreground">{hint}</div>
            )}
        </div>
    );
}
