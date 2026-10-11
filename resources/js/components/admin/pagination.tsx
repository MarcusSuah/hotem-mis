import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types';

function cleanLabel(label: string): string {
    return label
        .replace('&laquo;', '‹')
        .replace('&raquo;', '›')
        .replace(/\s*(Previous|Next)\s*/g, '');
}

export function Pagination<T>({ paginator }: { paginator: Paginated<T> }) {
    if (paginator.last_page <= 1) {
        return paginator.total > 0 ? (
            <p className="text-sm text-muted-foreground">
                Showing {paginator.total}{' '}
                {paginator.total === 1 ? 'record' : 'records'}
            </p>
        ) : null;
    }

    return (
        <nav
            aria-label="Pagination"
            className="flex flex-col items-center justify-between gap-3 sm:flex-row"
        >
            <p className="text-sm text-muted-foreground">
                Showing {paginator.from}–{paginator.to} of {paginator.total}
            </p>
            <div className="flex flex-wrap items-center gap-1">
                {paginator.links.map((link, index) =>
                    link.url ? (
                        <Link
                            key={`${link.label}-${index}`}
                            href={link.url}
                            preserveScroll
                            preserveState
                            aria-current={link.active ? 'page' : undefined}
                            className={cn(
                                'inline-flex h-8 min-w-8 items-center justify-center rounded-md border px-2 text-sm transition-colors hover:bg-accent',
                                link.active &&
                                    'border-primary bg-primary text-primary-foreground hover:bg-primary/90',
                            )}
                        >
                            {cleanLabel(link.label)}
                        </Link>
                    ) : (
                        <span
                            key={`${link.label}-${index}`}
                            className="inline-flex h-8 min-w-8 items-center justify-center px-2 text-sm text-muted-foreground"
                            aria-hidden
                        >
                            {cleanLabel(link.label)}
                        </span>
                    ),
                )}
            </div>
        </nav>
    );
}
