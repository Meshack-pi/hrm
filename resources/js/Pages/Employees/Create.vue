<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import EmployeeForm from './Partials/EmployeeForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    farms: { type: Array, default: () => [] },
    departments: { type: Array, default: () => [] },
    jobTitles: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
});

const form = useForm({
    first_name: '',
    middle_name: '',
    last_name: '',
    national_id: '',
    passport_number: '',
    kra_pin: '',
    nssf_number: '',
    sha_number: '',
    date_of_birth: '',
    gender: '',
    marital_status: '',
    phone: '',
    personal_email: '',
    work_email: '',
    physical_address: '',
    county: '',
    farm_id: '',
    department_id: '',
    job_title_id: '',
    manager_id: '',
    employment_type: 'permanent',
    hire_date: '',
    probation_end_date: '',
    contract_end_date: '',
});

function submit() {
    form.transform(data => ({
        ...data,
        kra_pin: data.kra_pin ? data.kra_pin.toUpperCase() : '',
    })).post(route('employees.store'));
}
</script>

<template>
    <Head title="Add Employee" />

    <AppLayout>
        <template #header>
            <div class="flex items-center gap-2 text-sm">
                <Link :href="route('employees.index')" class="text-gray-500 hover:text-gray-700">Employees</Link>
                <span class="text-gray-300">/</span>
                <span class="font-semibold text-gray-900">Add Employee</span>
            </div>
        </template>

        <EmployeeForm
            :form="form"
            :farms="farms"
            :departments="departments"
            :job-titles="jobTitles"
            :employees="employees"
            submit-label="Create Employee"
            @submit="submit"
        />
    </AppLayout>
</template>
