<script setup>
defineProps({
    columns: { type: Array, required: true },
    empty: { type: String, default: 'No records found.' },
});
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-gray-200 shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th
                            v-for="col in columns"
                            :key="col.key || col"
                            class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-600"
                        >
                            <template v-if="typeof col === 'string'">{{ col }}</template>
                            <template v-else-if="col.label">{{ col.label }}</template>
                            <span v-else class="sr-only">Actions</span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white">
                    <slot />
                    <tr v-if="!$slots.default">
                        <td :colspan="columns.length" class="px-4 py-8 text-center text-sm text-gray-500">
                            {{ empty }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
