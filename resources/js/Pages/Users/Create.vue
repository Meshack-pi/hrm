<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import UserForm from './Partials/UserForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    roles: { type: Array, default: () => [] },
});

const form = useForm({
    name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    roles: [],
    is_active: true,
});

function submit() {
    form.post(route('users.store'));
}
</script>

<template>
    <Head title="Add Team Member" />

    <AppLayout>
        <template #header>
            <div class="flex items-center gap-2 text-sm">
                <Link :href="route('users.index')" class="text-gray-500 hover:text-gray-700">Team Members</Link>
                <span class="text-gray-300">/</span>
                <span class="font-semibold text-gray-900">Add Team Member</span>
            </div>
        </template>

        <UserForm
            :form="form"
            :roles="roles"
            submit-label="Add Team Member"
            password-hint="Choose a password for this account. They can change it after signing in."
            @submit="submit"
        />
    </AppLayout>
</template>
