<script setup>
import { ref } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Are you sure?' },
    message: { type: String, default: 'This action cannot be undone.' },
    confirmLabel: { type: String, default: 'Confirm' },
    cancelLabel: { type: String, default: 'Cancel' },
    variant: { type: String, default: 'danger' },
});

const emit = defineEmits(['confirm', 'cancel']);

const btnClass = {
    danger: 'bg-red-600 hover:bg-red-700 focus:ring-red-500',
    warning: 'bg-yellow-600 hover:bg-yellow-700 focus:ring-yellow-500',
    primary: 'bg-[#1B5E20] hover:bg-[#2E7D32] focus:ring-green-500',
};
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/50" @click="$emit('cancel')" />
                <div class="relative bg-white rounded-lg shadow-xl w-full max-w-md p-6">
                    <h3 class="text-lg font-semibold text-gray-900">{{ title }}</h3>
                    <p class="mt-2 text-sm text-gray-600">{{ message }}</p>
                    <div class="mt-6 flex justify-end gap-3">
                        <button
                            @click="$emit('cancel')"
                            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50"
                        >
                            {{ cancelLabel }}
                        </button>
                        <button
                            @click="$emit('confirm')"
                            :class="btnClass[variant]"
                            class="px-4 py-2 text-sm font-medium text-white rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2"
                        >
                            {{ confirmLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
