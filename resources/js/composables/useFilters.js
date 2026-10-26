import { router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

/**
 * Keeps a set of filter inputs in sync with the URL, debounced.
 * Empty strings are dropped so they don't appear as blank query params.
 */
export function useFilters(routeName, initial, delay = 300) {
    const filters = ref({ ...initial });
    let timer;

    watch(
        filters,
        () => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                const query = {};
                for (const [key, value] of Object.entries(filters.value)) {
                    if (value !== '' && value !== null && value !== undefined) {
                        query[key] = value;
                    }
                }
                router.get(route(routeName), query, {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                });
            }, delay);
        },
        { deep: true },
    );

    function reset() {
        for (const key of Object.keys(filters.value)) {
            filters.value[key] = '';
        }
    }

    return { filters, reset };
}
