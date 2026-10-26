<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Pagination from '@/Components/Pagination.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { usePermissions } from '@/composables/usePermissions';
import { useFilters } from '@/composables/useFilters';
import { ref } from 'vue';

const props = defineProps({
    users: { type: Object, required: true },
    roles: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const { can } = usePermissions();

const { filters, reset } = useFilters('users.index', {
    search: props.filters.search ?? '',
    role: props.filters.role ?? '',
    active: props.filters.active ?? '',
});

const deleting = ref(null);

function confirmDelete() {
    router.delete(route('users.destroy', deleting.value.id), {
        onFinish: () => (deleting.value = null),
    });
}

const columns = [
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Email' },
    { key: 'phone', label: 'Phone' },
    { key: 'roles', label: 'Roles' },
    { key: 'status', label: 'Status' },
    { key: 'actions', label: '' },
];
</script>

<template>
    <Head title="Team Members" />

    <AppLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-gray-900">Team Members</h1>
        </template>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-gray-600">
                {{ users.total }} team member{{ users.total === 1 ? '' : 's' }} with system access
            </p>
            <Link
                v-if="can('users.create')"
                :href="route('users.create')"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#1B5E20] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#2E7D32]"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Add Team Member
            </Link>
        </div>

        <!-- Filters -->
        <div class="mt-4 grid gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <label class="block text-xs font-medium text-gray-600">Search</label>
                <input
                    v-model="filters.search"
                    type="search"
                    placeholder="Name or email…"
                    class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-[#4CAF50] focus:ring-[#4CAF50]"
                />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">Role</label>
                <select v-model="filters.role" class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-[#4CAF50] focus:ring-[#4CAF50]">
                    <option value="">All roles</option>
                    <option v-for="r in roles" :key="r.id" :value="r.name">{{ r.name }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600">Status</label>
                <select v-model="filters.active" class="mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-[#4CAF50] focus:ring-[#4CAF50]">
                    <option value="">All</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div class="sm:col-span-2 lg:col-span-4">
                <button type="button" @click="reset" class="text-sm font-medium text-[#1B5E20] hover:underline">
                    Reset filters
                </button>
            </div>
        </div>

        <!-- Table -->
        <div class="mt-4">
            <DataTable :columns="columns" empty="No team members match your filters.">
                <tr v-for="u in users.data" :key="u.id" class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#1B5E20] text-xs font-bold text-white">
                                {{ u.name.charAt(0).toUpperCase() }}
                            </div>
                            <span class="text-sm font-medium text-gray-900">{{ u.name }}</span>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ u.email }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ u.phone || '—' }}</td>
                    <td class="px-4 py-3">
                        <div class="flex flex-wrap gap-1">
                            <span
                                v-for="r in u.roles"
                                :key="r.id"
                                class="inline-flex rounded-full bg-green-50 px-2 py-1 text-xs font-medium text-[#1B5E20]"
                            >
                                {{ r.name }}
                            </span>
                            <span v-if="!u.roles?.length" class="text-xs text-gray-400">No role</span>
                        </div>
                    </td>
                    <td class="px-4 py-3">
                        <span
                            :class="u.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700'"
                            class="inline-flex rounded-full px-2 py-1 text-xs font-medium"
                        >
                            {{ u.is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-3">
                            <Link
                                v-if="can('users.edit')"
                                :href="route('users.edit', u.id)"
                                class="text-sm font-medium text-[#1B5E20] hover:underline"
                            >
                                Edit
                            </Link>
                            <button
                                v-if="can('users.delete')"
                                type="button"
                                @click="deleting = u"
                                class="text-sm font-medium text-red-600 hover:underline"
                            >
                                Delete
                            </button>
                        </div>
                    </td>
                </tr>
            </DataTable>

            <Pagination :links="users.links" />
        </div>

        <ConfirmModal
            :show="!!deleting"
            title="Remove team member"
            :message="`This will permanently remove ${deleting?.name}'s access to the system.`"
            confirm-label="Delete"
            @confirm="confirmDelete"
            @cancel="deleting = null"
        />
    </AppLayout>
</template>
