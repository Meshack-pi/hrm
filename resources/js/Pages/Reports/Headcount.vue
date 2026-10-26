<script setup>
import ReportShell from '@/Components/ReportShell.vue';
import ReportPanel from '@/Components/ReportPanel.vue';
import ExportMenu from '@/Components/ExportMenu.vue';
import DataTable from '@/Components/DataTable.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import PieChart3D from '@/Components/PieChart3D.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { selectClass, shortDate, useReportFilters, whole } from '@/composables/useReportFilters';

const props = defineProps({
    section: String,
    sections: Array,
    farms: Array,
    filters: Object,
    can: Object,
    summary: Object,
    byFarm: Array,
    byDepartment: Array,
    recentHires: Array,
});

const filters = useReportFilters('reports.headcount', { farm_id: props.filters.farm_id ?? '' });

const exports = computed(() =>
    props.can.exportEmployees
        ? [
              { label: 'Employee list (Excel)', hint: 'All employees in the current farm filter', href: route('reports.export.employees', { farm_id: filters.farm_id || undefined }) },
              { label: 'Employee list (CSV)', href: route('reports.export.employees', { farm_id: filters.farm_id || undefined, format: 'csv' }) },
          ]
        : []
);

const departmentSlices = computed(() => props.byDepartment.map((d) => ({ label: d.department, value: d.total })));

const tiles = computed(() => [
    { label: 'Employees', value: props.summary.total, sub: `${whole(props.summary.active)} active` },
    { label: 'On leave', value: props.summary.onLeave, sub: 'status today' },
    { label: 'New hires', value: props.summary.newHires, sub: 'last 30 days' },
    { label: 'Contracts ending', value: props.summary.contractsEnding, sub: 'next 60 days' },
]);
</script>

<template>
    <ReportShell :section="section" :sections="sections">
        <template #filters>
            <label class="text-xs font-medium text-gray-600">
                Farm
                <select v-model="filters.farm_id" :class="selectClass">
                    <option value="">All farms</option>
                    <option v-for="f in farms" :key="f.id" :value="f.id">{{ f.name }}</option>
                </select>
            </label>
        </template>
        <template #actions>
            <ExportMenu :items="exports" />
        </template>

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div v-for="t in tiles" :key="t.label" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ t.label }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">{{ whole(t.value) }}</p>
                <p class="text-xs text-gray-500">{{ t.sub }}</p>
            </div>
        </div>

        <ReportPanel title="By farm" subtitle="Status and contract type for each farm" flush>
            <DataTable
                :columns="['Farm', 'Total', 'Active', 'On leave', 'Suspended', 'Inactive', 'Permanent', 'Contract', 'Casual']"
                empty="No employees in this filter."
            >
                <tr v-for="r in byFarm" :key="r.farm" class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ r.farm }}</td>
                    <td class="px-4 py-3 text-sm font-semibold tabular-nums text-gray-900">{{ r.total }}</td>
                    <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ r.active }}</td>
                    <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ r.on_leave }}</td>
                    <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ r.suspended }}</td>
                    <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ r.inactive }}</td>
                    <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ r.permanent }}</td>
                    <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ r.contract }}</td>
                    <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ r.casual }}</td>
                </tr>
            </DataTable>
        </ReportPanel>

        <div class="grid gap-6 lg:grid-cols-2">
            <ReportPanel title="By department" subtitle="Share of headcount · hover a slice for details">
                <PieChart3D :items="departmentSlices" unit="employee" />
            </ReportPanel>

            <ReportPanel title="Recent hires" subtitle="Joined in the last 30 days" flush>
                <ul class="divide-y divide-gray-100">
                    <li v-for="h in recentHires" :key="h.id">
                        <Link :href="route('employees.show', h.uuid)" class="flex items-center justify-between gap-3 px-5 py-3 hover:bg-gray-50">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">{{ h.name }}</p>
                                <p class="truncate text-xs text-gray-500">{{ h.title }} · {{ h.farm }}</p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <StatusBadge :status="h.type" size="xs" />
                                <span class="text-xs text-gray-500">{{ shortDate(h.hired) }}</span>
                            </div>
                        </Link>
                    </li>
                    <li v-if="!recentHires.length" class="px-5 py-4 text-sm text-gray-500">No new hires in the last 30 days.</li>
                </ul>
            </ReportPanel>
        </div>
    </ReportShell>
</template>
