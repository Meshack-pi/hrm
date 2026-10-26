<script setup>
import ReportShell from '@/Components/ReportShell.vue';
import ReportPanel from '@/Components/ReportPanel.vue';
import ExportMenu from '@/Components/ExportMenu.vue';
import DataTable from '@/Components/DataTable.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { money, selectClass, useReportFilters, whole } from '@/composables/useReportFilters';

const props = defineProps({
    section: String,
    sections: Array,
    farms: Array,
    filters: Object,
    can: Object,
    runs: Array,
    selected: Object,
    totals: Object,
    previous: Object,
    byFarm: Array,
    byDepartment: Array,
    paymentStatus: Array,
});

const filters = useReportFilters('reports.payroll', {
    farm_id: props.filters.farm_id ?? '',
    run_id: props.filters.run_id ?? '',
});

const groupBy = ref('farm');
const groupRows = computed(() => (groupBy.value === 'farm' ? props.byFarm : props.byDepartment));

const exports = computed(() =>
    props.selected && props.can.exportPayroll
        ? [
              { label: 'Payroll register (Excel)', hint: `Every payslip line for ${props.selected.period_label}`, href: route('reports.export.payroll-register', { run: props.selected.id, farm_id: filters.farm_id || undefined }) },
              { label: 'Payroll register (CSV)', href: route('reports.export.payroll-register', { run: props.selected.id, farm_id: filters.farm_id || undefined, format: 'csv' }) },
          ]
        : []
);

function change(key) {
    if (!props.previous || !props.previous[key]) return null;
    const diff = props.totals[key] - props.previous[key];
    return { diff, pct: (diff / props.previous[key]) * 100 };
}

const tiles = computed(() =>
    props.totals
        ? [
              { key: 'headcount', label: 'Paid employees', value: whole(props.totals.headcount), fmt: whole },
              { key: 'gross', label: 'Gross pay', value: money(props.totals.gross), fmt: money },
              { key: 'net', label: 'Net pay', value: money(props.totals.net), fmt: money, emphasis: true },
              { key: 'employer_cost', label: 'Employer cost', value: money(props.totals.employer_cost), fmt: money },
          ]
        : []
);
</script>

<template>
    <ReportShell :section="section" :sections="sections">
        <template #filters>
            <label class="text-xs font-medium text-gray-600">
                Payroll month
                <select v-model="filters.run_id" :class="selectClass" :disabled="!runs.length">
                    <option v-for="r in runs" :key="r.id" :value="r.id">{{ r.period_label }} · {{ r.status.replace('_', ' ') }}</option>
                </select>
            </label>
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

        <div v-if="!selected" class="rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center text-sm text-gray-500">
            No payroll runs yet.
        </div>

        <template v-else>
            <div class="flex flex-wrap items-center gap-2 text-sm text-gray-600">
                <StatusBadge :status="selected.status" />
                <span>{{ selected.period_label }}</span>
                <span v-if="previous">· compared with {{ previous.period }}</span>
                <Link :href="route('payroll.runs.show', selected.id)" class="ml-auto font-medium text-[#1B5E20] hover:underline">
                    Open run →
                </Link>
            </div>

            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div v-for="t in tiles" :key="t.key" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ t.label }}</p>
                    <p :class="t.emphasis ? 'text-[#1B5E20]' : 'text-gray-900'" class="mt-1 text-xl font-semibold tabular-nums">{{ t.value }}</p>
                    <p v-if="change(t.key)" class="mt-0.5 text-xs tabular-nums" :class="change(t.key).diff >= 0 ? 'text-gray-600' : 'text-amber-700'">
                        {{ change(t.key).diff >= 0 ? '▲' : '▼' }} {{ t.fmt(Math.abs(change(t.key).diff)) }}
                        ({{ change(t.key).pct.toFixed(1) }}%)
                    </p>
                    <p v-else class="mt-0.5 text-xs text-gray-400">No previous month</p>
                </div>
            </div>

            <ReportPanel title="Pay breakdown" :subtitle="`Grouped by ${groupBy}`" flush>
                <template #aside>
                    <label class="flex items-center gap-2 text-xs font-medium text-gray-600">
                        Group by
                        <select v-model="groupBy" class="rounded-md border-gray-300 py-1 text-sm shadow-sm focus:border-[#4CAF50] focus:ring-[#4CAF50]">
                            <option value="farm">Farm</option>
                            <option value="department">Department</option>
                        </select>
                    </label>
                </template>
                <DataTable
                    :columns="[groupBy === 'farm' ? 'Farm' : 'Department', 'Employees', 'Gross', 'Deductions', 'Net pay', 'Employer cost']"
                    empty="This run has no payslips yet. Calculate it first."
                >
                    <tr v-for="r in groupRows" :key="r.name" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ r.name }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ r.headcount }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ money(r.gross) }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ money(r.deductions) }}</td>
                        <td class="px-4 py-3 text-sm font-medium tabular-nums text-gray-900">{{ money(r.net) }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ money(r.employer_cost) }}</td>
                    </tr>
                    <tr v-if="totals && groupRows.length > 1" class="bg-gray-50 font-semibold">
                        <td class="px-4 py-3 text-sm text-gray-900">Total</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ totals.headcount }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ money(totals.gross) }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ money(totals.deductions) }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums text-[#1B5E20]">{{ money(totals.net) }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ money(totals.employer_cost) }}</td>
                    </tr>
                </DataTable>
            </ReportPanel>

            <ReportPanel title="Payment status" subtitle="Net pay by payment outcome">
                <div v-if="paymentStatus.length" class="grid gap-4 sm:grid-cols-3">
                    <div v-for="p in paymentStatus" :key="p.status" class="rounded-lg bg-gray-50 p-4">
                        <StatusBadge :status="p.status" />
                        <p class="mt-2 text-lg font-semibold tabular-nums text-gray-900">KES {{ money(p.amount) }}</p>
                        <p class="text-xs text-gray-500">{{ p.count }} payslip(s)</p>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-500">No payslips yet.</p>
            </ReportPanel>
        </template>
    </ReportShell>
</template>
