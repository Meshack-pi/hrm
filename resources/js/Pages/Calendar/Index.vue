<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps({
    month: { type: String, required: true }, // YYYY-MM
    range: { type: Object, required: true },
    events: { type: Array, default: () => [] },
    upcoming: { type: Array, default: () => [] },
    farms: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    categories: { type: Object, default: () => ({}) },
    leaveScope: { type: String, default: 'own' },
    holidaysSyncedAt: { type: String, default: null },
    can: { type: Object, default: () => ({}) },
});

// ---- dates (all as YYYY-MM-DD strings to avoid timezone drift) -------------
const pad = (n) => String(n).padStart(2, '0');
const iso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const parse = (s) => {
    const [y, m, d] = s.split('-').map(Number);
    return new Date(y, m - 1, d);
};
const todayIso = iso(new Date());

const monthDate = computed(() => parse(`${props.month}-01`));
const monthLabel = computed(() => monthDate.value.toLocaleDateString('en-KE', { month: 'long', year: 'numeric' }));

function shiftMonth(delta) {
    const d = monthDate.value;
    const next = new Date(d.getFullYear(), d.getMonth() + delta, 1);
    go(`${next.getFullYear()}-${pad(next.getMonth() + 1)}`);
}

const farmId = ref(props.filters.farm_id ?? '');

function go(month = props.month) {
    router.get(
        route('calendar.index'),
        { month, farm_id: farmId.value || undefined },
        { preserveState: true, preserveScroll: true, replace: true, only: ['month', 'range', 'events', 'upcoming', 'filters'] }
    );
}
watch(farmId, () => go());

// ---- layers ------------------------------------------------------------------
const layers = reactive({ holiday: true, event: true, leave: true, payroll: true });

const layerDefs = computed(() => [
    { key: 'holiday', label: 'Public holidays', dot: 'bg-rose-500' },
    { key: 'event', label: 'Company events', dot: 'bg-sky-500' },
    { key: 'leave', label: { all: 'All leave', team: 'Team leave', own: 'My leave' }[props.leaveScope], dot: 'bg-amber-500' },
    ...(props.can.seePayroll ? [{ key: 'payroll', label: 'Payroll deadlines', dot: 'bg-purple-500' }] : []),
]);

const visible = computed(() => props.events.filter((e) => layers[e.type]));

// ---- grid ----------------------------------------------------------------------
const weekdays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

const days = computed(() => {
    const out = [];
    const end = parse(props.range.to);
    for (let d = parse(props.range.from); d <= end; d = new Date(d.getFullYear(), d.getMonth(), d.getDate() + 1)) {
        const key = iso(d);
        const items = visible.value
            .filter((e) => e.start <= key && e.end >= key)
            .sort((a, b) => order[a.type] - order[b.type] || (a.time ?? '').localeCompare(b.time ?? ''));
        out.push({
            key,
            day: d.getDate(),
            inMonth: d.getMonth() === monthDate.value.getMonth(),
            isToday: key === todayIso,
            isWeekend: d.getDay() === 0 || d.getDay() === 6,
            isHoliday: items.some((e) => e.type === 'holiday'),
            items,
        });
    }
    return out;
});

const dayMap = computed(() => Object.fromEntries(days.value.map((d) => [d.key, d])));

const order = { holiday: 0, payroll: 1, event: 2, leave: 3 };

const chipClass = (e) => {
    if (e.type === 'holiday') return 'bg-rose-100 text-rose-800';
    if (e.type === 'payroll') return 'bg-purple-100 text-purple-800';
    if (e.type === 'leave') {
        return e.meta.status === 'pending'
            ? 'border border-dashed border-amber-400 bg-amber-50 text-amber-800'
            : 'bg-amber-100 text-amber-900';
    }
    return {
        meeting: 'bg-sky-100 text-sky-800',
        training: 'bg-indigo-100 text-indigo-800',
        farm_activity: 'bg-green-100 text-green-800',
        deadline: 'bg-orange-100 text-orange-800',
        celebration: 'bg-pink-100 text-pink-800',
    }[e.meta.category] ?? 'bg-gray-100 text-gray-800';
};

const accentClass = (e) => {
    if (e.type !== 'event') return { holiday: 'bg-rose-500', payroll: 'bg-purple-500', leave: 'bg-amber-500' }[e.type];
    return {
        meeting: 'bg-sky-500',
        training: 'bg-indigo-500',
        farm_activity: 'bg-green-500',
        deadline: 'bg-orange-500',
        celebration: 'bg-pink-500',
    }[e.meta.category] ?? 'bg-gray-400';
};

const MAX_CHIPS = 3;
const MAX_BARS = 3;

// ---- phone views (month / week / day) -------------------------------------------
const views = [
    { key: 'month', label: 'Month' },
    { key: 'week', label: 'Week' },
    { key: 'day', label: 'Day' },
];
const view = ref('month');

const addDays = (s, n) => {
    const d = parse(s);
    return iso(new Date(d.getFullYear(), d.getMonth(), d.getDate() + n));
};
const defaultDay = (month) => (todayIso.startsWith(month) ? todayIso : `${month}-01`);

const selectedDate = ref(defaultDay(props.month));
watch(() => props.month, (m) => {
    if (!selectedDate.value.startsWith(m)) selectedDate.value = defaultDay(m);
});

const emptyDay = (key) => ({ key, day: parse(key).getDate(), isToday: key === todayIso, items: [] });
const selectedDay = computed(() => dayMap.value[selectedDate.value] ?? emptyDay(selectedDate.value));

const weekDays = computed(() => {
    const monday = addDays(selectedDate.value, -((parse(selectedDate.value).getDay() + 6) % 7));
    return Array.from({ length: 7 }, (_, i) => {
        const key = addDays(monday, i);
        return dayMap.value[key] ?? emptyDay(key);
    });
});

const heading = computed(() => {
    if (view.value === 'week') {
        const [first, last] = [weekDays.value[0].key, weekDays.value[6].key];
        return `${fmtShort(first)} – ${fmtShort(last)} ${parse(last).getFullYear()}`;
    }
    return view.value === 'day' ? fmtLong(selectedDate.value) : monthLabel.value;
});

/** Selecting a day outside the loaded month fetches that month; the selection survives the reload. */
function selectDate(key) {
    selectedDate.value = key;
    if (!key.startsWith(props.month)) go(key.slice(0, 7));
}

function shift(delta) {
    if (view.value === 'month') {
        shiftMonth(delta);
    } else if (view.value === 'week') {
        selectDate(addDays(selectedDate.value, delta * 7));
    } else {
        stepDay(delta);
    }
}

function openDay(key) {
    selectDate(key);
    view.value = 'day';
}

// ---- day pane: a swipeable sheet over the month grid, or inline in day view ----
const dayPaneOpen = ref(false);
const slideDir = ref(1);
const selectedDayLoaded = computed(() => !!dayMap.value[selectedDate.value]);

watch(view, () => (dayPaneOpen.value = false));

function openDayPane(key) {
    selectDate(key);
    dayPaneOpen.value = true;
}

function moveTo(key) {
    if (key === selectedDate.value) return;
    slideDir.value = key > selectedDate.value ? 1 : -1;
    selectDate(key);
}

const stepDay = (delta) => moveTo(addDays(selectedDate.value, delta));

let touchStart = null;
function onTouchStart(e) {
    const t = e.changedTouches[0];
    touchStart = { x: t.clientX, y: t.clientY };
}
function onTouchEnd(e) {
    if (!touchStart) return;
    const t = e.changedTouches[0];
    const [dx, dy] = [t.clientX - touchStart.x, t.clientY - touchStart.y];
    touchStart = null;
    // Mostly-horizontal swipes only, so scrolling a long day list doesn't change the day.
    if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5) stepDay(dx < 0 ? 1 : -1);
}

// The week/day views are phone-only, so fall back to the month grid on wider screens.
let desktopQuery;
const resetViewOnDesktop = (q) => {
    if (q.matches) view.value = 'month';
};
onMounted(() => {
    desktopQuery = window.matchMedia('(min-width: 768px)');
    resetViewOnDesktop(desktopQuery);
    desktopQuery.addEventListener('change', resetViewOnDesktop);
});
onBeforeUnmount(() => desktopQuery?.removeEventListener('change', resetViewOnDesktop));

// ---- detail / day modals ------------------------------------------------------
const selected = ref(null);
const dayOpen = ref(null);

function openEvent(e) {
    dayOpen.value = null;
    selected.value = e;
}

function onDayClick(day) {
    if (props.can.manageEvents) {
        openCreate(day.key);
    } else if (day.items.length) {
        dayOpen.value = day;
    }
}

const fmtLong = (s) => parse(s).toLocaleDateString('en-KE', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
const fmtShort = (s) => parse(s).toLocaleDateString('en-KE', { day: 'numeric', month: 'short' });
const span = (e) => (e.start === e.end ? fmtLong(e.start) : `${fmtShort(e.start)} – ${fmtLong(e.end)}`);

const typeLabel = { holiday: 'Public holiday', event: 'Company event', leave: 'Leave', payroll: 'Payroll' };

// ---- create / edit events -----------------------------------------------------
const editing = ref(null);
const showForm = ref(false);
const deleting = ref(null);

const form = useForm({
    title: '', description: '', category: 'meeting',
    start_date: '', end_date: '', start_time: '', end_time: '',
    location: '', farm_id: '',
});

function openCreate(date = todayIso) {
    editing.value = null;
    form.reset();
    form.clearErrors();
    form.start_date = date;
    form.end_date = date;
    showForm.value = true;
}

function openEdit(e) {
    selected.value = null;
    editing.value = e;
    form.clearErrors();
    Object.assign(form, {
        title: e.title,
        description: e.meta.description ?? '',
        category: e.meta.category,
        start_date: e.start,
        end_date: e.end,
        start_time: e.meta.startTime ?? '',
        end_time: e.meta.endTime ?? '',
        location: e.meta.location ?? '',
        farm_id: e.meta.farmId ?? '',
    });
    showForm.value = true;
}

watch(() => form.start_date, (v) => {
    if (v && (!form.end_date || form.end_date < v)) form.end_date = v;
});

function submit() {
    const opts = { preserveScroll: true, onSuccess: () => (showForm.value = false) };
    form.transform((d) => ({ ...d, farm_id: d.farm_id || null, start_time: d.start_time || null, end_time: d.end_time || null }));
    editing.value
        ? form.put(route('calendar.events.update', editing.value.meta.eventId), opts)
        : form.post(route('calendar.events.store'), opts);
}

function confirmDelete() {
    router.delete(route('calendar.events.destroy', deleting.value.meta.eventId), {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = null;
            selected.value = null;
        },
    });
}

const field = 'mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-[#4CAF50] focus:ring-[#4CAF50]';
</script>

<template>
    <Head title="Calendar" />

    <AppLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-gray-900">Calendar</h1>
        </template>

        <!-- Toolbar -->
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-2">
                <button type="button" @click="shift(-1)" class="rounded-lg border border-gray-300 bg-white p-2 text-gray-600 hover:bg-gray-50" :aria-label="`Previous ${view}`">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                </button>
                <button type="button" @click="shift(1)" class="rounded-lg border border-gray-300 bg-white p-2 text-gray-600 hover:bg-gray-50" :aria-label="`Next ${view}`">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                </button>
                <h2 class="min-w-0 flex-1 truncate text-base font-semibold text-gray-900 md:min-w-[10rem] md:flex-none md:text-lg">{{ heading }}</h2>
                <button type="button" @click="selectDate(todayIso)" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Today
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="inline-flex rounded-lg border border-gray-300 bg-white p-0.5 md:hidden" role="tablist" aria-label="Calendar view">
                    <button
                        v-for="v in views"
                        :key="v.key"
                        type="button"
                        role="tab"
                        :aria-selected="view === v.key"
                        @click="view = v.key"
                        :class="view === v.key ? 'bg-[#1B5E20] text-white' : 'text-gray-600 hover:bg-gray-50'"
                        class="rounded-md px-3 py-1 text-sm font-medium"
                    >
                        {{ v.label }}
                    </button>
                </div>
                <select v-model="farmId" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-[#4CAF50] focus:ring-[#4CAF50]">
                    <option value="">All farms</option>
                    <option v-for="f in farms" :key="f.id" :value="f.id">{{ f.name }}</option>
                </select>
                <button
                    v-if="can.manageEvents"
                    type="button"
                    @click="openCreate()"
                    class="inline-flex items-center gap-2 rounded-lg bg-[#1B5E20] px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    Add event
                </button>
            </div>
        </div>

        <!-- Layer toggles -->
        <div class="mt-4 flex flex-wrap items-center gap-2">
            <button
                v-for="l in layerDefs"
                :key="l.key"
                type="button"
                @click="layers[l.key] = !layers[l.key]"
                :aria-pressed="layers[l.key]"
                :class="layers[l.key] ? 'border-gray-300 bg-white text-gray-800' : 'border-gray-200 bg-gray-50 text-gray-400 line-through'"
                class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium transition"
            >
                <span :class="[l.dot, layers[l.key] ? '' : 'opacity-30']" class="h-2 w-2 rounded-full" />
                {{ l.label }}
            </button>
            <Link
                v-if="can.manageHolidays"
                :href="route('settings.holidays.index')"
                class="ml-auto text-xs font-medium text-[#1B5E20] hover:underline"
            >
                Manage public holidays
            </Link>
        </div>

        <div class="mt-4 grid gap-6 xl:grid-cols-[1fr_18rem]">
            <!-- Month grid (md and up) -->
            <div class="hidden overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm md:block">
                <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-50">
                    <div v-for="w in weekdays" :key="w" class="px-2 py-2 text-center text-xs font-semibold uppercase tracking-wide text-gray-500">
                        {{ w }}
                    </div>
                </div>
                <div class="grid grid-cols-7">
                    <div
                        v-for="d in days"
                        :key="d.key"
                        @click="onDayClick(d)"
                        :class="[
                            d.inMonth ? (d.isHoliday ? 'bg-rose-50/60' : d.isWeekend ? 'bg-gray-50/60' : 'bg-white') : 'bg-gray-50 text-gray-400',
                            can.manageEvents || d.items.length ? 'cursor-pointer hover:bg-green-50/60' : '',
                        ]"
                        class="min-h-[7rem] border-b border-r border-gray-100 p-1.5 [&:nth-child(7n)]:border-r-0"
                    >
                        <div class="flex justify-end">
                            <span
                                :class="d.isToday ? 'bg-[#1B5E20] font-semibold text-white' : d.inMonth ? 'text-gray-700' : ''"
                                class="flex h-6 w-6 items-center justify-center rounded-full text-xs"
                            >
                                {{ d.day }}
                            </span>
                        </div>
                        <div class="mt-1 space-y-1">
                            <button
                                v-for="e in d.items.slice(0, MAX_CHIPS)"
                                :key="e.id"
                                type="button"
                                @click.stop="openEvent(e)"
                                :class="chipClass(e)"
                                :title="e.title"
                                class="block w-full truncate rounded px-1.5 py-0.5 text-left text-[11px] font-medium leading-tight"
                            >
                                <span v-if="e.time" class="font-semibold">{{ e.time.slice(0, 5) }}</span>
                                {{ e.title }}
                            </button>
                            <button
                                v-if="d.items.length > MAX_CHIPS"
                                type="button"
                                @click.stop="dayOpen = d"
                                class="w-full rounded px-1.5 text-left text-[11px] font-medium text-gray-500 hover:text-gray-900"
                            >
                                +{{ d.items.length - MAX_CHIPS }} more
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Phones: month / week / day -->
            <div class="space-y-3 md:hidden">
                <!-- Month -->
                <div v-if="view === 'month'" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="grid grid-cols-7 border-b border-gray-200 bg-gray-50">
                        <div v-for="w in weekdays" :key="w" class="py-1.5 text-center text-[10px] font-semibold uppercase text-gray-500">
                            {{ w.charAt(0) }}
                        </div>
                    </div>
                    <div class="grid grid-cols-7">
                        <button
                            v-for="d in days"
                            :key="d.key"
                            type="button"
                            @click="openDayPane(d.key)"
                            :aria-label="`${fmtLong(d.key)}, ${d.items.length} item(s)`"
                            :aria-pressed="d.key === selectedDate"
                            :class="[
                                d.inMonth ? (d.isHoliday ? 'bg-rose-50/60' : d.isWeekend ? 'bg-gray-50/60' : 'bg-white') : 'bg-gray-50 text-gray-400',
                                d.key === selectedDate ? 'ring-2 ring-inset ring-[#4CAF50]' : '',
                            ]"
                            class="flex min-h-[4.75rem] min-w-0 flex-col items-stretch border-b border-r border-gray-100 p-0.5 text-left [&:nth-child(7n)]:border-r-0"
                        >
                            <span
                                :class="d.isToday ? 'bg-[#1B5E20] font-semibold text-white' : d.inMonth ? 'text-gray-700' : ''"
                                class="mx-auto flex h-5 w-5 items-center justify-center rounded-full text-[11px]"
                            >
                                {{ d.day }}
                            </span>
                            <span class="mt-0.5 space-y-px">
                                <span
                                    v-for="e in d.items.slice(0, MAX_BARS)"
                                    :key="e.id"
                                    :class="chipClass(e)"
                                    class="block truncate rounded-sm px-0.5 text-[9px] font-medium leading-[14px]"
                                >
                                    {{ e.title }}
                                </span>
                                <span v-if="d.items.length > MAX_BARS" class="block px-0.5 text-[9px] font-medium leading-3 text-gray-500">
                                    +{{ d.items.length - MAX_BARS }}
                                </span>
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Week -->
                <div v-else-if="view === 'week'" class="divide-y divide-gray-100 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <button
                        v-for="d in weekDays"
                        :key="d.key"
                        type="button"
                        @click="openDay(d.key)"
                        class="flex w-full gap-3 px-3 py-2.5 text-left hover:bg-gray-50"
                    >
                        <span class="w-10 shrink-0 text-center">
                            <span class="block text-[10px] font-semibold uppercase text-gray-500">{{ parse(d.key).toLocaleDateString('en-KE', { weekday: 'short' }) }}</span>
                            <span
                                :class="d.isToday ? 'bg-[#1B5E20] text-white' : 'text-gray-900'"
                                class="mx-auto mt-0.5 flex h-7 w-7 items-center justify-center rounded-full text-sm font-semibold"
                            >
                                {{ d.day }}
                            </span>
                        </span>
                        <span class="min-w-0 flex-1 space-y-1 self-center">
                            <span v-for="e in d.items" :key="e.id" :class="chipClass(e)" class="block truncate rounded px-2 py-1 text-xs font-medium">
                                <span v-if="e.time" class="font-semibold">{{ e.time }} · </span>{{ e.title }}
                            </span>
                            <span v-if="!d.items.length" class="block text-xs text-gray-400">No events</span>
                        </span>
                    </button>
                </div>

                <!-- Day pane: a swipeable sheet over the month grid, or inline as the day view -->
                <Teleport to="body" :disabled="view === 'day'">
                    <div v-if="view === 'day' || dayPaneOpen" :class="view === 'day' ? '' : 'fixed inset-0 z-40 flex flex-col justify-end md:hidden'">
                        <div v-if="view !== 'day'" class="absolute inset-0 bg-black/40" @click="dayPaneOpen = false" />
                        <section
                            @touchstart.passive="onTouchStart"
                            @touchend="onTouchEnd"
                            :class="view === 'day' ? 'rounded-lg border border-gray-200 shadow-sm' : 'relative max-h-[85vh] rounded-t-2xl shadow-xl'"
                            class="flex flex-col overflow-hidden bg-white"
                            role="dialog"
                            :aria-label="fmtLong(selectedDate)"
                        >
                            <template v-if="view !== 'day'">
                                <div class="mx-auto mt-2 h-1 w-10 rounded-full bg-gray-300" />
                                <div class="flex items-center gap-1 px-2 pt-2">
                                    <button type="button" @click="stepDay(-1)" class="rounded-lg p-2 text-gray-600 hover:bg-gray-100" aria-label="Previous day">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                                    </button>
                                    <h3 :class="selectedDay.isToday ? 'text-[#1B5E20]' : 'text-gray-900'" class="flex-1 text-center text-base font-semibold">
                                        {{ fmtLong(selectedDate) }}
                                    </h3>
                                    <button type="button" @click="stepDay(1)" class="rounded-lg p-2 text-gray-600 hover:bg-gray-100" aria-label="Next day">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                                    </button>
                                    <button type="button" @click="dayPaneOpen = false" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100" aria-label="Close">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                                    </button>
                                </div>
                            </template>

                            <!-- Week strip -->
                            <div class="grid grid-cols-7 gap-1 border-b border-gray-100 px-2 py-2">
                                <button
                                    v-for="d in weekDays"
                                    :key="d.key"
                                    type="button"
                                    @click="moveTo(d.key)"
                                    :aria-pressed="d.key === selectedDate"
                                    class="flex flex-col items-center gap-0.5 rounded-lg py-1"
                                >
                                    <span class="text-[10px] font-semibold uppercase text-gray-500">{{ parse(d.key).toLocaleDateString('en-KE', { weekday: 'narrow' }) }}</span>
                                    <span
                                        :class="d.key === selectedDate ? 'bg-[#1B5E20] text-white' : d.isToday ? 'text-[#1B5E20]' : 'text-gray-800'"
                                        class="flex h-7 w-7 items-center justify-center rounded-full text-sm font-semibold"
                                    >
                                        {{ d.day }}
                                    </span>
                                    <span class="flex h-1.5 gap-0.5">
                                        <span v-for="e in d.items.slice(0, 3)" :key="e.id" :class="accentClass(e)" class="h-1.5 w-1.5 rounded-full" />
                                    </span>
                                </button>
                            </div>

                            <div class="min-h-[14rem] flex-1 overflow-y-auto overflow-x-hidden">
                                <Transition
                                    mode="out-in"
                                    enter-active-class="transition duration-150 ease-out"
                                    leave-active-class="transition duration-100 ease-in"
                                    :enter-from-class="slideDir > 0 ? 'translate-x-8 opacity-0' : '-translate-x-8 opacity-0'"
                                    :leave-to-class="slideDir > 0 ? '-translate-x-8 opacity-0' : 'translate-x-8 opacity-0'"
                                >
                                    <ul :key="selectedDate" class="divide-y divide-gray-100">
                                        <template v-if="!selectedDayLoaded">
                                            <li v-for="n in 3" :key="n" class="flex animate-pulse gap-3 px-4 py-3">
                                                <span class="w-1.5 rounded-full bg-gray-200" />
                                                <span class="flex-1 space-y-2">
                                                    <span class="block h-3 w-2/3 rounded bg-gray-200" />
                                                    <span class="block h-2.5 w-1/3 rounded bg-gray-100" />
                                                </span>
                                            </li>
                                        </template>
                                        <li v-for="e in selectedDay.items" :key="e.id">
                                            <button type="button" @click="openEvent(e)" class="flex w-full items-stretch gap-3 px-4 py-3 text-left hover:bg-gray-50">
                                                <span :class="accentClass(e)" class="w-1.5 shrink-0 rounded-full" />
                                                <span class="min-w-0">
                                                    <span class="block text-sm font-medium text-gray-900">{{ e.title }}</span>
                                                    <span class="block text-xs text-gray-500">
                                                        {{ e.time ?? 'All day' }} · {{ e.type === 'event' ? categories[e.meta.category] : typeLabel[e.type] }}
                                                        <span v-if="e.start !== e.end"> · {{ fmtShort(e.start) }} – {{ fmtShort(e.end) }}</span>
                                                        <span v-if="e.type === 'leave' && e.meta.status === 'pending'"> · Pending</span>
                                                    </span>
                                                </span>
                                            </button>
                                        </li>
                                        <li v-if="selectedDayLoaded && !selectedDay.items.length" class="px-4 py-10 text-center text-sm text-gray-500">Nothing scheduled.</li>
                                    </ul>
                                </Transition>
                            </div>

                            <div v-if="can.manageEvents" class="border-t border-gray-100 p-3">
                                <button
                                    type="button"
                                    @click="openCreate(selectedDate)"
                                    class="w-full rounded-lg bg-[#1B5E20] px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]"
                                >
                                    Add event on {{ fmtShort(selectedDate) }}
                                </button>
                            </div>
                        </section>
                    </div>
                </Teleport>
            </div>

            <!-- Upcoming -->
            <aside class="space-y-4">
                <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <h3 class="border-b border-gray-100 px-4 py-3 text-sm font-semibold text-gray-900">Coming up</h3>
                    <ul class="divide-y divide-gray-100">
                        <li v-for="e in upcoming" :key="e.id">
                            <button type="button" @click="openEvent(e)" class="flex w-full gap-3 px-4 py-2.5 text-left hover:bg-gray-50">
                                <span class="w-12 shrink-0 text-center">
                                    <span class="block text-[10px] font-semibold uppercase text-gray-500">{{ parse(e.start).toLocaleDateString('en-KE', { month: 'short' }) }}</span>
                                    <span class="block text-lg font-semibold leading-tight text-gray-900">{{ parse(e.start).getDate() }}</span>
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-medium text-gray-900">{{ e.title }}</span>
                                    <span class="block text-xs text-gray-500">{{ typeLabel[e.type] }}<span v-if="e.start !== e.end"> · until {{ fmtShort(e.end) }}</span></span>
                                </span>
                            </button>
                        </li>
                        <li v-if="!upcoming.length" class="px-4 py-4 text-sm text-gray-500">Nothing in the next 45 days.</li>
                    </ul>
                </div>
                <p class="px-1 text-xs text-gray-500">
                    Public holidays sync weekly from a public source
                    <span v-if="holidaysSyncedAt">(last {{ new Date(holidaysSyncedAt).toLocaleDateString('en-KE', { day: 'numeric', month: 'short' }) }})</span>.
                    Gazetted changes such as Idd-ul-Fitr are added by HR.
                </p>
            </aside>
        </div>

        <!-- Event detail -->
        <Teleport to="body">
            <div v-if="selected" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/40" @click="selected = null" />
                <div class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                    <span :class="chipClass(selected)" class="inline-block rounded px-2 py-0.5 text-xs font-medium">
                        {{ selected.type === 'event' ? categories[selected.meta.category] : typeLabel[selected.type] }}
                    </span>
                    <h3 class="mt-2 text-lg font-semibold text-gray-900">{{ selected.title }}</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ span(selected) }}<span v-if="selected.time"> · {{ selected.time }}</span>
                    </p>

                    <dl class="mt-4 space-y-2 text-sm">
                        <template v-if="selected.type === 'event'">
                            <div v-if="selected.meta.location" class="flex gap-2"><dt class="w-20 text-gray-500">Where</dt><dd class="text-gray-900">{{ selected.meta.location }}</dd></div>
                            <div class="flex gap-2"><dt class="w-20 text-gray-500">Farm</dt><dd class="text-gray-900">{{ selected.meta.farm ?? 'All farms' }}</dd></div>
                            <div v-if="selected.meta.createdBy" class="flex gap-2"><dt class="w-20 text-gray-500">Added by</dt><dd class="text-gray-900">{{ selected.meta.createdBy }}</dd></div>
                            <p v-if="selected.meta.description" class="whitespace-pre-line pt-2 text-gray-700">{{ selected.meta.description }}</p>
                        </template>
                        <template v-else-if="selected.type === 'leave'">
                            <div class="flex gap-2"><dt class="w-20 text-gray-500">Status</dt><dd class="capitalize text-gray-900">{{ selected.meta.status }}</dd></div>
                            <div class="flex gap-2"><dt class="w-20 text-gray-500">Days</dt><dd class="text-gray-900">{{ selected.meta.days }} working day(s)</dd></div>
                        </template>
                        <template v-else-if="selected.type === 'holiday'">
                            <p class="text-gray-700">
                                {{ selected.meta.substitute
                                    ? 'Observed today because the holiday fell on a Sunday (Public Holidays Act).'
                                    : 'Kenyan public holiday. Not counted as a working day for leave.' }}
                            </p>
                        </template>
                        <p v-else-if="selected.meta.description" class="text-gray-700">{{ selected.meta.description }}</p>
                    </dl>

                    <div class="mt-6 flex justify-end gap-3">
                        <button v-if="selected.meta.editable" type="button" @click="deleting = selected" class="mr-auto text-sm font-medium text-red-600 hover:underline">Delete</button>
                        <button type="button" @click="selected = null" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Close</button>
                        <button v-if="selected.meta.editable" type="button" @click="openEdit(selected)" class="rounded-lg bg-[#1B5E20] px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]">Edit</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Day list (overflow) -->
        <Teleport to="body">
            <div v-if="dayOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/40" @click="dayOpen = null" />
                <div class="relative w-full max-w-sm rounded-xl bg-white p-5 shadow-xl">
                    <h3 class="text-base font-semibold text-gray-900">{{ fmtLong(dayOpen.key) }}</h3>
                    <div class="mt-3 space-y-1.5">
                        <button v-for="e in dayOpen.items" :key="e.id" type="button" @click="openEvent(e)" :class="chipClass(e)" class="block w-full rounded px-2 py-1.5 text-left text-sm">
                            <span v-if="e.time" class="font-semibold">{{ e.time }} · </span>{{ e.title }}
                        </button>
                    </div>
                    <div class="mt-4 flex justify-end gap-3">
                        <button v-if="can.manageEvents" type="button" @click="openCreate(dayOpen.key); dayOpen = null" class="text-sm font-medium text-[#1B5E20] hover:underline">Add event this day</button>
                        <button type="button" @click="dayOpen = null" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Close</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Create / edit form -->
        <Teleport to="body">
            <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/50" @click="showForm = false" />
                <div class="relative max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-xl bg-white p-6 shadow-xl">
                    <h3 class="text-lg font-semibold text-gray-900">{{ editing ? 'Edit event' : 'Add event' }}</h3>
                    <form @submit.prevent="submit" class="mt-5 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Title <span class="text-red-500">*</span></label>
                            <input v-model="form.title" type="text" maxlength="150" :class="field" required />
                            <p v-if="form.errors.title" class="mt-1 text-sm text-red-600">{{ form.errors.title }}</p>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Category</label>
                                <select v-model="form.category" :class="field">
                                    <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Farm</label>
                                <select v-model="form.farm_id" :class="field">
                                    <option value="">All farms</option>
                                    <option v-for="f in farms" :key="f.id" :value="f.id">{{ f.name }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Starts <span class="text-red-500">*</span></label>
                                <input v-model="form.start_date" type="date" :class="field" required />
                                <p v-if="form.errors.start_date" class="mt-1 text-sm text-red-600">{{ form.errors.start_date }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Ends <span class="text-red-500">*</span></label>
                                <input v-model="form.end_date" type="date" :min="form.start_date" :class="field" required />
                                <p v-if="form.errors.end_date" class="mt-1 text-sm text-red-600">{{ form.errors.end_date }}</p>
                            </div>
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">From <span class="font-normal text-gray-400">(optional)</span></label>
                                <input v-model="form.start_time" type="time" :class="field" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">To</label>
                                <input v-model="form.end_time" type="time" :class="field" :required="!!form.start_time" />
                                <p v-if="form.errors.end_time" class="mt-1 text-sm text-red-600">{{ form.errors.end_time }}</p>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Location</label>
                            <input v-model="form.location" type="text" maxlength="150" :class="field" placeholder="e.g. Sugoi Farm boardroom" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Details</label>
                            <textarea v-model="form.description" rows="3" :class="field" />
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="showForm = false" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Cancel</button>
                            <button type="submit" :disabled="form.processing" class="rounded-lg bg-[#1B5E20] px-5 py-2 text-sm font-medium text-white hover:bg-[#2E7D32] disabled:opacity-50">
                                {{ form.processing ? 'Saving…' : editing ? 'Save changes' : 'Add event' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <ConfirmModal
            :show="!!deleting"
            title="Delete event"
            :message="`Delete “${deleting?.title}” from the calendar?`"
            confirm-label="Delete"
            @confirm="confirmDelete"
            @cancel="deleting = null"
        />
    </AppLayout>
</template>
