<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import UserForm from './Partials/UserForm.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    user: { type: Object, required: true },
    roles: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    phone: props.user.phone ?? '',
    password: '',
    password_confirmation: '',
    roles: props.user.roles?.map(r => r.name) ?? [],
    is_active: !!props.user.is_active,
});

function submit() {
    form.put(route('users.update', props.user.id));
}
</script>

<template>
    <Head :title="`Edit ${user.name}`" />

    <AppLayout>
        <template #header>
            <div class="flex items-center gap-2 text-sm">
                <Link :href="route('users.index')" class="text-gray-500 hover:text-gray-700">Team Members</Link>
                <span class="text-gray-300">/</span>
                <span class="font-semibold text-gray-900">{{ user.name }}</span>
            </div>
        </template>

        <UserForm
            :form="form"
            :roles="roles"
            submit-label="Save Changes"
            password-hint="Leave blank to keep the current password."
            @submit="submit"
        />
    </AppLayout>
</template>
