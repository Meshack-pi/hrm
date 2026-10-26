<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const messages = ref([]);
let counter = 0;

function addMessage(type, text) {
    const id = ++counter;
    messages.value.push({ id, type, text });
    setTimeout(() => {
        messages.value = messages.value.filter(m => m.id !== id);
    }, 5000);
}

function dismiss(id) {
    messages.value = messages.value.filter(m => m.id !== id);
}

watch(() => page.props.flash, (flash) => {
    if (flash?.success) addMessage('success', flash.success);
    if (flash?.error) addMessage('error', flash.error);
    if (flash?.warning) addMessage('warning', flash.warning);
}, { deep: true, immediate: true });

const iconMap = {
    success: 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
    error: 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
    warning: 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z',
};

const colorMap = {
    success: 'bg-green-50 border-green-400 text-green-800',
    error: 'bg-red-50 border-red-400 text-red-800',
    warning: 'bg-yellow-50 border-yellow-400 text-yellow-800',
};
</script>

<template>
    <div class="fixed top-4 right-4 z-50 space-y-2 w-80">
        <TransitionGroup
            enter-active-class="transition ease-out duration-300"
            enter-from-class="translate-x-full opacity-0"
            enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="translate-x-0 opacity-100"
            leave-to-class="translate-x-full opacity-0"
        >
            <div
                v-for="msg in messages"
                :key="msg.id"
                :class="colorMap[msg.type]"
                class="flex items-start gap-3 rounded-lg border p-4 shadow-lg"
            >
                <svg class="h-5 w-5 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" :d="iconMap[msg.type]" />
                </svg>
                <p class="flex-1 text-sm font-medium">{{ msg.text }}</p>
                <button @click="dismiss(msg.id)" class="shrink-0 text-current opacity-50 hover:opacity-100">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
