<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
    size: { type: String, default: 'sm' },
});

const colorMap = {
    active: 'bg-green-100 text-green-800',
    inactive: 'bg-gray-100 text-gray-800',
    suspended: 'bg-red-100 text-red-800',
    on_leave: 'bg-yellow-100 text-yellow-800',
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    cancelled: 'bg-gray-100 text-gray-600',
    draft: 'bg-gray-100 text-gray-700',
    calculated: 'bg-blue-100 text-blue-800',
    paid: 'bg-green-100 text-green-800',
    partially_paid: 'bg-yellow-100 text-yellow-800',
    failed: 'bg-red-100 text-red-800',
    permanent: 'bg-blue-100 text-blue-800',
    contract: 'bg-purple-100 text-purple-800',
    casual: 'bg-orange-100 text-orange-800',
    intern: 'bg-cyan-100 text-cyan-800',
};

const classes = computed(() => {
    const base = colorMap[props.status] || 'bg-gray-100 text-gray-700';
    const sizeClass = props.size === 'xs' ? 'px-1.5 py-0.5 text-xs' : 'px-2 py-1 text-xs';
    return `${base} ${sizeClass}`;
});

const label = computed(() => {
    return props.status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
});
</script>

<template>
    <span :class="classes" class="inline-flex items-center rounded-full font-medium leading-none whitespace-nowrap">
        {{ label }}
    </span>
</template>
