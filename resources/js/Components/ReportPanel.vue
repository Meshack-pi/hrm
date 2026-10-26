<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
    flush: { type: Boolean, default: false }, // no inner padding, for tables
});
</script>

<template>
    <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
        <header class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 px-5 py-3">
            <div>
                <h2 class="text-sm font-semibold text-gray-900">{{ title }}</h2>
                <p v-if="subtitle" class="text-xs text-gray-500">{{ subtitle }}</p>
            </div>
            <slot name="aside" />
        </header>
        <div :class="flush ? '' : 'p-5'">
            <slot />
        </div>
    </section>
</template>
