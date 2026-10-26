<script setup>
import { computed, ref } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import { usePermissions } from '@/composables/usePermissions';
import FlashMessages from '@/Components/FlashMessages.vue';

const { can, canAny, hasAnyRole } = usePermissions();
const page = usePage();
const user = page.props.auth.user;

function onRoute(pattern) {
    try {
        return route().current(pattern);
    } catch {
        return false;
    }
}

const sidebarOpen = ref(false);
const userMenuOpen = ref(false);
// Groups start expanded when you're already on one of their pages.
const settingsOpen = ref(onRoute('settings.*') || onRoute('users.*'));
const reportsOpen = ref(onRoute('reports.*'));

const reportSections = computed(() => page.props.reportSections ?? []);

const navigation = [
    { name: 'Dashboard', href: 'dashboard', icon: 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6', show: true },
    { name: 'Employees', href: 'employees.index', icon: 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zM12.75 12a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z', show: canAny('employees.view_all', 'employees.view_team') },
    { name: 'Payroll', href: 'payroll.runs.index', icon: 'M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z', show: canAny('payroll.create', 'payroll.calculate', 'payroll.approve', 'payroll.mark_paid', 'payroll.export') },
    { name: 'Leave', href: 'leave.requests.index', icon: 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z', show: can('leave.apply') },
    { name: 'Calendar', href: 'calendar.index', icon: 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5', show: true },
];

const settingsNav = [
    { name: 'Farms', href: 'settings.farms.index', show: can('settings.farms') },
    { name: 'Departments', href: 'settings.departments.index', show: canAny('settings.company', 'employees.create') },
    { name: 'Public Holidays', href: 'settings.holidays.index', show: can('holidays.manage') },
    { name: 'Team Members', href: 'users.index', show: can('users.view') },
];

function isActive(routeName) {
    try {
        return route().current(routeName) || route().current(routeName.replace('.index', '.*'));
    } catch {
        return false;
    }
}

function logout() {
    router.post(route('logout'));
}
</script>

<template>
    <div class="min-h-screen bg-gray-50">
        <FlashMessages />

        <!-- Mobile sidebar overlay -->
        <Transition
            enter-active-class="transition-opacity ease-linear duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity ease-linear duration-300"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="sidebarOpen" class="fixed inset-0 z-40 bg-black/50 lg:hidden" @click="sidebarOpen = false" />
        </Transition>

        <!-- Sidebar -->
        <aside
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
            class="fixed inset-y-0 left-0 z-50 w-64 bg-[#1B5E20] transition-transform duration-300 ease-in-out lg:translate-x-0"
        >
            <div class="flex h-full flex-col">
                <!-- Logo. The mark has dark green type on a transparent ground, so it sits
                     on a white panel to stay legible against the dark sidebar. -->
                <div class="flex h-20 items-center gap-3 px-4 border-b border-green-800">
                    <Link :href="route('dashboard')" class="flex items-center gap-3 w-full">
                        <div class="bg-white rounded-lg px-3 py-2 shadow-sm">
                            <img
                                src="/images/logo.png"
                                alt="Yegen Farms"
                                class="h-9 w-auto object-contain"
                            />
                        </div>
                        <div class="text-[10px] uppercase tracking-widest text-green-300 leading-tight">
                            HR &amp;<br />Payroll
                        </div>
                    </Link>
                </div>

                <!-- Navigation -->
                <nav class="flex-1 overflow-y-auto px-3 py-4 space-y-1">
                    <template v-for="item in navigation" :key="item.name">
                        <Link
                            v-if="item.show"
                            :href="route(item.href)"
                            :class="[
                                isActive(item.href)
                                    ? 'bg-green-800 text-white'
                                    : 'text-green-100 hover:bg-green-800/50 hover:text-white',
                                'group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors'
                            ]"
                        >
                            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" :d="item.icon" />
                            </svg>
                            {{ item.name }}
                        </Link>
                    </template>

                    <!-- Reports dropdown -->
                    <div v-if="reportSections.length">
                        <button
                            type="button"
                            @click="reportsOpen = !reportsOpen"
                            :aria-expanded="reportsOpen"
                            :class="onRoute('reports.*') ? 'bg-green-800/40 text-white' : 'text-green-100'"
                            class="w-full group flex items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-sm font-medium hover:bg-green-800/50 hover:text-white transition-colors"
                        >
                            <span class="flex items-center gap-3">
                                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
                                </svg>
                                Reports
                            </span>
                            <svg
                                :class="reportsOpen ? 'rotate-90' : ''"
                                class="h-4 w-4 transition-transform duration-200"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                        <div v-show="reportsOpen" class="ml-5 mt-1 space-y-0.5 border-l border-green-700/60 pl-3">
                            <Link
                                v-for="s in reportSections"
                                :key="s.key"
                                :href="route(s.route)"
                                :title="s.desc"
                                :class="[
                                    onRoute(s.route)
                                        ? 'text-white bg-green-800'
                                        : 'text-green-200 hover:text-white hover:bg-green-800/50',
                                    'block rounded-md px-3 py-2 text-sm transition-colors'
                                ]"
                            >
                                {{ s.label }}
                            </Link>
                        </div>
                    </div>

                    <!-- Settings dropdown -->
                    <div v-if="settingsNav.some(s => s.show)">
                        <button
                            type="button"
                            @click="settingsOpen = !settingsOpen"
                            :aria-expanded="settingsOpen"
                            :class="onRoute('settings.*') || onRoute('users.*') ? 'bg-green-800/40 text-white' : 'text-green-100'"
                            class="w-full group flex items-center justify-between gap-3 rounded-lg px-3 py-2.5 text-sm font-medium hover:bg-green-800/50 hover:text-white transition-colors"
                        >
                            <span class="flex items-center gap-3">
                                <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                Settings
                            </span>
                            <svg
                                :class="settingsOpen ? 'rotate-90' : ''"
                                class="h-4 w-4 transition-transform duration-200"
                                fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                            </svg>
                        </button>
                        <div v-show="settingsOpen" class="ml-5 mt-1 space-y-0.5 border-l border-green-700/60 pl-3">
                            <template v-for="sub in settingsNav" :key="sub.name">
                                <Link
                                    v-if="sub.show"
                                    :href="route(sub.href)"
                                    :class="[
                                        isActive(sub.href)
                                            ? 'text-white bg-green-800'
                                            : 'text-green-200 hover:text-white hover:bg-green-800/50',
                                        'block rounded-md px-3 py-2 text-sm transition-colors'
                                    ]"
                                >
                                    {{ sub.name }}
                                </Link>
                            </template>
                        </div>
                    </div>

                </nav>

                <!-- User info at bottom of sidebar -->
                <div class="border-t border-green-800 p-4">
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-green-700 text-sm font-bold text-white">
                            {{ user.name.charAt(0).toUpperCase() }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="truncate text-sm font-medium text-white">{{ user.name }}</p>
                            <p class="truncate text-xs text-green-300">{{ user.email }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Main content -->
        <div class="lg:pl-64">
            <!-- Top bar -->
            <header class="sticky top-0 z-30 flex h-16 items-center gap-4 border-b border-gray-200 bg-white px-4 shadow-sm sm:px-6">
                <button @click="sidebarOpen = true" class="lg:hidden text-gray-600 hover:text-gray-900">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                <div class="flex-1">
                    <slot name="header" />
                </div>

                <!-- User dropdown -->
                <div class="relative">
                    <button @click="userMenuOpen = !userMenuOpen" class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full bg-[#1B5E20] text-xs font-bold text-white">
                            {{ user.name.charAt(0).toUpperCase() }}
                        </div>
                        <span class="hidden sm:block font-medium">{{ user.name }}</span>
                        <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>

                    <Transition
                        enter-active-class="transition ease-out duration-100"
                        enter-from-class="transform opacity-0 scale-95"
                        enter-to-class="transform opacity-100 scale-100"
                        leave-active-class="transition ease-in duration-75"
                        leave-from-class="transform opacity-100 scale-100"
                        leave-to-class="transform opacity-0 scale-95"
                    >
                        <div v-if="userMenuOpen" @click="userMenuOpen = false" class="absolute right-0 mt-2 w-48 rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                            <Link :href="route('profile.edit')" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                Profile
                            </Link>
                            <button @click="logout" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                Log Out
                            </button>
                        </div>
                    </Transition>
                </div>
            </header>

            <!-- Page content -->
            <main class="p-4 sm:p-6 lg:p-8">
                <slot />
            </main>
        </div>
    </div>
</template>
