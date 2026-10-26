<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Pagination from '@/Components/Pagination.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EditEmployeeModal from './Partials/EditEmployeeModal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { usePermissions } from '@/composables/usePermissions';
import { useFilters } from '@/composables/useFilters';

const props = defineProps({
    employees: { type: Object, required: true },
    farms: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    // Optional prop: only fetched for the employee being edited.
    editForm: { type: Object, default: null },
});

const { can } = usePermissions();

const editing = ref(false);
const loadingEditFor = ref(null);
const editKey = ref(0);

// preserveUrl keeps the uuid out of the address bar while it's sent to the server.
function openEdit(employee) {
    router.reload({
        only: ['editForm'],
        data: { edit: employee.uuid },
        preserveUrl: true,
        onStart: () => (loadingEditFor.value = employee.uuid),
        onFinish: () => (loadingEditFor.value = null),
        onSuccess: () => {
            editKey.value++;
            editing.value = true;
        },
    });
}

const { filters, reset } = useFilters('employees.index', {
    search: props.filters.search ?? '',
    farm_id: props.filters.farm_id ?? '',
    department_id: props.filters.department_id ?? '',
    status: props.filters.status ?? '',
    employment_type: props.filters.employment_type ?? '',
});

const selectClass =
    'mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-[#4CAF50] focus:ring-[#4CAF50]';

const columns = [
    { key: 'employee', label: 'Employee' },
    { key: 'number', label: 'Staff No.' },
    { key: 'farm', label: 'Farm' },
    { key: 'department', label: 'Department' },
    { key: 'type', label: 'Type' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' },
];

function fullName(e) {
    return [e.first_name, e.middle_name, e.last_name].filter(Boolean).join(' ');
}
</script>

<template>
    <Head title="Employees" />

    <AppLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-gray-900">Employees</h1>
        </template>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-gray-600">
                {{ employees.total }} employee{{ employees.total === 1 ? '' : 's' }} across all farms
            </p>
            <Link
                v-if="can('employees.create')"
                :href="route('employees.create')"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#1B5E20] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#2E7D32]"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Add Employee
            </Link>
        </div>

        <!-- Filters -->
        <div class="mt-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="lg:col-span-2">
                    <label class="block text-xs font-medium text-gray-600">Search</label>
                    <input
                        v-model="filters.search"
                        type="search"
                        placeholder="Name, staff no. or ID…"
                        :class="selectClass"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">Farm</label>
                    <select v-model="filters.farm_id" :class="selectClass">
                        <option value="">All farms</option>
                        <option v-for="f in farms" :key="f.id" :value="f.id">{{ f.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">Department</label>
                    <select v-model="filters.department_id" :class="selectClass">
                        <option value="">All departments</option>
                        <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">Status</label>
                    <select v-model="filters.status" :class="selectClass">
                        <option value="">All statuses</option>
                        <option value="active">Active</option>
                        <option value="on_leave">On Leave</option>
                        <option value="suspended">Suspended</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">Employment type</label>
                    <select v-model="filters.employment_type" :class="selectClass">
                        <option value="">All types</option>
                        <option value="permanent">Permanent</option>
                        <option value="contract">Contract</option>
                        <option value="casual">Casual</option>
                        <option value="intern">Intern</option>
                    </select>
                </div>
            </div>
            <div class="mt-3">
                <button type="button" @click="reset" class="text-sm font-medium text-[#1B5E20] hover:underline">
                    Reset filters
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="mt-4">
            <DataTable :columns="columns" empty="No employees match your filters.">
                <tr v-for="e in employees.data" :key="e.id" class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-green-100 text-sm font-bold text-[#1B5E20]">
                                {{ e.first_name.charAt(0).toUpperCase() }}
                            </div>
                            <div class="min-w-0">
                                <Link :href="route('employees.show', e.uuid)" class="block truncate text-sm font-medium text-gray-900 hover:text-[#1B5E20] hover:underline">
                                    {{ fullName(e) }}
                                </Link>
                                <span class="block truncate text-xs text-gray-500">
                                    {{ e.job_title?.name || 'No job title' }}
                                </span>
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 font-mono text-sm text-gray-600">{{ e.employee_number }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ e.farm?.name || '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ e.department?.name || '—' }}</td>
                    <td class="px-4 py-3">
                        <StatusBadge :status="e.employment_type" />
                    </td>
                    <td class="px-4 py-3">
                        <StatusBadge :status="e.status" />
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-3">
                            <Link :href="route('employees.show', e.uuid)" class="text-sm font-medium text-[#1B5E20] hover:underline">
                                View
                            </Link>
                            <button
                                v-if="can('employees.edit')"
                                type="button"
                                @click="openEdit(e)"
                                :disabled="loadingEditFor === e.uuid"
                                class="text-sm font-medium text-gray-600 hover:underline disabled:opacity-50"
                            >
                                {{ loadingEditFor === e.uuid ? 'Loading…' : 'Edit' }}
                            </button>
                        </div>
                    </td>
                </tr>
            </DataTable>

            <Pagination :links="employees.links" />
        </div>

        <EditEmployeeModal
            v-if="editForm"
            :key="editKey"
            :show="editing"
            :edit-form="editForm"
            @close="editing = false"
        />
    </AppLayout>
</template>
