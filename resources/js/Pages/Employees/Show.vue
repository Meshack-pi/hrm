<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import EditEmployeeModal from './Partials/EditEmployeeModal.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { usePermissions } from '@/composables/usePermissions';
import { computed, ref } from 'vue';

const props = defineProps({
    employee: { type: Object, required: true },
    // Optional prop: only fetched when the edit modal opens.
    editForm: { type: Object, default: null },
});

const { can } = usePermissions();

const activeTab = ref('overview');

const editing = ref(false);
const loadingEdit = ref(false);
const editKey = ref(0);

// Fetch fresh form data each time, so a reopened modal never shows stale values.
function openEdit() {
    router.reload({
        only: ['editForm'],
        onStart: () => (loadingEdit.value = true),
        onFinish: () => (loadingEdit.value = false),
        onSuccess: () => {
            editKey.value++;
            editing.value = true;
        },
    });
}
const revealAccounts = ref(false);

const tabs = computed(() =>
    [
        { key: 'overview', label: 'Overview', show: true },
        { key: 'personal', label: 'Personal & Statutory', show: true },
        { key: 'employment', label: 'Employment', show: true },
        { key: 'salary', label: 'Salary & Pay', show: can('salaries.view') },
        { key: 'payment', label: 'Payment Details', show: can('salaries.view') },
        { key: 'leave', label: 'Leave', show: true },
        { key: 'documents', label: 'Documents', show: true },
    ].filter(t => t.show),
);

const e = computed(() => props.employee);

const fullName = computed(() =>
    [e.value.first_name, e.value.middle_name, e.value.last_name].filter(Boolean).join(' '),
);

const initials = computed(() =>
    `${e.value.first_name?.[0] ?? ''}${e.value.last_name?.[0] ?? ''}`.toUpperCase(),
);

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}

function formatMoney(value) {
    if (value === null || value === undefined || value === '') return '—';
    return `KES ${Number(value).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function mask(value) {
    if (!value) return '—';
    const str = String(value);
    if (revealAccounts.value) return str;
    return str.length <= 4 ? '••••' : `${'•'.repeat(str.length - 4)}${str.slice(-4)}`;
}

// The model accessor isn't serialised, so derive the balance here.
function leaveBalance(b) {
    return (
        Number(b.accrued ?? 0) +
        Number(b.carried_forward ?? 0) -
        Number(b.taken ?? 0) -
        Number(b.pending ?? 0)
    ).toFixed(2);
}

const currentSalary = computed(() => {
    const list = e.value.salaries ?? [];
    if (!list.length) return null;
    return [...list].sort((a, b) => new Date(b.effective_from) - new Date(a.effective_from))[0];
});

const isDocumentExpiring = (doc) => {
    if (!doc.expires_at) return false;
    const days = (new Date(doc.expires_at) - new Date()) / 86400000;
    return days < 60;
};
</script>

<template>
    <Head :title="fullName" />

    <AppLayout>
        <template #header>
            <div class="flex items-center gap-2 text-sm">
                <Link :href="route('employees.index')" class="text-gray-500 hover:text-gray-700">Employees</Link>
                <span class="text-gray-300">/</span>
                <span class="font-semibold text-gray-900">{{ fullName }}</span>
            </div>
        </template>

        <!-- Profile header -->
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-center gap-4">
                    <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-full bg-[#1B5E20] text-xl font-bold text-white">
                        {{ initials }}
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">{{ fullName }}</h2>
                        <p class="text-sm text-gray-600">
                            {{ e.job_title?.name || 'No job title' }}
                            <span v-if="e.department"> · {{ e.department.name }}</span>
                        </p>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <span class="rounded-md bg-gray-100 px-2 py-1 font-mono text-xs text-gray-700">
                                {{ e.employee_number }}
                            </span>
                            <StatusBadge :status="e.status" />
                            <StatusBadge :status="e.employment_type" />
                            <span v-if="e.farm" class="rounded-full bg-green-50 px-2 py-1 text-xs font-medium text-[#1B5E20]">
                                {{ e.farm.name }}
                            </span>
                        </div>
                    </div>
                </div>

                <button
                    v-if="can('employees.edit')"
                    type="button"
                    @click="openEdit"
                    :disabled="loadingEdit"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-[#1B5E20] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#2E7D32] disabled:opacity-50"
                >
                    {{ loadingEdit ? 'Loading…' : 'Edit Employee' }}
                </button>
            </div>
        </div>

        <!-- Tabs -->
        <div class="mt-6 border-b border-gray-200">
            <nav class="-mb-px flex gap-1 overflow-x-auto">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    @click="activeTab = tab.key"
                    :class="
                        activeTab === tab.key
                            ? 'border-[#1B5E20] text-[#1B5E20]'
                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
                    "
                    class="whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium transition"
                >
                    {{ tab.label }}
                </button>
            </nav>
        </div>

        <div class="mt-6">
            <!-- Overview -->
            <div v-show="activeTab === 'overview'" class="grid gap-6 lg:grid-cols-2">
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">Contact</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600">Phone</dt>
                            <dd class="text-right font-medium text-gray-900">{{ e.phone || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600">Work email</dt>
                            <dd class="text-right font-medium text-gray-900">{{ e.work_email || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600">Personal email</dt>
                            <dd class="text-right font-medium text-gray-900">{{ e.personal_email || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600">County</dt>
                            <dd class="text-right font-medium text-gray-900">{{ e.county || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600">Address</dt>
                            <dd class="text-right font-medium text-gray-900">{{ e.physical_address || '—' }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">Next of kin</h3>
                    <div v-if="e.next_of_kin?.length" class="mt-4 space-y-4">
                        <div v-for="kin in e.next_of_kin" :key="kin.id" class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ kin.name }}</p>
                                    <p class="text-xs text-gray-600">{{ kin.relationship }} · {{ kin.phone }}</p>
                                </div>
                                <span
                                    v-if="kin.is_emergency_contact"
                                    class="shrink-0 rounded-full bg-red-50 px-2 py-1 text-xs font-medium text-red-700"
                                >
                                    Emergency
                                </span>
                            </div>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-gray-500">No next of kin recorded.</p>
                </section>
            </div>

            <!-- Personal & statutory -->
            <div v-show="activeTab === 'personal'" class="grid gap-6 lg:grid-cols-2">
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">Personal</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600">Date of birth</dt>
                            <dd class="font-medium text-gray-900">{{ formatDate(e.date_of_birth) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600">Gender</dt>
                            <dd class="font-medium capitalize text-gray-900">{{ e.gender || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600">Marital status</dt>
                            <dd class="font-medium capitalize text-gray-900">{{ e.marital_status || '—' }}</dd>
                        </div>
                    </dl>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">Statutory</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600">National ID</dt>
                            <dd class="font-mono font-medium text-gray-900">{{ e.national_id || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600">Passport</dt>
                            <dd class="font-mono font-medium text-gray-900">{{ e.passport_number || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600">KRA PIN</dt>
                            <dd class="font-mono font-medium text-gray-900">{{ e.kra_pin || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600">NSSF number</dt>
                            <dd class="font-mono font-medium text-gray-900">{{ e.nssf_number || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600">SHA number</dt>
                            <dd class="font-mono font-medium text-gray-900">{{ e.sha_number || '—' }}</dd>
                        </div>
                        <div v-if="e.has_disability_exemption" class="flex justify-between gap-4 border-t border-gray-100 pt-3">
                            <dt class="text-gray-600">Disability exemption</dt>
                            <dd class="font-medium text-gray-900">
                                Until {{ formatDate(e.disability_certificate_expiry) }}
                            </dd>
                        </div>
                    </dl>
                </section>
            </div>

            <!-- Employment -->
            <div v-show="activeTab === 'employment'">
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">Employment details</h3>
                    <dl class="mt-4 grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600">Farm</dt>
                            <dd class="font-medium text-gray-900">{{ e.farm?.name || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600">Department</dt>
                            <dd class="font-medium text-gray-900">{{ e.department?.name || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600">Job title</dt>
                            <dd class="font-medium text-gray-900">{{ e.job_title?.name || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600">Line manager</dt>
                            <dd class="font-medium text-gray-900">
                                {{ e.manager ? `${e.manager.first_name} ${e.manager.last_name}` : '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600">Employment type</dt>
                            <dd class="font-medium capitalize text-gray-900">{{ e.employment_type }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600">Status</dt>
                            <dd><StatusBadge :status="e.status" /></dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600">Hire date</dt>
                            <dd class="font-medium text-gray-900">{{ formatDate(e.hire_date) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600">Probation ends</dt>
                            <dd class="font-medium text-gray-900">{{ formatDate(e.probation_end_date) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-600">Contract ends</dt>
                            <dd class="font-medium text-gray-900">{{ formatDate(e.contract_end_date) }}</dd>
                        </div>
                    </dl>
                </section>
            </div>

            <!-- Salary & pay -->
            <div v-show="activeTab === 'salary'" class="space-y-6">
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">Current basic salary</h3>
                    <p class="mt-3 text-3xl font-bold text-[#1B5E20]">
                        {{ currentSalary ? formatMoney(currentSalary.basic_salary) : '—' }}
                    </p>
                    <p v-if="currentSalary" class="mt-1 text-sm text-gray-600">
                        Effective {{ formatDate(currentSalary.effective_from) }}
                    </p>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">Salary history</h3>
                    <div v-if="e.salaries?.length" class="mt-4 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wider text-gray-600">
                                    <th class="pb-2 pr-4 font-semibold">Basic salary</th>
                                    <th class="pb-2 pr-4 font-semibold">From</th>
                                    <th class="pb-2 pr-4 font-semibold">To</th>
                                    <th class="pb-2 font-semibold">Reason</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <tr v-for="s in e.salaries" :key="s.id">
                                    <td class="py-2 pr-4 font-medium text-gray-900">{{ formatMoney(s.basic_salary) }}</td>
                                    <td class="py-2 pr-4 text-gray-600">{{ formatDate(s.effective_from) }}</td>
                                    <td class="py-2 pr-4 text-gray-600">{{ s.effective_to ? formatDate(s.effective_to) : 'Current' }}</td>
                                    <td class="py-2 text-gray-600">{{ s.reason || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p v-else class="mt-4 text-sm text-gray-500">No salary records yet.</p>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">Recurring allowances &amp; deductions</h3>
                    <div v-if="e.pay_components?.length" class="mt-4 space-y-2">
                        <div
                            v-for="pc in e.pay_components"
                            :key="pc.id"
                            class="flex items-center justify-between rounded-lg border border-gray-100 bg-gray-50 px-3 py-2"
                        >
                            <div>
                                <p class="text-sm font-medium text-gray-900">{{ pc.pay_component?.name || '—' }}</p>
                                <p class="text-xs text-gray-600">From {{ formatDate(pc.effective_from) }}</p>
                            </div>
                            <span
                                :class="pc.pay_component?.type === 'deduction' ? 'text-red-700' : 'text-[#1B5E20]'"
                                class="text-sm font-semibold"
                            >
                                {{ pc.pay_component?.type === 'deduction' ? '−' : '+' }}{{ formatMoney(pc.amount_or_rate) }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-gray-500">No recurring pay components.</p>
                </section>

                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">Reliefs</h3>
                    <div v-if="e.reliefs?.length" class="mt-4 space-y-2">
                        <div
                            v-for="r in e.reliefs"
                            :key="r.id"
                            class="flex items-center justify-between rounded-lg border border-gray-100 bg-gray-50 px-3 py-2"
                        >
                            <div>
                                <p class="text-sm font-medium capitalize text-gray-900">{{ r.type }}</p>
                                <p class="text-xs text-gray-600">{{ r.provider || '—' }} · {{ r.policy_number || 'No policy no.' }}</p>
                            </div>
                            <span class="text-sm font-semibold text-gray-900">
                                {{ formatMoney(r.monthly_premium_or_amount) }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-gray-500">No reliefs recorded.</p>
                </section>
            </div>

            <!-- Payment details -->
            <div v-show="activeTab === 'payment'">
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Payment details</h3>
                            <p class="mt-1 text-sm text-gray-600">Where this employee's net pay is sent.</p>
                        </div>
                        <button
                            v-if="e.bank_details?.length"
                            type="button"
                            @click="revealAccounts = !revealAccounts"
                            class="shrink-0 text-sm font-medium text-[#1B5E20] hover:underline"
                        >
                            {{ revealAccounts ? 'Hide' : 'Reveal' }}
                        </button>
                    </div>

                    <div v-if="e.bank_details?.length" class="mt-4 space-y-3">
                        <div
                            v-for="bd in e.bank_details"
                            :key="bd.id"
                            class="rounded-lg border border-gray-200 p-4"
                            :class="bd.is_primary ? 'border-[#4CAF50] bg-green-50/50' : ''"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <span class="text-sm font-semibold capitalize text-gray-900">{{ bd.payment_method }}</span>
                                <span v-if="bd.is_primary" class="rounded-full bg-[#1B5E20] px-2 py-0.5 text-xs font-medium text-white">
                                    Primary
                                </span>
                            </div>

                            <dl class="mt-3 space-y-2 text-sm">
                                <template v-if="bd.payment_method === 'bank'">
                                    <div class="flex justify-between gap-4">
                                        <dt class="text-gray-600">Bank</dt>
                                        <dd class="font-medium text-gray-900">{{ bd.bank_name || '—' }}</dd>
                                    </div>
                                    <div class="flex justify-between gap-4">
                                        <dt class="text-gray-600">Branch</dt>
                                        <dd class="font-medium text-gray-900">{{ bd.branch_name || '—' }}</dd>
                                    </div>
                                    <div class="flex justify-between gap-4">
                                        <dt class="text-gray-600">Account name</dt>
                                        <dd class="font-medium text-gray-900">{{ bd.account_name || '—' }}</dd>
                                    </div>
                                    <div class="flex justify-between gap-4">
                                        <dt class="text-gray-600">Account number</dt>
                                        <dd class="font-mono font-medium text-gray-900">{{ mask(bd.account_number) }}</dd>
                                    </div>
                                </template>
                                <div v-else class="flex justify-between gap-4">
                                    <dt class="text-gray-600">M-Pesa number</dt>
                                    <dd class="font-mono font-medium text-gray-900">{{ mask(bd.mpesa_number) }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-gray-500">
                        No payment details on file. This employee cannot be paid until these are added.
                    </p>
                </section>
            </div>

            <!-- Leave -->
            <div v-show="activeTab === 'leave'">
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">Leave balances</h3>
                    <div v-if="e.leave_balances?.length" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div
                            v-for="b in e.leave_balances"
                            :key="b.id"
                            class="rounded-lg border border-gray-200 p-4"
                        >
                            <p class="text-sm font-medium text-gray-900">{{ b.leave_type?.name || '—' }}</p>
                            <p class="mt-2 text-2xl font-bold text-[#1B5E20]">{{ leaveBalance(b) }}</p>
                            <p class="text-xs text-gray-600">days available · {{ b.year }}</p>
                            <dl class="mt-3 space-y-1 border-t border-gray-100 pt-3 text-xs">
                                <div class="flex justify-between">
                                    <dt class="text-gray-600">Accrued</dt>
                                    <dd class="font-medium text-gray-900">{{ b.accrued }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-gray-600">Carried forward</dt>
                                    <dd class="font-medium text-gray-900">{{ b.carried_forward }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-gray-600">Taken</dt>
                                    <dd class="font-medium text-gray-900">{{ b.taken }}</dd>
                                </div>
                                <div class="flex justify-between">
                                    <dt class="text-gray-600">Pending</dt>
                                    <dd class="font-medium text-gray-900">{{ b.pending }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-gray-500">No leave balances for this employee yet.</p>
                </section>
            </div>

            <!-- Documents -->
            <div v-show="activeTab === 'documents'">
                <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-base font-semibold text-gray-900">Documents</h3>
                    <div v-if="e.documents?.length" class="mt-4 space-y-2">
                        <div
                            v-for="doc in e.documents"
                            :key="doc.id"
                            class="flex items-center justify-between gap-4 rounded-lg border border-gray-100 bg-gray-50 px-4 py-3"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium capitalize text-gray-900">
                                    {{ doc.type.replace(/_/g, ' ') }}
                                </p>
                                <p class="text-xs text-gray-600">
                                    Uploaded {{ formatDate(doc.created_at) }}
                                    <span v-if="doc.uploader"> by {{ doc.uploader.name }}</span>
                                </p>
                            </div>
                            <span
                                v-if="doc.expires_at"
                                :class="isDocumentExpiring(doc) ? 'bg-red-50 text-red-700' : 'bg-gray-100 text-gray-700'"
                                class="shrink-0 rounded-full px-2 py-1 text-xs font-medium"
                            >
                                Expires {{ formatDate(doc.expires_at) }}
                            </span>
                        </div>
                    </div>
                    <p v-else class="mt-4 text-sm text-gray-500">No documents uploaded.</p>
                </section>
            </div>
        </div>

        <EditEmployeeModal v-if="editForm" :key="editKey" :show="editing" :edit-form="editForm" @close="editing = false" />
    </AppLayout>
</template>
