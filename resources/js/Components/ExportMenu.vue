<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';

defineProps({
    items: { type: Array, required: true }, // [{ label, href, hint? }]
    label: { type: String, default: 'Export' },
});

const open = ref(false);
const root = ref(null);

function onClickOutside(e) {
    if (root.value && !root.value.contains(e.target)) open.value = false;
}

onMounted(() => document.addEventListener('click', onClickOutside));
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside));
</script>

<template>
    <div v-if="items.length" ref="root" class="relative">
        <button
            type="button"
            @click="open = !open"
            :aria-expanded="open"
            class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
        >
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
            </svg>
            {{ label }}
            <svg :class="open ? 'rotate-180' : ''" class="h-4 w-4 text-gray-400 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
            </svg>
        </button>

        <Transition
            enter-active-class="transition ease-out duration-100"
            enter-from-class="opacity-0 scale-95"
            enter-to-class="opacity-100 scale-100"
            leave-active-class="transition ease-in duration-75"
            leave-from-class="opacity-100 scale-100"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="open"
                class="absolute right-0 z-30 mt-2 w-64 origin-top-right rounded-lg border border-gray-200 bg-white py-1 shadow-lg"
            >
                <a
                    v-for="item in items"
                    :key="item.label"
                    :href="item.href"
                    @click="open = false"
                    class="block px-4 py-2 hover:bg-gray-50"
                >
                    <span class="block text-sm text-gray-900">{{ item.label }}</span>
                    <span v-if="item.hint" class="block text-xs text-gray-500">{{ item.hint }}</span>
                </a>
            </div>
        </Transition>
    </div>
</template>
