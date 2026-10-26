<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    form: { type: Object, required: true },
    farms: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    jobTitles: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    submitLabel: { type: String, default: 'Save' },
    showStatus: { type: Boolean, default: false },
    // Inside a modal, Cancel closes it instead of leaving the page.
    inModal: { type: Boolean, default: false },
});

defineEmits(['submit', 'cancel']);

const field = 'mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-[#4CAF50] focus:ring-[#4CAF50]';

// Job titles belong to a department; show only the relevant ones once a
// department is chosen, keeping unassigned titles always available.
const availableJobTitles = computed(() => {
    if (!props.form.department_id) return props.jobTitles;
    return props.jobTitles.filter(
        jt => !jt.department_id || String(jt.department_id) === String(props.form.department_id),
    );
});

function employeeLabel(e) {
    return `${e.first_name} ${e.last_name} (${e.employee_number})`;
}
</script>

<template>
    <form @submit.prevent="$emit('submit')" :class="inModal ? '' : 'max-w-5xl'" class="space-y-6">
        <!-- Personal details -->
        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Personal details</h2>
            <p class="mt-1 text-sm text-gray-600">Basic identifying information for this employee.</p>

            <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">First name <span class="text-red-500">*</span></label>
                    <input v-model="form.first_name" type="text" :class="field" required />
                    <p v-if="form.errors.first_name" class="mt-1 text-sm text-red-600">{{ form.errors.first_name }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Middle name</label>
                    <input v-model="form.middle_name" type="text" :class="field" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Last name <span class="text-red-500">*</span></label>
                    <input v-model="form.last_name" type="text" :class="field" required />
                    <p v-if="form.errors.last_name" class="mt-1 text-sm text-red-600">{{ form.errors.last_name }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Date of birth</label>
                    <input v-model="form.date_of_birth" type="date" :class="field" />
                    <p v-if="form.errors.date_of_birth" class="mt-1 text-sm text-red-600">{{ form.errors.date_of_birth }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Gender</label>
                    <select v-model="form.gender" :class="field">
                        <option value="">Not specified</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Marital status</label>
                    <select v-model="form.marital_status" :class="field">
                        <option value="">Not specified</option>
                        <option value="single">Single</option>
                        <option value="married">Married</option>
                        <option value="divorced">Divorced</option>
                        <option value="widowed">Widowed</option>
                    </select>
                </div>
            </div>
        </section>

        <!-- Contact -->
        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Contact</h2>
            <p class="mt-1 text-sm text-gray-600">How to reach this employee.</p>

            <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Phone</label>
                    <input v-model="form.phone" type="tel" placeholder="07XX XXX XXX" :class="field" />
                    <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600">{{ form.errors.phone }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Personal email</label>
                    <input v-model="form.personal_email" type="email" :class="field" />
                    <p v-if="form.errors.personal_email" class="mt-1 text-sm text-red-600">{{ form.errors.personal_email }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Work email</label>
                    <input v-model="form.work_email" type="email" :class="field" />
                    <p v-if="form.errors.work_email" class="mt-1 text-sm text-red-600">{{ form.errors.work_email }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-gray-700">Physical address</label>
                    <textarea v-model="form.physical_address" rows="2" :class="field" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">County</label>
                    <input v-model="form.county" type="text" placeholder="e.g. Uasin Gishu" :class="field" />
                </div>
            </div>
        </section>

        <!-- Statutory -->
        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Statutory details</h2>
            <p class="mt-1 text-sm text-gray-600">
                Required for payroll. Missing values will be flagged before a payroll run.
            </p>

            <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">National ID</label>
                    <input v-model="form.national_id" type="text" :class="field" />
                    <p v-if="form.errors.national_id" class="mt-1 text-sm text-red-600">{{ form.errors.national_id }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Passport number</label>
                    <input v-model="form.passport_number" type="text" :class="field" />
                    <p v-if="form.errors.passport_number" class="mt-1 text-sm text-red-600">{{ form.errors.passport_number }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">KRA PIN</label>
                    <input
                        v-model="form.kra_pin"
                        type="text"
                        placeholder="A123456789B"
                        class="mt-1 w-full rounded-lg border-gray-300 text-sm uppercase shadow-sm focus:border-[#4CAF50] focus:ring-[#4CAF50]"
                    />
                    <p v-if="form.errors.kra_pin" class="mt-1 text-sm text-red-600">{{ form.errors.kra_pin }}</p>
                    <p v-else class="mt-1 text-xs text-gray-500">Format: letter, 9 digits, letter</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">NSSF number</label>
                    <input v-model="form.nssf_number" type="text" :class="field" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">SHA / SHIF number</label>
                    <input v-model="form.sha_number" type="text" :class="field" />
                </div>
            </div>
        </section>

        <!-- Employment -->
        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Employment</h2>
            <p class="mt-1 text-sm text-gray-600">Where this employee works and under what terms.</p>

            <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Farm</label>
                    <select v-model="form.farm_id" :class="field">
                        <option value="">Unassigned</option>
                        <option v-for="f in farms" :key="f.id" :value="f.id">{{ f.name }}</option>
                    </select>
                    <p v-if="form.errors.farm_id" class="mt-1 text-sm text-red-600">{{ form.errors.farm_id }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Department</label>
                    <select v-model="form.department_id" :class="field">
                        <option value="">Unassigned</option>
                        <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Job title</label>
                    <select v-model="form.job_title_id" :class="field">
                        <option value="">Unassigned</option>
                        <option v-for="jt in availableJobTitles" :key="jt.id" :value="jt.id">{{ jt.name }}</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Line manager</label>
                    <select v-model="form.manager_id" :class="field">
                        <option value="">None</option>
                        <option v-for="e in employees" :key="e.id" :value="e.id">{{ employeeLabel(e) }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Employment type <span class="text-red-500">*</span></label>
                    <select v-model="form.employment_type" :class="field" required>
                        <option value="permanent">Permanent</option>
                        <option value="contract">Contract</option>
                        <option value="casual">Casual</option>
                        <option value="intern">Intern</option>
                    </select>
                    <p v-if="form.errors.employment_type" class="mt-1 text-sm text-red-600">{{ form.errors.employment_type }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Hire date <span class="text-red-500">*</span></label>
                    <input v-model="form.hire_date" type="date" :class="field" required />
                    <p v-if="form.errors.hire_date" class="mt-1 text-sm text-red-600">{{ form.errors.hire_date }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Probation end date</label>
                    <input v-model="form.probation_end_date" type="date" :class="field" />
                    <p v-if="form.errors.probation_end_date" class="mt-1 text-sm text-red-600">{{ form.errors.probation_end_date }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Contract end date</label>
                    <input v-model="form.contract_end_date" type="date" :class="field" />
                    <p v-if="form.errors.contract_end_date" class="mt-1 text-sm text-red-600">{{ form.errors.contract_end_date }}</p>
                </div>
                <div v-if="showStatus">
                    <label class="block text-sm font-medium text-gray-700">Status</label>
                    <select v-model="form.status" :class="field">
                        <option value="active">Active</option>
                        <option value="on_leave">On Leave</option>
                        <option value="suspended">Suspended</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
        </section>

        <!-- Tax exemption -->
        <section v-if="showStatus" class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Disability tax exemption</h2>
            <p class="mt-1 text-sm text-gray-600">
                Exempts the first KES 150,000 of monthly pay from PAYE. Requires a valid certificate.
            </p>

            <div class="mt-5 space-y-4">
                <label class="inline-flex items-center gap-2">
                    <input
                        v-model="form.has_disability_exemption"
                        type="checkbox"
                        class="rounded border-gray-300 text-[#1B5E20] shadow-sm focus:ring-[#4CAF50]"
                    />
                    <span class="text-sm text-gray-700">This employee has a disability exemption certificate</span>
                </label>

                <div v-if="form.has_disability_exemption" class="max-w-xs">
                    <label class="block text-sm font-medium text-gray-700">Certificate expiry date</label>
                    <input v-model="form.disability_certificate_expiry" type="date" :class="field" />
                </div>
            </div>
        </section>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3">
            <button
                v-if="inModal"
                type="button"
                @click="$emit('cancel')"
                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
                Cancel
            </button>
            <Link v-else :href="route('employees.index')" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Cancel
            </Link>
            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-lg bg-[#1B5E20] px-5 py-2 text-sm font-medium text-white transition hover:bg-[#2E7D32] disabled:opacity-50"
            >
                {{ form.processing ? 'Saving…' : submitLabel }}
            </button>
        </div>
    </form>
</template>
