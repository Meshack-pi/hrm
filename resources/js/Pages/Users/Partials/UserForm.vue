<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    form: { type: Object, required: true },
    roles: { type: Array, default: () => [] },
    submitLabel: { type: String, default: 'Save' },
    passwordHint: { type: String, default: '' },
});

defineEmits(['submit']);

const inputClass =
    'mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-[#4CAF50] focus:ring-[#4CAF50]';

function toggleRole(form, name) {
    const index = form.roles.indexOf(name);
    if (index === -1) {
        form.roles.push(name);
    } else {
        form.roles.splice(index, 1);
    }
}
</script>

<template>
    <form @submit.prevent="$emit('submit')" class="max-w-3xl space-y-6">
        <!-- Account details -->
        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Account details</h2>
            <p class="mt-1 text-sm text-gray-600">Who this person is and how they sign in.</p>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Full name <span class="text-red-500">*</span></label>
                    <input v-model="form.name" type="text" :class="inputClass" required />
                    <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Email <span class="text-red-500">*</span></label>
                    <input v-model="form.email" type="email" :class="inputClass" required />
                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Phone</label>
                    <input v-model="form.phone" type="tel" placeholder="07XX XXX XXX" :class="inputClass" />
                    <p v-if="form.errors.phone" class="mt-1 text-sm text-red-600">{{ form.errors.phone }}</p>
                </div>

                <div class="flex items-end">
                    <label class="inline-flex items-center gap-2">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="rounded border-gray-300 text-[#1B5E20] shadow-sm focus:ring-[#4CAF50]"
                        />
                        <span class="text-sm text-gray-700">Account is active</span>
                    </label>
                </div>
            </div>
        </section>

        <!-- Password -->
        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Password</h2>
            <p class="mt-1 text-sm text-gray-600">{{ passwordHint || 'Set the sign-in password for this account.' }}</p>

            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Password</label>
                    <input v-model="form.password" type="password" autocomplete="new-password" :class="inputClass" />
                    <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Confirm password</label>
                    <input
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        :class="inputClass"
                    />
                </div>
            </div>
        </section>

        <!-- Roles -->
        <section class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold text-gray-900">Roles <span class="text-red-500">*</span></h2>
            <p class="mt-1 text-sm text-gray-600">
                Roles determine what this team member can see and do. At least one is required.
            </p>

            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                <label
                    v-for="r in roles"
                    :key="r.id"
                    :class="form.roles.includes(r.name) ? 'border-[#4CAF50] bg-green-50' : 'border-gray-200 hover:bg-gray-50'"
                    class="flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition"
                >
                    <input
                        type="checkbox"
                        :checked="form.roles.includes(r.name)"
                        @change="toggleRole(form, r.name)"
                        class="rounded border-gray-300 text-[#1B5E20] shadow-sm focus:ring-[#4CAF50]"
                    />
                    <span class="text-sm font-medium text-gray-900">{{ r.name }}</span>
                </label>
            </div>
            <p v-if="form.errors.roles" class="mt-2 text-sm text-red-600">{{ form.errors.roles }}</p>
        </section>

        <!-- Actions -->
        <div class="flex items-center justify-end gap-3">
            <Link :href="route('users.index')" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
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
