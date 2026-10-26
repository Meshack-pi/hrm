<script setup>
import SlideOver from '@/Components/SlideOver.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import { computed } from 'vue';

// `request` is a row from the leave list, with employee, leaveType, reliefEmployee and approver loaded.
const props = defineProps({
    request: { type: Object, required: true },
    canDecide: { type: Boolean, default: false }, // may approve / reject
    canCancel: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'approve', 'reject', 'cancel']);

const name = (e) => (e ? `${e.first_name} ${e.last_name}` : null);

const date = (v, opts = {}) =>
    v ? new Date(v).toLocaleDateString('en-KE', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', ...opts }) : '—';
const dateTime = (v) =>
    v ? new Date(v).toLocaleString('en-KE', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';

const days = computed(() => {
    const n = Number(props.request.days);
    return `${n.toLocaleString('en-KE', { maximumFractionDigits: 2 })} working day${n === 1 ? '' : 's'}`;
});

const decision = computed(() => {
    const r = props.request;
    if (r.status === 'approved') return { label: 'Approved', by: r.approver?.name, at: r.approved_at };
    if (r.status === 'rejected') return { label: 'Rejected', by: r.approver?.name, at: r.approved_at };
    return null;
});

const row = 'flex items-baseline justify-between gap-4 px-4 py-2.5 text-sm';
</script>

<template>
    <SlideOver :label="`Leave request from ${name(request.employee)}`" @close="emit('close')">
        <template #header>
            <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Leave request</p>
            <h2 class="mt-0.5 truncate text-lg font-semibold text-gray-900">{{ name(request.employee) ?? 'Unknown employee' }}</h2>
            <p class="flex items-center gap-2 text-xs text-gray-500">
                <span class="font-mono">{{ request.employee?.employee_number }}</span>
                <StatusBadge :status="request.status" size="xs" />
            </p>
        </template>

        <!-- Headline -->
        <div class="rounded-xl bg-[#1B5E20] p-5 text-white">
            <p class="text-xs font-medium uppercase tracking-wide text-green-100">{{ request.leave_type?.name ?? 'Leave' }}</p>
            <p class="mt-1 text-2xl font-semibold">{{ days }}</p>
            <p class="mt-1 text-sm text-green-100">
                {{ date(request.start_date) }}
                <template v-if="request.end_date !== request.start_date"> – {{ date(request.end_date) }}</template>
            </p>
        </div>

        <!-- Reason -->
        <section class="rounded-xl border border-gray-200 bg-white p-4">
            <h3 class="text-sm font-semibold text-gray-900">Reason given</h3>
            <p v-if="request.reason" class="mt-2 whitespace-pre-line break-words text-sm leading-relaxed text-gray-700">{{ request.reason }}</p>
            <p v-else class="mt-2 text-sm italic text-gray-400">No reason was given.</p>
        </section>

        <!-- Rejection -->
        <section v-if="request.status === 'rejected' && request.rejection_reason" class="rounded-xl border border-red-200 bg-red-50 p-4">
            <h3 class="text-sm font-semibold text-red-900">Why it was rejected</h3>
            <p class="mt-2 whitespace-pre-line break-words text-sm leading-relaxed text-red-800">{{ request.rejection_reason }}</p>
        </section>

        <!-- Details -->
        <section class="divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white">
            <div :class="row">
                <span class="text-gray-600">Applied on</span>
                <span class="text-right text-gray-900">{{ dateTime(request.created_at) }}</span>
            </div>
            <div :class="row">
                <span class="text-gray-600">Covering while away</span>
                <span class="text-right text-gray-900">{{ name(request.relief_employee) ?? 'Not set' }}</span>
            </div>
            <div v-if="decision" :class="row">
                <span class="text-gray-600">{{ decision.label }} by</span>
                <span class="text-right text-gray-900">
                    {{ decision.by ?? '—' }}
                    <span class="block text-xs text-gray-500">{{ dateTime(decision.at) }}</span>
                </span>
            </div>
        </section>

        <template v-if="canDecide || canCancel" #footer>
            <template v-if="canDecide">
                <button
                    type="button"
                    @click="emit('approve', request)"
                    class="flex-1 rounded-lg bg-[#1B5E20] px-4 py-2 text-sm font-medium text-white hover:bg-[#2E7D32]"
                >
                    Approve
                </button>
                <button
                    type="button"
                    @click="emit('reject', request)"
                    class="flex-1 rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50"
                >
                    Reject
                </button>
            </template>
            <button
                v-else
                type="button"
                @click="emit('cancel', request)"
                class="flex-1 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
                Cancel request
            </button>
        </template>
    </SlideOver>
</template>
