<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    departments: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
});

const showModal = ref(false);
const editing = ref(null);
const deleting = ref(null);

const form = useForm({
    name: '',
    code: '',
    head_employee_id: '',
});

const field = 'mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-[#4CAF50] focus:ring-[#4CAF50]';

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
}

function openEdit(dept) {
    editing.value = dept;
    form.clearErrors();
    form.name = dept.name;
    form.code = dept.code;
    form.head_employee_id = dept.head_employee_id ?? '';
    showModal.value = true;
}

function close() {
    showModal.value = false;
    editing.value = null;
}

function submit() {
    const options = { onSuccess: close, preserveScroll: true };

    if (editing.value) {
        form.put(route('settings.departments.update', editing.value.id), options);
    } else {
        form.post(route('settings.departments.store'), options);
    }
}

function confirmDelete() {
    router.delete(route('settings.departments.destroy', deleting.value.id), {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}

const columns = [
    { key: 'name', label: 'Department' },
    { key: 'code', label: 'Code' },
    { key: 'head', label: 'Head' },
    { key: 'employees', label: 'Employees' },
    { key: 'actions', label: '' },
];
</script>

<template>
    <Head title="Departments" />

    <AppLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-gray-900">Departments</h1>
        </template>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-gray-600">
                Departments group employees by function across all farms.
            </p>
            <button
                type="button"
                @click="openCreate"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#1B5E20] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#2E7D32]"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Add Department
            </button>
        </div>

        <div class="mt-4">
            <DataTable :columns="columns" empty="No departments yet. Add your first department to get started.">
                <tr v-for="d in departments" :key="d.id" class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ d.name }}</td>
                    <td class="px-4 py-3">
                        <span class="rounded-md bg-gray-100 px-2 py-1 font-mono text-xs text-gray-700">{{ d.code }}</span>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        {{ d.head ? `${d.head.first_name} ${d.head.last_name}` : '—' }}
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ d.employees_count ?? 0 }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-3">
                            <button type="button" @click="openEdit(d)" class="text-sm font-medium text-[#1B5E20] hover:underline">
                                Edit
                            </button>
                            <button type="button" @click="deleting = d" class="text-sm font-medium text-red-600 hover:underline">
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
                            {{ editing ? 'Edit department' : 'Add department' }}
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
                                    placeholder="e.g. OPS"
                                    class="mt-1 w-full rounded-lg border-gray-300 text-sm uppercase shadow-sm focus:border-[#4CAF50] focus:ring-[#4CAF50]"
                                    required
                                />
                                <p v-if="form.errors.code" class="mt-1 text-sm text-red-600">{{ form.errors.code }}</p>
                                <p v-else class="mt-1 text-xs text-gray-500">Short unique identifier, up to 10 characters.</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Department head</label>
                                <select v-model="form.head_employee_id" :class="field">
                                    <option value="">None</option>
                                    <option v-for="e in employees" :key="e.id" :value="e.id">
                                        {{ e.first_name }} {{ e.last_name }} ({{ e.employee_number }})
                                    </option>
                                </select>
                                <p v-if="form.errors.head_employee_id" class="mt-1 text-sm text-red-600">
                                    {{ form.errors.head_employee_id }}
                                </p>
                            </div>

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
                                    {{ form.processing ? 'Saving…' : editing ? 'Save Changes' : 'Add Department' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <ConfirmModal
            :show="!!deleting"
            title="Delete department"
            :message="`Delete ${deleting?.name}? Departments with employees assigned cannot be deleted.`"
            confirm-label="Delete"
            @confirm="confirmDelete"
            @cancel="deleting = null"
        />
    </AppLayout>
</template>
