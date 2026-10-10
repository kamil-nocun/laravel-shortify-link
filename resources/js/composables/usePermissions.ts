import { usePage } from '@inertiajs/vue3';

export function usePermissions(): { can: (key: string) => boolean } {
    const page = usePage();

    return { can: (key) => page.props.auth?.can?.[key] === true };
}
