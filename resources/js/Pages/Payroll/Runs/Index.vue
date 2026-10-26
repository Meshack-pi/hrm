<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import { usePermissions } from '@/composables/usePermissions';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    runs: { type: Object, required: true },
});

const { can } = usePermissions();

const months = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

function period(run) {
    return `${months[run.period_month - 1]} ${run.period_year}`;
}

function money(value) {
    return Number(value ?? 0).toLocaleString('en-KE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

const columns = [
    { key: 'period', label: 'Period' },
    { key: 'status', label: 'Status' },
    { key: 'employees', label: 'Employees' },
    { key: 'gross', label: 'Gross' },
    { key: 'net', label: 'Net' },
    { key: 'prepared', label: 'Prepared by' },
    { key: 'approved', label: 'Approved by' },
    { key: 'actions', label: '' },
];
</script>

<template>
    <Head title="Payroll Runs" />

    <AppLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-gray-900">Payroll Runs</h1>
        </template>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-gray-600">
                One run per month. A run must be approved by someone other than whoever calculated it.
            </p>
            <Link
                v-if="can('payroll.create')"
                :href="route('payroll.runs.create')"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#1B5E20] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#2E7D32]"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                New Payroll Run
            </Link>
        </div>

        <div class="mt-4">
            <DataTable :columns="columns" empty="No payroll runs yet.">
                <tr v-for="run in runs.data" :key="run.id" class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">
                        {{ period(run) }}
                    </td>
                    <td class="px-4 py-3">
                        <StatusBadge :status="run.status" />
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ run.payslips_count ?? 0 }}</td>
                    <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ money(run.total_gross) }}</td>
                    <td class="px-4 py-3 text-sm font-medium tabular-nums text-gray-900">{{ money(run.total_net) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ run.calculated_by_user?.name ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ run.approved_by_user?.name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        <Link
                            :href="route('payroll.runs.show', run.id)"
                            class="text-sm font-medium text-[#1B5E20] hover:underline"
                        >
                            Open
                        </Link>
                    </td>
                </tr>
            </DataTable>

            <Pagination v-if="runs.links" :links="runs.links" class="mt-4" />
        </div>
    </AppLayout>
</template>
