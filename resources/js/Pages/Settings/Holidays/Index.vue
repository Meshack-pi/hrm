<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({
    year: { type: Number, required: true },
    years: { type: Array, required: true },
    holidays: { type: Array, default: () => [] },
    lastSyncedAt: { type: String, default: null },
    apiUrl: { type: String, default: '' },
});

const year = ref(props.year);
watch(year, (y) => router.get(route('settings.holidays.index'), { year: y }, { preserveState: true, replace: true }));

const syncing = ref(false);
function syncNow() {
    router.post(route('settings.holidays.sync'), {}, {
        preserveScroll: true,
        onStart: () => (syncing.value = true),
        onFinish: () => (syncing.value = false),
    });
}

function toggle(h) {
    router.patch(route('settings.holidays.update', h.id), { is_active: !h.isActive }, { preserveScroll: true });
}

const deleting = ref(null);
function confirmDelete() {
    router.delete(route('settings.holidays.destroy', deleting.value.id), {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}

const showAdd = ref(false);
const form = useForm({ date: '', name: '' });
function submit() {
    form.post(route('settings.holidays.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showAdd.value = false;
            form.reset();
        },
    });
}

const fmtDate = (s) => new Date(`${s}T00:00:00`).toLocaleDateString('en-KE', { day: 'numeric', month: 'short', year: 'numeric' });
const fmtSynced = (s) => new Date(s).toLocaleString('en-KE', { dateStyle: 'medium', timeStyle: 'short' });

const sourceBadge = {
    api: ['Synced', 'bg-sky-100 text-sky-800'],
    system: ['Built-in', 'bg-gray-100 text-gray-700'],
    manual: ['Added by HR', 'bg-green-100 text-green-800'],
};

const field = 'mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-[#4CAF50] focus:ring-[#4CAF50]';
</script>

<template>
    <Head title="Public Holidays" />

    <AppLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-gray-900">Public Holidays</h1>
        </template>

        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm lg:col-span-2">
                <h2 class="text-sm font-semibold text-gray-900">How holidays are kept up to date</h2>
                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-gray-600">
                    <li>Every Monday the system syncs this year and next from a public holiday service. If it can't be reached, the existing list is kept.</li>
                    <li>Holidays that fall on a Sunday are automatically observed on the Monday, as the Public Holidays Act requires.</li>
                    <li><strong>Idd-ul-Fitr and any short-notice gazetted holiday must be added here</strong> — no public service knows those in advance. Holidays you add are never changed by the sync.</li>
                    <li>Switch off a holiday that doesn't apply; it stops counting against leave and stays off after future syncs.</li>
                </ul>
            </div>
            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-sm font-semibold text-gray-900">Last sync</h2>
                <p class="mt-1 text-sm text-gray-600">{{ lastSyncedAt ? fmtSynced(lastSyncedAt) : 'Never — using built-in dates' }}</p>
                <p class="mt-1 truncate text-xs text-gray-400" :title="apiUrl">Source: {{ apiUrl }}</p>
                <button
                    type="button"
                    @click="syncNow"
                    :disabled="syncing"
                    class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-lg border border-[#1B5E20] bg-white px-4 py-2 text-sm font-medium text-[#1B5E20] hover:bg-green-50 disabled:opacity-50"
                >
                    <svg :class="syncing ? 'animate-spin' : ''" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    {{ syncing ? 'Syncing…' : 'Sync now' }}
                </button>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap items-end justify-between gap-3">
            <label class="text-xs font-medium text-gray-600">
                Year
                <select v-model="year" class="mt-1 block rounded-lg border-gray-300 text-sm shadow-sm focus:border-[#4CAF50] focus:ring-[#4CAF50]">
                    <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                </select>
            </label>
            <button
                type="button"
                @click="showAdd = true"
                class="inline-flex items-center gap-2 rounded-lg bg-[#1B5E20] px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                Add gazetted holiday
            </button>
        </div>

        <div class="mt-3">
            <DataTable :columns="['Date', 'Day', 'Holiday', 'Source', 'Counts for leave', '']" :empty="`No holidays recorded for ${year}.`">
                <tr v-for="h in holidays" :key="h.id" :class="h.isActive ? '' : 'bg-gray-50'" class="hover:bg-gray-50">
                    <td class="whitespace-nowrap px-4 py-3 text-sm font-medium text-gray-900">{{ fmtDate(h.date) }}</td>
                    <td :class="['Saturday', 'Sunday'].includes(h.weekday) ? 'text-amber-700' : 'text-gray-600'" class="px-4 py-3 text-sm">{{ h.weekday }}</td>
                    <td :class="h.isActive ? 'text-gray-900' : 'text-gray-400 line-through'" class="px-4 py-3 text-sm">
                        {{ h.name }}
                        <span v-if="h.isSubstitute" class="ml-1 text-xs text-gray-500">(Sunday rule)</span>
                    </td>
                    <td class="px-4 py-3">
                        <span :class="sourceBadge[h.source][1]" class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium">{{ sourceBadge[h.source][0] }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <button
                            v-if="!h.isSubstitute"
                            type="button"
                            role="switch"
                            :aria-checked="h.isActive"
                            @click="toggle(h)"
                            :class="h.isActive ? 'bg-[#4CAF50]' : 'bg-gray-300'"
                            class="relative inline-flex h-5 w-9 items-center rounded-full transition"
                        >
                            <span :class="h.isActive ? 'translate-x-4' : 'translate-x-1'" class="inline-block h-3.5 w-3.5 rounded-full bg-white shadow transition" />
                        </button>
                        <span v-else class="text-xs text-gray-500">Follows original</span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button v-if="h.source === 'manual'" type="button" @click="deleting = h" class="text-sm font-medium text-red-600 hover:underline">Delete</button>
                    </td>
                </tr>
            </DataTable>
        </div>

        <!-- Add modal -->
        <Teleport to="body">
            <div v-if="showAdd" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/50" @click="showAdd = false" />
                <div class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                    <h3 class="text-lg font-semibold text-gray-900">Add gazetted holiday</h3>
                    <p class="mt-1 text-sm text-gray-600">For Idd-ul-Fitr or any holiday announced in the Kenya Gazette.</p>
                    <form @submit.prevent="submit" class="mt-4 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Date <span class="text-red-500">*</span></label>
                            <input v-model="form.date" type="date" :class="field" required />
                            <p v-if="form.errors.date" class="mt-1 text-sm text-red-600">{{ form.errors.date }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Name <span class="text-red-500">*</span></label>
                            <input v-model="form.name" type="text" maxlength="120" placeholder="e.g. Idd-ul-Fitr" :class="field" required />
                            <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                        </div>
                        <div class="flex justify-end gap-3">
                            <button type="button" @click="showAdd = false" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                            <button type="submit" :disabled="form.processing" class="rounded-lg bg-[#1B5E20] px-5 py-2 text-sm font-medium text-white hover:bg-[#2E7D32] disabled:opacity-50">Add holiday</button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <ConfirmModal
            :show="!!deleting"
            title="Delete holiday"
            :message="`Delete ${deleting?.name}? Leave already approved over this date is not recalculated.`"
            confirm-label="Delete"
            @confirm="confirmDelete"
            @cancel="deleting = null"
        />
    </AppLayout>
</template>
