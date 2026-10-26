<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    missingDetails: { type: Array, default: () => [] },
});

const now = new Date();

const form = useForm({
    period_month: now.getMonth() + 1,
    period_year: now.getFullYear(),
    notes: '',
});

const months = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

const years = Array.from({ length: 6 }, (_, i) => now.getFullYear() - 2 + i);

const field = 'mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-[#4CAF50] focus:ring-[#4CAF50]';

function submit() {
    form.post(route('payroll.runs.store'));
}
</script>

<template>
    <Head title="New Payroll Run" />

    <AppLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-gray-900">New Payroll Run</h1>
        </template>

        <div class="max-w-3xl space-y-6">
            <!-- Employees with incomplete records cannot be paid cleanly, so surface
                 them before the run is created rather than at approval time. -->
            <div
                v-if="missingDetails.length"
                class="rounded-lg border border-amber-200 bg-amber-50 p-4"
            >
                <div class="flex gap-3">
                    <svg class="h-5 w-5 shrink-0 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                    <div class="min-w-0">
                        <h3 class="text-sm font-semibold text-amber-900">
                            {{ missingDetails.length }} employee(s) have incomplete records
                        </h3>
                        <p class="mt-1 text-sm text-amber-800">
                            These are missing details needed for statutory schedules or payment files.
                            You can still create the run, but fix these before approving it.
                        </p>
                        <ul class="mt-3 space-y-1 text-sm text-amber-900">
                            <li v-for="e in missingDetails.slice(0, 10)" :key="e.employee_id">
                                <span class="font-medium">{{ e.employee_number }} — {{ e.name }}</span>
                                <span class="text-amber-700"> · missing {{ e.missing.join(', ') }}</span>
                            </li>
                        </ul>
                        <p v-if="missingDetails.length > 10" class="mt-2 text-xs text-amber-700">
                            …and {{ missingDetails.length - 10 }} more.
                        </p>
                    </div>
                </div>
            </div>

            <form @submit.prevent="submit" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            Month <span class="text-red-500">*</span>
                        </label>
                        <select v-model="form.period_month" :class="field" required>
                            <option v-for="(m, i) in months" :key="m" :value="i + 1">{{ m }}</option>
                        </select>
                        <p v-if="form.errors.period_month" class="mt-1 text-sm text-red-600">
                            {{ form.errors.period_month }}
                        </p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            Year <span class="text-red-500">*</span>
                        </label>
                        <select v-model="form.period_year" :class="field" required>
                            <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                        </select>
                        <p v-if="form.errors.period_year" class="mt-1 text-sm text-red-600">
                            {{ form.errors.period_year }}
                        </p>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700">Notes</label>
                    <textarea v-model="form.notes" rows="3" :class="field" />
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <Link
                        :href="route('payroll.runs.index')"
                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-[#1B5E20] px-5 py-2 text-sm font-medium text-white transition hover:bg-[#2E7D32] disabled:opacity-50"
                    >
                        {{ form.processing ? 'Creating…' : 'Create Run' }}
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
