<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import PayBreakdownPanel from './Partials/PayBreakdownPanel.vue';
import { usePermissions } from '@/composables/usePermissions';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    run: { type: Object, required: true },
    payslips: { type: Object, required: true },
    validation: { type: Object, default: null },
    canApprove: { type: Boolean, default: false },
    canViewBreakdown: { type: Boolean, default: false },
    // Optional prop: one employee's pay breakdown, fetched when a row is opened.
    breakdown: { type: Object, default: null },
});

const { can } = usePermissions();

const confirming = ref(null);

const showBreakdown = ref(false);
const loadingSlip = ref(null);

// preserveUrl keeps ?payslip= out of the address bar; the panel reads the fetched prop.
function openBreakdown(slip) {
    if (!props.canViewBreakdown || loadingSlip.value) return;
    router.reload({
        only: ['breakdown'],
        data: { payslip: slip.id },
        preserveUrl: true,
        onStart: () => (loadingSlip.value = slip.id),
        onFinish: () => (loadingSlip.value = null),
        onSuccess: () => (showBreakdown.value = true),
    });
}

const months = [
    'January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December',
];

const period = computed(() => `${months[props.run.period_month - 1]} ${props.run.period_year}`);

const breaches = computed(() => props.validation?.two_thirds_breaches ?? []);
const negatives = computed(() => props.validation?.negative_net_pay ?? []);
const hasIssues = computed(() => breaches.value.length > 0 || negatives.value.length > 0);

const isLocked = computed(() => !!props.run.locked_at);
const canCalculate = computed(
    () => can('payroll.calculate') && !isLocked.value && ['draft', 'calculated'].includes(props.run.status)
);

function money(value) {
    return Number(value ?? 0).toLocaleString('en-KE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function calculate() {
    router.post(route('payroll.runs.calculate', props.run.id), {}, { preserveScroll: true });
}

function approve() {
    router.post(route('payroll.runs.approve', props.run.id), {}, {
        preserveScroll: true,
        onFinish: () => (confirming.value = null),
    });
}

const totals = computed(() => [
    { label: 'Gross', value: props.run.total_gross },
    { label: 'Deductions', value: props.run.total_deductions },
    { label: 'Net Pay', value: props.run.total_net, emphasis: true },
    { label: 'Employer Cost', value: props.run.total_employer_cost },
]);

const columns = [
    { key: 'employee', label: 'Employee' },
    { key: 'farm', label: 'Farm' },
    { key: 'gross', label: 'Gross' },
    { key: 'nssf', label: 'NSSF' },
    { key: 'shif', label: 'SHIF' },
    { key: 'ahl', label: 'AHL' },
    { key: 'paye', label: 'PAYE' },
    { key: 'net', label: 'Net Pay' },
    { key: 'payment', label: 'Payment' },
    { key: 'actions', label: '' },
];
</script>

<template>
    <Head :title="`Payroll — ${period}`" />

    <AppLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <h1 class="text-lg font-semibold text-gray-900">Payroll — {{ period }}</h1>
                <StatusBadge :status="run.status" />
                <span
                    v-if="isLocked"
                    class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2 py-1 text-xs font-medium text-gray-700"
                >
                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                    Locked
                </span>
            </div>
        </template>

        <!-- Actions -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="text-sm text-gray-600">
                <span v-if="run.calculated_by_user">
                    Calculated by {{ run.calculated_by_user.name }}
                </span>
                <span v-if="run.approved_by_user" class="ml-2">
                    · Approved by {{ run.approved_by_user.name }}
                </span>
            </div>

            <div class="flex gap-3">
                <button
                    v-if="canCalculate"
                    type="button"
                    @click="calculate"
                    class="rounded-lg border border-[#1B5E20] bg-white px-4 py-2 text-sm font-medium text-[#1B5E20] transition hover:bg-green-50"
                >
                    {{ run.status === 'draft' ? 'Calculate' : 'Recalculate' }}
                </button>

                <button
                    v-if="canApprove"
                    type="button"
                    :disabled="hasIssues"
                    :title="hasIssues ? 'Resolve the validation issues below before approving.' : ''"
                    @click="confirming = true"
                    class="rounded-lg bg-[#1B5E20] px-5 py-2 text-sm font-medium text-white transition hover:bg-[#2E7D32] disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Approve &amp; Lock
                </button>
            </div>
        </div>

        <!-- Maker-checker notice -->
        <div
            v-if="run.status === 'calculated' && !canApprove && can('payroll.calculate')"
            class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900"
        >
            This run is waiting for approval. Whoever calculated it cannot approve it — approval must
            come from a different user with the payroll approval permission.
        </div>

        <!-- Validation issues -->
        <div v-if="hasIssues" class="mt-4 rounded-lg border border-red-200 bg-red-50 p-4">
            <h3 class="text-sm font-semibold text-red-900">
                This run cannot be approved until these are resolved
            </h3>

            <div v-if="breaches.length" class="mt-3">
                <p class="text-sm font-medium text-red-800">
                    Deductions above two-thirds of wages ({{ breaches.length }})
                </p>
                <ul class="mt-1 space-y-1 text-sm text-red-800">
                    <li v-for="b in breaches" :key="b.employee_id">
                        {{ b.employee_number }} — {{ b.name }}:
                        deducting {{ money(b.total_deductions) }} of {{ money(b.gross_pay) }}
                        (max {{ money(b.max_deductible) }}, over by {{ money(b.excess) }})
                    </li>
                </ul>
            </div>

            <div v-if="negatives.length" class="mt-3">
                <p class="text-sm font-medium text-red-800">
                    Negative net pay ({{ negatives.length }})
                </p>
                <ul class="mt-1 space-y-1 text-sm text-red-800">
                    <li v-for="n in negatives" :key="n.employee_id">
                        {{ n.employee_number }} — {{ n.name }}: {{ money(n.net_pay) }}
                    </li>
                </ul>
            </div>
        </div>

        <!-- Totals -->
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="t in totals"
                :key="t.label"
                class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm"
            >
                <p class="text-xs font-medium uppercase tracking-wide text-gray-500">{{ t.label }}</p>
                <p
                    :class="t.emphasis ? 'text-[#1B5E20]' : 'text-gray-900'"
                    class="mt-1 text-xl font-semibold tabular-nums"
                >
                    KES {{ money(t.value) }}
                </p>
            </div>
        </div>

        <!-- Payslips -->
        <div class="mt-6">
            <h2 class="text-sm font-semibold text-gray-900">Payslips</h2>
            <div class="mt-3">
                <DataTable
                    :columns="columns"
                    empty="No payslips yet. Calculate the run to generate them."
                >
                    <tr
                        v-for="slip in payslips.data"
                        :key="slip.id"
                        @click="openBreakdown(slip)"
                        :class="[
                            canViewBreakdown ? 'cursor-pointer hover:bg-green-50/60' : 'hover:bg-gray-50',
                            loadingSlip === slip.id ? 'bg-green-50/60' : '',
                        ]"
                    >
                        <td class="px-4 py-3">
                            <button
                                v-if="canViewBreakdown"
                                type="button"
                                @click.stop="openBreakdown(slip)"
                                class="text-left text-sm font-medium text-gray-900 hover:text-[#1B5E20] hover:underline"
                            >
                                {{ slip.employee?.first_name }} {{ slip.employee?.last_name }}
                            </button>
                            <div v-else class="text-sm font-medium text-gray-900">
                                {{ slip.employee?.first_name }} {{ slip.employee?.last_name }}
                            </div>
                            <div class="text-xs text-gray-500">{{ slip.employee?.employee_number }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600">
                            {{ slip.employee?.farm?.name ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ money(slip.gross_pay) }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ money(slip.nssf_employee) }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ money(slip.shif) }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ money(slip.ahl_employee) }}</td>
                        <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ money(slip.paye) }}</td>
                        <td class="px-4 py-3 text-sm font-medium tabular-nums text-gray-900">{{ money(slip.net_pay) }}</td>
                        <td class="px-4 py-3">
                            <StatusBadge :status="slip.payment_status" size="xs" />
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-3 whitespace-nowrap">
                                <span v-if="loadingSlip === slip.id" class="text-sm text-gray-500">Loading…</span>
                                <a :href="route('payslips.download', slip.id)" @click.stop class="text-sm font-medium text-gray-600 hover:underline">PDF</a>
                            </div>
                        </td>
                    </tr>
                </DataTable>

                <Pagination v-if="payslips.links" :links="payslips.links" class="mt-4" />
            </div>
        </div>

        <PayBreakdownPanel
            v-if="showBreakdown && breakdown"
            :breakdown="breakdown"
            :loading="loadingSlip !== null"
            @close="showBreakdown = false"
        />

        <ConfirmModal
            :show="!!confirming"
            title="Approve payroll run"
            :message="`Approve and lock payroll for ${period}? Once locked, the run cannot be recalculated and corrections must go into the next month as adjustments.`"
            confirm-label="Approve & Lock"
            variant="primary"
            @confirm="approve"
            @cancel="confirming = null"
        />
    </AppLayout>
</template>
