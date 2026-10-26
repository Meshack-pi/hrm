<script setup>
import ReportShell from '@/Components/ReportShell.vue';
import ReportPanel from '@/Components/ReportPanel.vue';
import DataTable from '@/Components/DataTable.vue';
import { computed, ref } from 'vue';
import { selectClass, shortDate, useReportFilters, whole } from '@/composables/useReportFilters';

const props = defineProps({
    section: String,
    sections: Array,
    farms: Array,
    filters: Object,
    can: Object,
    year: Number,
    summary: Object,
    byType: Array,
    byFarm: Array,
    onLeave: Array,
});

const filters = useReportFilters('reports.leave', {
    farm_id: props.filters.farm_id ?? '',
    year: props.filters.year,
});

const thisYear = new Date().getFullYear();
const years = [thisYear, thisYear - 1, thisYear - 2];

const groupBy = ref('type');
const rows = computed(() =>
    groupBy.value === 'type'
        ? props.byType.map((r) => ({ ...r, name: r.type }))
        : props.byFarm.map((r) => ({ ...r, name: r.farm }))
);

const tiles = computed(() => [
    { label: 'Days taken', value: props.summary.daysTaken },
    { label: 'Approved requests', value: props.summary.approved },
    { label: 'Pending requests', value: props.summary.pending },
    { label: 'On leave today', value: props.summary.onLeaveToday },
]);
</script>

<template>
    <ReportShell :section="section" :sections="sections">
        <template #filters>
            <label class="text-xs font-medium text-gray-600">
                Year
                <select v-model="filters.year" :class="selectClass">
                    <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
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

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div v-for="t in tiles" :key="t.label" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ t.label }}</p>
                <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">{{ whole(t.value) }}</p>
                <p class="text-xs text-gray-500">{{ year }}</p>
            </div>
        </div>

        <ReportPanel title="Leave activity" :subtitle="`Grouped by ${groupBy === 'type' ? 'leave type' : 'farm'}`" flush>
            <template #aside>
                <label class="flex items-center gap-2 text-xs font-medium text-gray-600">
                    Group by
                    <select v-model="groupBy" class="rounded-md border-gray-300 py-1 text-sm shadow-sm focus:border-[#4CAF50] focus:ring-[#4CAF50]">
                        <option value="type">Leave type</option>
                        <option value="farm">Farm</option>
                    </select>
                </label>
            </template>
            <DataTable
                :columns="[groupBy === 'type' ? 'Leave type' : 'Farm', 'Days taken', 'Approved', 'Pending', 'Rejected']"
                :empty="`No leave requests in ${year}.`"
            >
                <tr v-for="r in rows" :key="r.name" class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ r.name }}</td>
                    <td class="px-4 py-3 text-sm font-semibold tabular-nums text-gray-900">{{ r.days_taken }}</td>
                    <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ r.approved }}</td>
                    <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ r.pending }}</td>
                    <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ r.rejected }}</td>
                </tr>
            </DataTable>
        </ReportPanel>

        <ReportPanel title="On leave today" flush>
            <DataTable :columns="['Employee', 'Farm', 'Leave type', 'From', 'To']" empty="Nobody is on leave today.">
                <tr v-for="r in onLeave" :key="r.id">
                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ r.employee }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ r.farm ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ r.type }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ shortDate(r.from) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ shortDate(r.to) }}</td>
                </tr>
            </DataTable>
        </ReportPanel>
    </ReportShell>
</template>
