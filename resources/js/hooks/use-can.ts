import { usePage } from '@inertiajs/react';
import { useCallback } from 'react';

/**
 * Permission checks for showing or hiding UI. The server always enforces
 * authorization independently.
 */
export function useCan(): {
    can: (permission: string) => boolean;
    canAny: (permissions: string[]) => boolean;
} {
    const { auth } = usePage().props;
    const granted = auth.permissions;

    const can = useCallback(
        (permission: string) => granted.includes(permission),
        [granted],
    );

    const canAny = useCallback(
        (permissions: string[]) =>
            permissions.length === 0 ||
            permissions.some((permission) => granted.includes(permission)),
        [granted],
    );

    return { can, canAny };
}
