import { usePage } from '@inertiajs/react';

/**
 * Show or hide UI based on what the signed-in user may do. This is a convenience only:
 * every action is enforced again on the server by the `permission:` route middleware.
 */
export function usePermission() {
    const { auth } = usePage().props;
    const held = auth.permissions ?? [];
    const isAdmin = held.includes('*');

    const can = (permission: string): boolean =>
        isAdmin || held.includes(permission);
    const canAny = (permissions: string[]): boolean => permissions.some(can);

    return { can, canAny, isAdmin };
}
