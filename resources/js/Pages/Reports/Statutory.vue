<script setup>
import ReportShell from '@/Components/ReportShell.vue';
import ReportPanel from '@/Components/ReportPanel.vue';
import ExportMenu from '@/Components/ExportMenu.vue';
import DataTable from '@/Components/DataTable.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { computed } from 'vue';
import { money, selectClass, shortDate, useReportFilters } from '@/composables/useReportFilters';

const props = defineProps({
    section: String,
    sections: Array,
    farms: Array,
    filters: Object,
    can: Object,
    runs: Array,
    selected: Object,
    remittances: Array,
    byFarm: Array,
    dueDate: String,
});

const filters = useReportFilters('reports.statutory', {
    farm_id: props.filters.farm_id ?? '',
    run_id: props.filters.run_id ?? '',
});

const total = computed(() => props.remittances.reduce((t, r) => t + r.employee + r.employer, 0));

const farmTotals = computed(() => {
    const keys = ['headcount', 'paye', 'nssf', 'shif', 'ahl', 'nita'];
    return Object.fromEntries(keys.map((k) => [k, props.byFarm.reduce((t, r) => t + r[k], 0)]));
});

const exports = computed(() =>
    props.selected && props.can.exportPayroll
        ? [
              { label: 'Payroll register (Excel)', hint: 'Per-employee PAYE, NSSF, SHIF and AHL', href: route('reports.export.payroll-register', { run: props.selected.id, farm_id: filters.farm_id || undefined }) },
              { label: 'Payroll register (CSV)', href: route('reports.export.payroll-register', { run: props.selected.id, farm_id: filters.farm_id || undefined, format: 'csv' }) },
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
            <div
                v-if="selected.status === 'draft' || selected.status === 'calculated'"
                class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900"
            >
                {{ selected.period_label }} is not approved yet — these figures can still change.
            </div>

            <ReportPanel
                title="Remittances due"
                :subtitle="`${selected.period_label} · due by ${shortDate(dueDate)}`"
                flush
            >
                <template #aside><StatusBadge :status="selected.status" /></template>
                <DataTable :columns="['Item', 'Pay to', 'Employee share', 'Employer share', 'Total']">
                    <tr v-for="r in remittances" :key="r.label">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ r.label }}</td>
                        <td class="px-4 py-3 text-sm text-gray-600">{{ r.body }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ money(r.employee) }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ money(r.employer) }}</td>
                        <td class="px-4 py-3 text-sm font-medium tabular-nums text-gray-900">{{ money(r.employee + r.employer) }}</td>
                    </tr>
                    <tr class="bg-gray-50">
                        <td colspan="4" class="px-4 py-3 text-sm font-semibold text-gray-900">Total to remit</td>
                        <td class="px-4 py-3 text-sm font-semibold tabular-nums text-[#1B5E20]">{{ money(total) }}</td>
                    </tr>
                </DataTable>
            </ReportPanel>

            <ReportPanel title="By farm" subtitle="Employee and employer shares combined" flush>
                <DataTable :columns="['Farm', 'Employees', 'PAYE', 'NSSF', 'SHIF', 'Housing Levy', 'NITA']" empty="No payslips for this run.">
                    <tr v-for="r in byFarm" :key="r.farm" class="hover:bg-gray-50">
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ r.farm }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ r.headcount }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ money(r.paye) }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ money(r.nssf) }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ money(r.shif) }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ money(r.ahl) }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ money(r.nita) }}</td>
                    </tr>
                    <tr v-if="byFarm.length > 1" class="bg-gray-50 font-semibold">
                        <td class="px-4 py-3 text-sm text-gray-900">Total</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ farmTotals.headcount }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ money(farmTotals.paye) }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ money(farmTotals.nssf) }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ money(farmTotals.shif) }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ money(farmTotals.ahl) }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums">{{ money(farmTotals.nita) }}</td>
                    </tr>
                </DataTable>
            </ReportPanel>
        </template>
    </ReportShell>
</template>
