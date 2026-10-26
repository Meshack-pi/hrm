<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    farms: { type: Array, default: () => [] },
});

const showModal = ref(false);
const editing = ref(null);
const deleting = ref(null);

const form = useForm({
    name: '',
    code: '',
    location: '',
    county: '',
    is_active: true,
});

const field = 'mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-[#4CAF50] focus:ring-[#4CAF50]';

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
}

function openEdit(farm) {
    editing.value = farm;
    form.clearErrors();
    form.name = farm.name;
    form.code = farm.code;
    form.location = farm.location ?? '';
    form.county = farm.county ?? '';
    form.is_active = !!farm.is_active;
    showModal.value = true;
}

function close() {
    showModal.value = false;
    editing.value = null;
}

function submit() {
    const options = { onSuccess: close, preserveScroll: true };

    if (editing.value) {
        form.put(route('settings.farms.update', editing.value.id), options);
    } else {
        form.post(route('settings.farms.store'), options);
    }
}

function confirmDelete() {
    router.delete(route('settings.farms.destroy', deleting.value.id), {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}

const columns = [
    { key: 'name', label: 'Farm' },
    { key: 'code', label: 'Code' },
    { key: 'location', label: 'Location' },
    { key: 'county', label: 'County' },
    { key: 'employees', label: 'Employees' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' },
];
</script>

<template>
    <Head title="Farms" />

    <AppLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-gray-900">Farms</h1>
        </template>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-gray-600">
                Farm locations across the company. Employees are assigned to a farm.
            </p>
            <button
                type="button"
                @click="openCreate"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#1B5E20] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#2E7D32]"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Add Farm
            </button>
        </div>

        <div class="mt-4">
            <DataTable :columns="columns" empty="No farms yet. Add your first farm to get started.">
                <tr v-for="f in farms" :key="f.id" class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ f.name }}</td>
                    <td class="px-4 py-3">
                        <span class="rounded-md bg-gray-100 px-2 py-1 font-mono text-xs text-gray-700">{{ f.code }}</span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ f.location || '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ f.county || '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ f.employees_count ?? 0 }}</td>
                    <td class="px-4 py-3">
                        <span
                            :class="f.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'"
                            class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                        >
                            {{ f.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-3">
                            <button type="button" @click="openEdit(f)" class="text-sm font-medium text-[#1B5E20] hover:underline">
                                Edit
                            </button>
                            <button type="button" @click="deleting = f" class="text-sm font-medium text-red-600 hover:underline">
                                Delete
                            </button>
                        </div>
                    </td>
                </tr>
            </DataTable>
        </div>

        <!-- Create / edit modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/50" @click="close" />
                    <div class="relative w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
                        <h3 class="text-lg font-semibold text-gray-900">
                            {{ editing ? 'Edit farm' : 'Add farm' }}
                        </h3>

                        <form @submit.prevent="submit" class="mt-5 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Name <span class="text-red-500">*</span></label>
                                <input v-model="form.name" type="text" :class="field" required />
                                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Code <span class="text-red-500">*</span></label>
                                <input
                                    v-model="form.code"
                                    type="text"
                                    maxlength="10"
                                    placeholder="e.g. KOS"
                                    class="mt-1 w-full rounded-lg border-gray-300 text-sm uppercase shadow-sm focus:border-[#4CAF50] focus:ring-[#4CAF50]"
                                    required
                                />
                                <p v-if="form.errors.code" class="mt-1 text-sm text-red-600">{{ form.errors.code }}</p>
                                <p v-else class="mt-1 text-xs text-gray-500">Short unique identifier, up to 10 characters.</p>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Location</label>
                                    <input v-model="form.location" type="text" :class="field" />
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">County</label>
                                    <input v-model="form.county" type="text" :class="field" />
                                </div>
                            </div>

                            <label v-if="editing" class="inline-flex items-center gap-2">
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="rounded border-gray-300 text-[#1B5E20] shadow-sm focus:ring-[#4CAF50]"
                                />
                                <span class="text-sm text-gray-700">Farm is active</span>
                            </label>

                            <div class="flex justify-end gap-3 pt-2">
                                <button
                                    type="button"
                                    @click="close"
                                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="rounded-lg bg-[#1B5E20] px-5 py-2 text-sm font-medium text-white transition hover:bg-[#2E7D32] disabled:opacity-50"
                                >
                                    {{ form.processing ? 'Saving…' : editing ? 'Save Changes' : 'Add Farm' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <ConfirmModal
            :show="!!deleting"
            title="Delete farm"
            :message="`Delete ${deleting?.name}? Farms with employees assigned cannot be deleted.`"
            confirm-label="Delete"
            @confirm="confirmDelete"
            @cancel="deleting = null"
        />
    </AppLayout>
</template>
