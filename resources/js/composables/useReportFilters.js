import { router } from '@inertiajs/vue3';
import { reactive, watch } from 'vue';

/**
 * Keeps a set of filter dropdowns in sync with the current report URL.
 * Empty values are dropped so the URL stays clean.
 */
export function useReportFilters(routeName, initial) {
    const filters = reactive({ ...initial });

    watch(
        () => ({ ...filters }),
        (value) => {
            const params = Object.fromEntries(
                Object.entries(value).filter(([, v]) => v !== '' && v !== null && v !== undefined)
            );
            router.get(route(routeName), params, { preserveState: true, preserveScroll: true, replace: true });
        }
    );

    return filters;
}

export const money = (v) =>
    Number(v ?? 0).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

export const whole = (v) => Number(v ?? 0).toLocaleString('en-KE', { maximumFractionDigits: 0 });

export const shortDate = (d) =>
    new Date(d).toLocaleDateString('en-KE', { day: 'numeric', month: 'short', year: 'numeric' });

export const selectClass =
    'mt-1 block w-full rounded-lg border-gray-300 sm:w-auto sm:min-w-[11rem] text-sm shadow-sm focus:border-[#4CAF50] focus:ring-[#4CAF50]';
