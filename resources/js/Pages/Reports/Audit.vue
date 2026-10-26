<script setup>
import ReportShell from '@/Components/ReportShell.vue';
import ReportPanel from '@/Components/ReportPanel.vue';
import Pagination from '@/Components/Pagination.vue';
import { ref } from 'vue';
import { selectClass, useReportFilters } from '@/composables/useReportFilters';

const props = defineProps({
    section: String,
    sections: Array,
    farms: Array,
    filters: Object,
    can: Object,
    entries: Object,
    subjects: Array,
    causers: Array,
});

const filters = useReportFilters('reports.audit', {
    subject: props.filters.subject ?? '',
    event: props.filters.event ?? '',
    causer_id: props.filters.causer_id ?? '',
});

const expanded = ref(new Set());

function toggle(id) {
    const next = new Set(expanded.value);
    next.has(id) ? next.delete(id) : next.add(id);
    expanded.value = next;
}

const eventClass = {
    created: 'bg-green-100 text-green-800',
    updated: 'bg-blue-100 text-blue-800',
    deleted: 'bg-red-100 text-red-800',
    downloaded: 'bg-purple-100 text-purple-800',
};

const show = (v) => (v === null || v === undefined || v === '' ? '—' : typeof v === 'object' ? JSON.stringify(v) : String(v));
const when = (d) => new Date(d).toLocaleString('en-KE', { dateStyle: 'medium', timeStyle: 'short' });
</script>

<template>
    <ReportShell :section="section" :sections="sections">
        <template #filters>
            <label class="text-xs font-medium text-gray-600">
                Record type
                <select v-model="filters.subject" :class="selectClass">
                    <option value="">All records</option>
                    <option v-for="s in subjects" :key="s" :value="s">{{ s }}</option>
                </select>
            </label>
            <label class="text-xs font-medium text-gray-600">
                Action
                <select v-model="filters.event" :class="selectClass">
                    <option value="">All actions</option>
                    <option value="created">Created</option>
                    <option value="updated">Updated</option>
                    <option value="deleted">Deleted</option>
                    <option value="downloaded">Downloaded</option>
                </select>
            </label>
            <label class="text-xs font-medium text-gray-600">
                Changed by
                <select v-model="filters.causer_id" :class="selectClass">
                    <option value="">Anyone</option>
                    <option v-for="c in causers" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </label>
        </template>

        <ReportPanel title="Change history" :subtitle="`${entries.total} entr${entries.total === 1 ? 'y' : 'ies'}`" flush>
            <ul class="divide-y divide-gray-100">
                <li v-for="e in entries.data" :key="e.id">
                    <button
                        type="button"
                        @click="toggle(e.id)"
                        :disabled="!e.changes.length"
                        class="flex w-full items-center gap-3 px-5 py-3 text-left hover:bg-gray-50 disabled:cursor-default"
                    >
                        <span :class="eventClass[e.event] ?? 'bg-gray-100 text-gray-700'" class="w-20 shrink-0 rounded-full px-2 py-0.5 text-center text-xs font-medium capitalize">
                            {{ e.event }}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="text-sm font-medium text-gray-900">{{ e.subject }} #{{ e.subjectId }}</span>
                            <span v-if="e.changes.length" class="ml-2 text-xs text-gray-500">
                                {{ e.changes.map((c) => c.field).join(', ') }}
                            </span>
                        </span>
                        <span class="hidden shrink-0 text-sm text-gray-600 sm:block">{{ e.causer ?? 'System' }}</span>
                        <span class="shrink-0 text-xs text-gray-500">{{ when(e.at) }}</span>
                        <svg
                            v-if="e.changes.length"
                            :class="expanded.has(e.id) ? 'rotate-180' : ''"
                            class="h-4 w-4 shrink-0 text-gray-400 transition-transform"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                        <span v-else class="w-4 shrink-0" />
                    </button>

                    <div v-if="expanded.has(e.id)" class="bg-gray-50 px-5 pb-4 pt-1">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-gray-500">
                                    <th class="py-1 pr-4 font-medium">Field</th>
                                    <th class="py-1 pr-4 font-medium">Before</th>
                                    <th class="py-1 font-medium">After</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="c in e.changes" :key="c.field" class="align-top">
                                    <td class="py-1 pr-4 font-mono text-xs text-gray-700">{{ c.field }}</td>
                                    <td class="py-1 pr-4 text-gray-500 line-through decoration-gray-300">{{ show(c.old) }}</td>
                                    <td class="py-1 text-gray-900">{{ show(c.new) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </li>
                <li v-if="!entries.data.length" class="px-5 py-8 text-center text-sm text-gray-500">
                    No changes match these filters.
                </li>
            </ul>
        </ReportPanel>

        <Pagination v-if="entries.links" :links="entries.links" />
    </ReportShell>
</template>
