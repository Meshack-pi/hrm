<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const isPasswordVisible = ref(false);
const isCapsLockOn = ref(false);

const detectCapsLock = (event) => {
    if (typeof event.getModifierState === 'function') {
        isCapsLockOn.value = event.getModifierState('CapsLock');
    }
};

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <div class="flex min-h-screen bg-gray-100">
        <Head title="Log in" />

        <main
            class="flex w-full flex-col items-center justify-center px-4 py-10 sm:px-6"
        >
            <div class="w-full max-w-md">
                <div class="mb-8 flex justify-center">
                    <Link href="/">
                        <ApplicationLogo class="h-20 w-auto" />
                    </Link>
                </div>

                <div class="rounded-2xl bg-white px-6 py-8 shadow-md sm:px-10">
                    <div class="mb-6">
                        <h1 class="text-2xl font-bold text-gray-900">
                            Welcome back
                        </h1>
                        <p class="mt-1 text-sm text-gray-600">
                            Sign in with your work email to continue.
                        </p>
                    </div>

                    <div
                        v-if="status"
                        class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-800"
                        role="status"
                    >
                        {{ status }}
                    </div>

                    <form class="space-y-5" @submit.prevent="submit">
                        <div>
                            <InputLabel for="email" value="Email address" />

                            <TextInput
                                id="email"
                                type="email"
                                inputmode="email"
                                class="mt-1 block w-full focus:!border-green-600 focus:!ring-green-600"
                                :class="{
                                    '!border-red-500': form.errors.email,
                                }"
                                v-model="form.email"
                                placeholder="you@yegenfarms.com"
                                required
                                autofocus
                                autocomplete="username"
                                :aria-invalid="!!form.errors.email"
                                aria-describedby="email-error"
                            />

                            <InputError
                                id="email-error"
                                class="mt-2"
                                :message="form.errors.email"
                            />
                        </div>

                        <div>
                            <div class="flex items-center justify-between">
                                <InputLabel for="password" value="Password" />

                                <Link
                                    v-if="canResetPassword"
                                    :href="route('password.request')"
                                    class="rounded text-sm font-medium text-green-700 hover:text-green-900 focus:outline-none focus:ring-2 focus:ring-green-600 focus:ring-offset-2"
                                >
                                    Forgot password?
                                </Link>
                            </div>

                            <div class="relative mt-1">
                                <TextInput
                                    id="password"
                                    :type="isPasswordVisible ? 'text' : 'password'"
                                    class="block w-full pe-12 focus:!border-green-600 focus:!ring-green-600"
                                    :class="{
                                        '!border-red-500': form.errors.password,
                                    }"
                                    v-model="form.password"
                                    placeholder="Enter your password"
                                    required
                                    autocomplete="current-password"
                                    :aria-invalid="!!form.errors.password"
                                    aria-describedby="password-error password-caps-lock"
                                    @keyup="detectCapsLock"
                                    @keydown="detectCapsLock"
                                    @blur="isCapsLockOn = false"
                                />

                                <button
                                    type="button"
                                    class="absolute inset-y-0 end-0 flex items-center rounded-e-md px-3 text-gray-500 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-green-600"
                                    :aria-label="
                                        isPasswordVisible
                                            ? 'Hide password'
                                            : 'Show password'
                                    "
                                    :aria-pressed="isPasswordVisible"
                                    @click="isPasswordVisible = !isPasswordVisible"
                                >
                                    <svg
                                        v-if="isPasswordVisible"
                                        class="h-5 w-5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88"
                                        />
                                    </svg>
                                    <svg
                                        v-else
                                        class="h-5 w-5"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        aria-hidden="true"
                                    >
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z"
                                        />
                                        <path
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                                        />
                                    </svg>
                                </button>
                            </div>

                            <p
                                v-if="isCapsLockOn"
                                id="password-caps-lock"
                                class="mt-2 flex items-center gap-1.5 text-sm text-amber-700"
                                role="status"
                            >
                                <svg
                                    class="h-4 w-4"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 9v3.75m0 3.75h.008M10.34 3.94L1.82 18a1.875 1.875 0 001.6 2.81h17.16a1.875 1.875 0 001.6-2.81L13.66 3.94a1.875 1.875 0 00-3.32 0z"
                                    />
                                </svg>
                                Caps Lock is on
                            </p>

                            <InputError
                                id="password-error"
                                class="mt-2"
                                :message="form.errors.password"
                            />
                        </div>

                        <label class="flex cursor-pointer items-center gap-2">
                            <Checkbox
                                name="remember"
                                v-model:checked="form.remember"
                                class="!text-green-700 focus:!ring-green-600"
                            />
                            <span class="text-sm text-gray-700">
                                Keep me signed in on this device
                            </span>
                        </label>

                        <button
                            type="submit"
                            class="flex w-full items-center justify-center gap-2 rounded-lg bg-green-800 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-600 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-70"
                            :disabled="form.processing"
                        >
                            <svg
                                v-if="form.processing"
                                class="h-4 w-4 animate-spin"
                                fill="none"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                />
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                                />
                            </svg>
                            {{ form.processing ? 'Signing in…' : 'Sign in' }}
                        </button>
                    </form>
                </div>

                <p class="mt-6 text-center text-sm text-gray-600">
                    Having trouble signing in? Contact your HR administrator.
                </p>
            </div>
        </main>
    </div>
</template>
