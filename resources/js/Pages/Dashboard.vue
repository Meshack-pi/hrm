<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { usePermissions } from '@/composables/usePermissions';
import { computed } from 'vue';

const props = defineProps({
    stats: { type: Object, default: () => ({}) },
    payroll: { type: Object, default: null },
    pendingApprovals: { type: Array, default: null },
    onLeaveToday: { type: Array, default: null },
    contractsEnding: { type: Array, default: null },
    me: { type: Object, default: null },
    nextHoliday: { type: Object, default: null },
});

const { can, canAny } = usePermissions();
const user = usePage().props.auth.user;

const firstName = computed(() => user.name.split(' ')[0]);

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 17) return 'Good afternoon';
    return 'Good evening';
});

const whole = (v) => Number(v ?? 0).toLocaleString('en-KE', { maximumFractionDigits: 0 });
const money = (v) => Number(v ?? 0).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const days = (v) => Number(v ?? 0).toLocaleString('en-KE', { maximumFractionDigits: 2 });
const shortDate = (d) => new Date(d).toLocaleDateString('en-KE', { day: 'numeric', month: 'short' });

const icons = {
    people: 'M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z',
    farm: 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z',
    calendar: 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5',
    user: 'M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z',
};

const statCards = computed(() => {
    const s = props.stats ?? {};
    return [
        s.employees && {
            label: s.employees.scope === 'team' ? 'My Team' : 'Total Employees',
            value: whole(s.employees.total),
            sub: `${whole(s.employees.active)} active · ${whole(s.employees.onLeave)} on leave`,
            href: route('employees.index'),
            icon: icons.people,
        },
        s.farms && {
            label: 'Active Farms',
            value: whole(s.farms.active),
            sub: s.farms.breakdown.map((f) => `${f.code} ${f.employees}`).join(' · '),
            href: can('settings.farms') ? route('settings.farms.index') : route('reports.index'),
            icon: icons.farm,
        },
        s.pendingLeave !== null && s.pendingLeave !== undefined && {
            label: 'Leave Awaiting Approval',
            value: whole(s.pendingLeave),
            sub: s.pendingLeave ? 'Needs your review' : 'All caught up',
            href: route('leave.requests.index', { status: 'pending' }),
            icon: icons.calendar,
        },
        s.users && {
            label: 'Team Members',
            value: whole(s.users.total),
            sub: `${whole(s.users.active)} active`,
            href: route('users.index'),
            icon: icons.user,
        },
    ].filter(Boolean);
});

const run = computed(() => props.payroll?.run ?? null);

const quickActions = computed(() => [
    { label: 'Apply for Leave', href: route('leave.requests.index'), show: !!props.me && can('leave.apply'), desc: 'Request time off' },
    { label: 'Add Employee', href: route('employees.create'), show: can('employees.create'), desc: 'Register a new farm worker' },
    { label: 'Payroll Runs', href: route('payroll.runs.index'), show: canAny('payroll.create', 'payroll.approve', 'payroll.export'), desc: 'Prepare, review and approve pay' },
    { label: 'Reports', href: route('reports.index'), show: (usePage().props.reportSections ?? []).length > 0, desc: 'Headcount, payroll and leave' },
    { label: 'View Employees', href: route('employees.index'), show: canAny('employees.view_all', 'employees.view_team'), desc: 'Browse and filter staff records' },
    { label: 'Manage Team Members', href: route('users.index'), show: can('users.view'), desc: 'Add team members and assign roles' },
].filter((a) => a.show).slice(0, 4));

const card = 'rounded-xl border border-gray-200 bg-white p-5 shadow-sm';
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-gray-900">Dashboard</h1>
        </template>

        <!-- Welcome banner -->
        <div class="rounded-xl bg-gradient-to-r from-[#1B5E20] to-[#4CAF50] p-6 text-white shadow-sm sm:p-8">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-2xl font-bold">{{ greeting }}, {{ firstName }}</h2>
                    <p class="mt-1 text-green-50">Welcome to the Yegen Farms HR &amp; Payroll system.</p>
                </div>
                <Link
                    v-if="nextHoliday"
                    :href="route('calendar.index', { month: nextHoliday.date.slice(0, 7) })"
                    class="rounded-lg bg-white/15 px-4 py-2 text-sm backdrop-blur transition hover:bg-white/25"
                >
                    <span class="block text-xs uppercase tracking-wide text-green-100">Next public holiday</span>
                    <span class="font-semibold">{{ nextHoliday.name }}</span>
                    <span class="text-green-50">
                        · {{ new Date(`${nextHoliday.date}T00:00:00`).toLocaleDateString('en-KE', { weekday: 'short', day: 'numeric', month: 'short' }) }}
                        ({{ nextHoliday.daysAway === 0 ? 'today' : nextHoliday.daysAway === 1 ? 'tomorrow' : `in ${nextHoliday.daysAway} days` }})
                    </span>
                </Link>
            </div>
        </div>

        <!-- Payroll needs attention -->
        <Link
            v-if="payroll?.awaitingMyApproval"
            :href="route('payroll.runs.show', run.id)"
            class="mt-6 flex items-center justify-between gap-4 rounded-xl border border-amber-200 bg-amber-50 p-4 transition hover:border-amber-300"
        >
            <div>
                <p class="text-sm font-semibold text-amber-900">{{ run.period }} payroll is waiting for your approval</p>
                <p class="mt-0.5 text-sm text-amber-800">
                    {{ run.payslips }} payslips · net KES {{ whole(run.net) }} · calculated by {{ run.calculatedBy }}
                </p>
            </div>
            <span class="shrink-0 rounded-lg bg-[#1B5E20] px-4 py-2 text-sm font-medium text-white">Review</span>
        </Link>

        <!-- Stats -->
        <div v-if="statCards.length" class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Link
                v-for="c in statCards"
                :key="c.label"
                :href="c.href"
                class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-[#4CAF50] hover:shadow-md"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-600">{{ c.label }}</p>
                        <p class="mt-2 text-3xl font-bold tabular-nums text-gray-900">{{ c.value }}</p>
                        <p class="mt-1 truncate text-xs text-gray-500">{{ c.sub }}</p>
                    </div>
                    <span class="shrink-0 rounded-lg bg-green-50 p-2 text-[#1B5E20] transition group-hover:bg-[#1B5E20] group-hover:text-white">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" :d="c.icon" />
                        </svg>
                    </span>
                </div>
            </Link>
        </div>

        <!-- My leave & pay (anyone linked to an employee record) -->
        <div v-if="me" class="mt-6 grid gap-4 lg:grid-cols-3">
            <div :class="card" class="lg:col-span-2">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">My leave balance · {{ new Date().getFullYear() }}</h3>
                    <Link :href="route('leave.requests.index')" class="text-sm font-medium text-[#1B5E20] hover:underline">
                        {{ me.pendingRequests ? `${me.pendingRequests} pending` : 'Apply' }}
                    </Link>
                </div>
                <div v-if="me.leaveBalances.length" class="mt-4 grid gap-3 sm:grid-cols-2">
                    <div v-for="b in me.leaveBalances" :key="b.code" class="rounded-lg bg-gray-50 p-3">
                        <p class="text-xs font-medium text-gray-600">{{ b.type }}</p>
                        <p class="mt-1 text-2xl font-semibold tabular-nums text-gray-900">
                            {{ days(b.balance) }} <span class="text-sm font-normal text-gray-500">days left</span>
                        </p>
                        <p class="mt-0.5 text-xs text-gray-500">
                            {{ days(b.taken) }} taken<span v-if="b.pending"> · {{ days(b.pending) }} pending</span>
                        </p>
                    </div>
                </div>
                <p v-else class="mt-4 text-sm text-gray-500">
                    No leave entitlement yet — annual leave starts after 12 months of service.
                </p>
            </div>

            <div :class="card" class="flex flex-col">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">My latest payslip</h3>
                    <StatusBadge v-if="me.latestPayslip" :status="me.latestPayslip.paymentStatus" size="xs" />
                </div>
                <template v-if="me.latestPayslip">
                    <p class="mt-1 text-xs text-gray-500">{{ me.latestPayslip.period }}</p>
                    <p class="mt-3 text-3xl font-bold tabular-nums text-[#1B5E20]">KES {{ money(me.latestPayslip.net) }}</p>
                    <dl class="mt-3 space-y-1 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-600">Gross pay</dt><dd class="tabular-nums text-gray-900">{{ money(me.latestPayslip.gross) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-600">Tax &amp; statutory</dt><dd class="tabular-nums text-gray-900">−{{ money(me.latestPayslip.statutory) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-gray-600">Other deductions</dt><dd class="tabular-nums text-gray-900">−{{ money(me.latestPayslip.other) }}</dd></div>
                    </dl>
                    <a
                        :href="route('payslips.download', me.latestPayslip.id)"
                        class="mt-4 inline-flex items-center justify-center gap-2 rounded-lg bg-[#1B5E20] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#2E7D32]"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg>
                        Download PDF
                    </a>
                    <a
                        :href="route('payslips.download', { payslip: me.latestPayslip.id, inline: 1 })"
                        target="_blank"
                        rel="noopener"
                        class="mt-2 text-center text-xs font-medium text-[#1B5E20] hover:underline"
                    >
                        View full breakdown
                    </a>
                </template>
                <p v-else class="mt-3 text-sm text-gray-500">No approved payslips yet.</p>
            </div>
        </div>

        <!-- Payslip history -->
        <div v-if="me && me.payslips.length > 1" :class="card" class="mt-4 !p-0">
            <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3">
                <h3 class="text-sm font-semibold text-gray-900">My payslips</h3>
                <span class="text-xs text-gray-500">Last {{ me.payslips.length }} months</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-600">
                        <tr>
                            <th class="px-5 py-2">Month</th>
                            <th class="px-5 py-2 text-right">Gross</th>
                            <th class="px-5 py-2 text-right">Deductions</th>
                            <th class="px-5 py-2 text-right">Net pay</th>
                            <th class="px-5 py-2">Status</th>
                            <th class="px-5 py-2" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="p in me.payslips" :key="p.id" class="hover:bg-gray-50">
                            <td class="px-5 py-2.5 font-medium text-gray-900">{{ p.period }}</td>
                            <td class="px-5 py-2.5 text-right tabular-nums text-gray-600">{{ money(p.gross) }}</td>
                            <td class="px-5 py-2.5 text-right tabular-nums text-gray-600">{{ money(p.deductions) }}</td>
                            <td class="px-5 py-2.5 text-right font-medium tabular-nums text-gray-900">{{ money(p.net) }}</td>
                            <td class="px-5 py-2.5"><StatusBadge :status="p.paymentStatus" size="xs" /></td>
                            <td class="px-5 py-2.5 text-right">
                                <a :href="route('payslips.download', p.id)" class="inline-flex items-center gap-1 text-sm font-medium text-[#1B5E20] hover:underline">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                    </svg>
                                    PDF
                                </a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-6 grid gap-4 lg:grid-cols-2">
            <!-- Latest payroll run -->
            <div v-if="payroll" :class="card">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">Latest payroll</h3>
                    <Link :href="route('payroll.runs.index')" class="text-sm font-medium text-[#1B5E20] hover:underline">All runs</Link>
                </div>
                <template v-if="run">
                    <Link :href="route('payroll.runs.show', run.id)" class="mt-3 block rounded-lg p-1 -m-1 hover:bg-gray-50">
                        <div class="flex items-center gap-2">
                            <p class="text-lg font-semibold text-gray-900">{{ run.period }}</p>
                            <StatusBadge :status="run.status" />
                        </div>
                        <dl class="mt-3 grid grid-cols-3 gap-3 text-sm">
                            <div><dt class="text-xs text-gray-500">Gross</dt><dd class="font-medium tabular-nums text-gray-900">{{ whole(run.gross) }}</dd></div>
                            <div><dt class="text-xs text-gray-500">Net pay</dt><dd class="font-medium tabular-nums text-[#1B5E20]">{{ whole(run.net) }}</dd></div>
                            <div><dt class="text-xs text-gray-500">Employer cost</dt><dd class="font-medium tabular-nums text-gray-900">{{ whole(run.employerCost) }}</dd></div>
                        </dl>
                    </Link>
                    <p v-if="payroll.awaitingOtherApprover" class="mt-3 text-xs text-gray-500">
                        You calculated this run — it needs approval from a different user.
                    </p>
                    <p v-else-if="payroll.pendingPayment" class="mt-3 text-xs text-gray-500">
                        Approved and locked — waiting to be marked as paid.
                    </p>
                </template>
                <p v-else class="mt-3 text-sm text-gray-500">No payroll runs yet.</p>
            </div>

            <!-- Pending leave approvals -->
            <div v-if="pendingApprovals" :class="card">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-900">Leave awaiting approval</h3>
                    <Link :href="route('leave.requests.index', { status: 'pending' })" class="text-sm font-medium text-[#1B5E20] hover:underline">
                        View all
                    </Link>
                </div>
                <ul v-if="pendingApprovals.length" class="mt-3 divide-y divide-gray-100">
                    <li v-for="r in pendingApprovals" :key="r.id" class="flex items-center justify-between gap-3 py-2.5">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-900">{{ r.employee }}</p>
                            <p class="truncate text-xs text-gray-500">{{ r.type }} · {{ r.farm ?? '—' }}</p>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-sm tabular-nums text-gray-900">{{ shortDate(r.start) }} – {{ shortDate(r.end) }}</p>
                            <p class="text-xs text-gray-500">{{ days(r.days) }} day(s)</p>
                        </div>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-gray-500">Nothing waiting — all caught up.</p>
            </div>

            <!-- On leave today -->
            <div v-if="onLeaveToday" :class="card">
                <h3 class="text-sm font-semibold text-gray-900">On leave today</h3>
                <ul v-if="onLeaveToday.length" class="mt-3 divide-y divide-gray-100">
                    <li v-for="r in onLeaveToday" :key="r.id" class="flex items-center justify-between gap-3 py-2.5">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-gray-900">{{ r.employee }}</p>
                            <p class="truncate text-xs text-gray-500">{{ r.type }} · {{ r.farm ?? '—' }}</p>
                        </div>
                        <p class="shrink-0 text-xs text-gray-500">Back {{ shortDate(r.returns) }}</p>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-gray-500">Everyone is in today.</p>
            </div>

            <!-- Contracts ending -->
            <div v-if="contractsEnding" :class="card">
                <h3 class="text-sm font-semibold text-gray-900">Contracts ending in 60 days</h3>
                <ul v-if="contractsEnding.length" class="mt-3 divide-y divide-gray-100">
                    <li v-for="c in contractsEnding" :key="c.id">
                        <Link :href="route('employees.show', c.uuid)" class="flex items-center justify-between gap-3 py-2.5 hover:bg-gray-50">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-gray-900">{{ c.name }}</p>
                                <p class="truncate text-xs text-gray-500">{{ c.title }} · {{ c.farm ?? '—' }}</p>
                            </div>
                            <span
                                :class="c.daysLeft <= 14 ? 'bg-red-100 text-red-800' : 'bg-amber-100 text-amber-800'"
                                class="shrink-0 rounded-full px-2 py-1 text-xs font-medium"
                            >
                                {{ c.daysLeft }}d · {{ shortDate(c.ends) }}
                            </span>
                        </Link>
                    </li>
                </ul>
                <p v-else class="mt-3 text-sm text-gray-500">No contracts ending soon.</p>
            </div>
        </div>

        <!-- Quick actions -->
        <div v-if="quickActions.length" class="mt-8">
            <h3 class="text-base font-semibold text-gray-900">Quick actions</h3>
            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Link
                    v-for="a in quickActions"
                    :key="a.label"
                    :href="a.href"
                    class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-[#4CAF50] hover:shadow-md"
                >
                    <p class="font-semibold text-gray-900">{{ a.label }}</p>
                    <p class="mt-1 text-sm text-gray-600">{{ a.desc }}</p>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
