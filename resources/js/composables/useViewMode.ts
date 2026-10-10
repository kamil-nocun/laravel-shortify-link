import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';

export function useViewMode(): { isAdminMode: ComputedRef<boolean> } {
    const page = usePage();

    return {
        isAdminMode: computed(() => /^\/admin(\/|\?|$)/.test(page.url)),
    };
}
