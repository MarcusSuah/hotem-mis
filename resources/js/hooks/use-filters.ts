import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

/**
 * Keeps list filters in sync with the URL query string. Text input is
 * debounced; other changes apply immediately.
 */
export function useFilters<T extends Record<string, string>>(
    url: string,
    initial: T,
    debounceMs = 350,
): {
    filters: T;
    setFilter: (key: keyof T, value: string, immediate?: boolean) => void;
    reset: () => void;
} {
    const [filters, setFilters] = useState<T>(initial);
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        return () => {
            if (timer.current) {
                clearTimeout(timer.current);
            }
        };
    }, []);

    function visit(next: T) {
        const query = Object.fromEntries(
            Object.entries(next).filter(([, value]) => value !== ''),
        );

        router.get(url, query, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    }

    function setFilter(key: keyof T, value: string, immediate = false) {
        const next = { ...filters, [key]: value };
        setFilters(next);

        if (timer.current) {
            clearTimeout(timer.current);
        }

        if (immediate) {
            visit(next);

            return;
        }

        timer.current = setTimeout(() => visit(next), debounceMs);
    }

    function reset() {
        const cleared = Object.fromEntries(
            Object.keys(filters).map((key) => [key, '']),
        ) as T;

        setFilters(cleared);
        visit(cleared);
    }

    return { filters, setFilter, reset };
}
