<script setup>
import SlideOver from '@/Components/SlideOver.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

// `breakdown` is the server's optional `breakdown` prop: PayslipDocument::build() plus employer totals.
defineProps({
    breakdown: { type: Object, required: true },
    loading: { type: Boolean, default: false },
});

const emit = defineEmits(['close']);

const money = (v) =>
    Number(v ?? 0).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const days = (d) => `${Number(d).toLocaleString('en-KE', { maximumFractionDigits: 2 })} day${Number(d) === 1 ? '' : 's'}`;

const section = 'rounded-xl border border-gray-200 bg-white';
const row = 'flex items-baseline justify-between gap-4 px-4 py-2 text-sm';
const subtotal = 'flex items-baseline justify-between gap-4 border-t border-gray-100 bg-gray-50 px-4 py-2 text-sm font-semibold text-gray-900';
</script>

<template>
    <SlideOver :label="`Pay breakdown for ${breakdown.employee.name}`" :loading="loading" @close="emit('close')">
        <template #header>
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Pay breakdown · {{ breakdown.period }}</p>
            <h2 class="mt-0.5 truncate text-lg font-semibold text-gray-900">{{ breakdown.employee.name }}</h2>
            <p class="truncate text-xs text-gray-500">
                <span class="font-mono">{{ breakdown.employee.number }}</span>
                <span v-if="breakdown.employee.jobTitle"> · {{ breakdown.employee.jobTitle }}</span>
                <span v-if="breakdown.employee.farm"> · {{ breakdown.employee.farm }}</span>
            </p>
        </template>

        <!-- Headline figures -->
        <div class="rounded-xl bg-[#1B5E20] p-5 text-white">
            <p class="text-xs font-medium uppercase tracking-wide text-green-100">Take-home pay</p>
            <p class="mt-1 text-3xl font-semibold tabular-nums">KES {{ money(breakdown.net) }}</p>
            <dl class="mt-4 grid grid-cols-3 gap-3 border-t border-white/20 pt-3 text-xs">
                <div>
                    <dt class="text-green-100">Gross</dt>
                    <dd class="mt-0.5 text-sm font-semibold tabular-nums">{{ money(breakdown.gross) }}</dd>
                </div>
                <div>
                    <dt class="text-green-100">Deductions</dt>
                    <dd class="mt-0.5 text-sm font-semibold tabular-nums">−{{ money(breakdown.totalDeductions) }}</dd>
                </div>
                <div>
                    <dt class="text-green-100">Cost to company</dt>
                    <dd class="mt-0.5 text-sm font-semibold tabular-nums">{{ money(breakdown.costToCompany) }}</dd>
                </div>
            </dl>
        </div>

        <!-- Earnings -->
        <section :class="section">
            <h3 class="px-4 pb-1 pt-3 text-sm font-semibold text-gray-900">Earnings</h3>
            <div v-for="line in breakdown.earnings" :key="line.name" :class="row">
                <span class="text-gray-600">{{ line.name }}</span>
                <span class="tabular-nums text-gray-900">{{ money(line.amount) }}</span>
            </div>
            <div v-if="breakdown.unpaidLeave.amount > 0" :class="row">
                <span class="text-gray-600">Less: unpaid leave ({{ days(breakdown.unpaidLeave.days) }})</span>
                <span class="tabular-nums text-red-600">−{{ money(breakdown.unpaidLeave.amount) }}</span>
            </div>
            <div :class="subtotal">
                <span>Gross pay</span>
                <span class="tabular-nums">{{ money(breakdown.gross) }}</span>
            </div>
        </section>

        <!-- Deductions -->
        <section :class="section">
            <h3 class="px-4 pb-1 pt-3 text-sm font-semibold text-gray-900">Deductions</h3>
            <p class="px-4 pt-1 text-xs font-medium uppercase tracking-wide text-gray-400">Statutory</p>
            <div v-for="line in breakdown.statutory" :key="line.name" :class="row">
                <span class="text-gray-600">{{ line.name }}</span>
                <span class="tabular-nums text-gray-900">{{ money(line.amount) }}</span>
            </div>
            <p class="px-4 pt-2 text-xs font-medium uppercase tracking-wide text-gray-400">Other</p>
            <div v-for="line in breakdown.other" :key="line.name" :class="row">
                <span class="text-gray-600">{{ line.name }}</span>
                <span class="tabular-nums text-gray-900">{{ money(line.amount) }}</span>
            </div>
            <div v-if="!breakdown.other.length" :class="row">
                <span class="text-gray-400">None this month</span>
                <span class="tabular-nums text-gray-400">0.00</span>
            </div>
            <div :class="subtotal">
                <span>Total deductions</span>
                <span class="tabular-nums">{{ money(breakdown.totalDeductions) }}</span>
            </div>
        </section>

        <!-- PAYE -->
        <details :class="section" class="group">
            <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 text-sm font-semibold text-gray-900">
                How PAYE was worked out
                <svg class="h-4 w-4 text-gray-400 transition-transform group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" /></svg>
            </summary>
            <div :class="row"><span class="text-gray-600">Gross pay</span><span class="tabular-nums">{{ money(breakdown.tax.gross) }}</span></div>
            <div :class="row"><span class="text-gray-600">Less: NSSF, SHIF, Housing Levy</span><span class="tabular-nums text-red-600">−{{ money(breakdown.tax.nssf + breakdown.tax.shif + breakdown.tax.ahl) }}</span></div>
            <div :class="subtotal"><span>Taxable pay</span><span class="tabular-nums">{{ money(breakdown.tax.taxable) }}</span></div>
            <div :class="row"><span class="text-gray-600">Tax at KRA bands</span><span class="tabular-nums">{{ money(breakdown.tax.payeBeforeRelief) }}</span></div>
            <div :class="row"><span class="text-gray-600">Less: personal relief</span><span class="tabular-nums text-red-600">−{{ money(breakdown.tax.personalRelief) }}</span></div>
            <div v-if="breakdown.tax.insuranceRelief > 0" :class="row"><span class="text-gray-600">Less: insurance relief</span><span class="tabular-nums text-red-600">−{{ money(breakdown.tax.insuranceRelief) }}</span></div>
            <div :class="subtotal" class="rounded-b-xl"><span>PAYE</span><span class="tabular-nums">{{ money(breakdown.tax.paye) }}</span></div>
        </details>

        <!-- Employer -->
        <section :class="section">
            <h3 class="px-4 pb-1 pt-3 text-sm font-semibold text-gray-900">Paid by the company on top</h3>
            <div v-for="line in breakdown.employer" :key="line.name" :class="row">
                <span class="text-gray-600">{{ line.name }}</span>
                <span class="tabular-nums text-gray-900">{{ money(line.amount) }}</span>
            </div>
            <div :class="subtotal">
                <span>Gross + employer contributions</span>
                <span class="tabular-nums">{{ money(breakdown.costToCompany) }}</span>
            </div>
        </section>

        <!-- Paid to -->
        <section :class="section" class="px-4 py-3 text-sm">
            <div class="flex items-center justify-between gap-4">
                <span class="text-gray-600">Paid to</span>
                <span class="text-right text-gray-900">
                    {{ breakdown.payment.method ?? '—' }}<span v-if="breakdown.payment.bank"> · {{ breakdown.payment.bank }}</span>
                    <span v-if="breakdown.payment.account" class="ml-1 font-mono text-xs text-gray-500">{{ breakdown.payment.account }}</span>
                </span>
            </div>
            <div class="mt-2 flex items-center justify-between gap-4">
                <span class="text-gray-600">Run status</span>
                <StatusBadge :status="breakdown.runStatus" size="xs" />
            </div>
        </section>

        <template #footer>
            <a
                :href="route('payslips.download', breakdown.payslipId)"
                class="inline-flex flex-1 items-center justify-center gap-2 rounded-lg bg-[#1B5E20] px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                Download payslip
            </a>
            <button type="button" @click="emit('close')" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Close
            </button>
        </template>
    </SlideOver>
</template>
