<script setup>
import { onBeforeUnmount, onMounted } from 'vue';

/**
 * A right-hand detail panel over a dimmed page. Mount it with v-if while open; it closes on
 * the backdrop, the X button or Escape, and locks page scroll while mounted.
 */
defineProps({
    label: { type: String, required: true }, // accessible name of the dialog
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const closeOnEscape = (e) => e.key === 'Escape' && emit('close');

onMounted(() => {
    document.addEventListener('keydown', closeOnEscape);
    document.body.style.overflow = 'hidden';
});
onBeforeUnmount(() => {
    document.removeEventListener('keydown', closeOnEscape);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <div class="fixed inset-0 z-50 flex justify-end">
            <div class="absolute inset-0 bg-black/40" @click="emit('close')" />

            <aside role="dialog" aria-modal="true" :aria-label="label" class="relative flex h-full w-full max-w-xl flex-col bg-gray-50 shadow-2xl">
                <header class="flex items-start justify-between gap-4 border-b border-gray-200 bg-white px-5 py-4">
                    <div class="min-w-0">
                        <slot name="header" />
                    </div>
                    <button type="button" @click="emit('close')" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100" aria-label="Close">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                </header>

                <div :class="loading ? 'opacity-50' : ''" class="flex-1 space-y-4 overflow-y-auto p-5 transition-opacity">
                    <slot />
                </div>

                <footer v-if="$slots.footer" class="flex gap-3 border-t border-gray-200 bg-white px-5 py-3">
                    <slot name="footer" />
                </footer>
            </aside>
        </div>
    </Teleport>
</template>
