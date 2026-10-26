<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { computed, useSlots } from 'vue';

// Switching between reports happens in the sidebar's Reports group, so the shell has no tabs of its own.
const props = defineProps({
    section: { type: String, required: true },
    sections: { type: Array, default: () => [] },
});

const slots = useSlots();

const current = computed(() => props.sections.find((s) => s.key === props.section));
</script>

<template>
    <Head :title="`${current?.label ?? 'Reports'} · Reports`" />

    <AppLayout>
        <template #header>
            <div class="flex items-center gap-2 text-sm">
                <span class="text-gray-500">Reports</span>
                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                </svg>
                <span class="font-semibold text-gray-900">{{ current?.label }}</span>
            </div>
        </template>

        <!-- Title + actions -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-2xl font-semibold tracking-tight text-gray-900">{{ current?.label ?? 'Reports' }}</h1>
                <p v-if="current?.desc" class="mt-1 text-sm text-gray-600">{{ current.desc }}</p>
            </div>
            <div v-if="slots.actions" class="flex shrink-0 gap-2">
                <slot name="actions" />
            </div>
        </div>

        <!-- Filters: each change applies straight away -->
        <section
            v-if="slots.filters"
            aria-label="Report filters"
            class="mt-5 rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm"
        >
            <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
                <p class="flex shrink-0 items-center gap-2 text-xs font-semibold uppercase tracking-wide text-gray-500 lg:pb-2.5">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c2.755 0 5.455.232 8.083.678.533.09.917.556.917 1.096v1.044a2.25 2.25 0 0 1-.659 1.591l-5.432 5.432a2.25 2.25 0 0 0-.659 1.591v2.927a2.25 2.25 0 0 1-1.244 2.013L9.75 21v-6.568a2.25 2.25 0 0 0-.659-1.591L3.659 7.409A2.25 2.25 0 0 1 3 5.818V4.774c0-.54.384-1.006.917-1.096A48.32 48.32 0 0 1 12 3Z" />
                    </svg>
                    Filters
                </p>
                <div class="grid flex-1 gap-3 sm:flex sm:flex-wrap sm:items-end">
                    <slot name="filters" />
                </div>
            </div>
        </section>

        <div class="mt-6 space-y-6">
            <slot />
        </div>
    </AppLayout>
</template>
