<script setup>
import Modal from '@/Components/Modal.vue';
import EmployeeForm from './EmployeeForm.vue';
import { useForm } from '@inertiajs/vue3';

// `editForm` is the server's optional `editForm` prop: { employee, farms, departments, jobTitles, employees }.
const props = defineProps({
    show: { type: Boolean, default: false },
    editForm: { type: Object, required: true },
});

const emit = defineEmits(['close']);

// Dates arrive as ISO strings; <input type="date"> needs YYYY-MM-DD.
function toDateInput(value) {
    return value ? String(value).slice(0, 10) : '';
}

const e = props.editForm.employee;

const form = useForm({
    first_name: e.first_name ?? '',
    middle_name: e.middle_name ?? '',
    last_name: e.last_name ?? '',
    national_id: e.national_id ?? '',
    passport_number: e.passport_number ?? '',
    kra_pin: e.kra_pin ?? '',
    nssf_number: e.nssf_number ?? '',
    sha_number: e.sha_number ?? '',
    date_of_birth: toDateInput(e.date_of_birth),
    gender: e.gender ?? '',
    marital_status: e.marital_status ?? '',
    phone: e.phone ?? '',
    personal_email: e.personal_email ?? '',
    work_email: e.work_email ?? '',
    physical_address: e.physical_address ?? '',
    county: e.county ?? '',
    farm_id: e.farm_id ?? '',
    department_id: e.department_id ?? '',
    job_title_id: e.job_title_id ?? '',
    manager_id: e.manager_id ?? '',
    employment_type: e.employment_type ?? 'permanent',
    hire_date: toDateInput(e.hire_date),
    probation_end_date: toDateInput(e.probation_end_date),
    contract_end_date: toDateInput(e.contract_end_date),
    status: e.status ?? 'active',
    has_disability_exemption: !!e.has_disability_exemption,
    disability_certificate_expiry: toDateInput(e.disability_certificate_expiry),
});

function submit() {
    form.transform(data => ({
        ...data,
        kra_pin: data.kra_pin ? data.kra_pin.toUpperCase() : '',
    })).put(route('employees.update', e.uuid), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <Modal :show="show" max-width="5xl" @close="emit('close')">
        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Edit {{ e.first_name }} {{ e.last_name }}</h2>
                <p class="font-mono text-xs text-gray-500">{{ e.employee_number }}</p>
            </div>
            <button type="button" @click="emit('close')" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100" aria-label="Close">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
            </button>
        </div>

        <div class="bg-gray-50 p-4 sm:p-6">
            <EmployeeForm
                :form="form"
                :farms="editForm.farms"
                :departments="editForm.departments"
                :job-titles="editForm.jobTitles"
                :employees="editForm.employees"
                submit-label="Save Changes"
                show-status
                in-modal
                @submit="submit"
                @cancel="emit('close')"
            />
        </div>
    </Modal>
</template>
