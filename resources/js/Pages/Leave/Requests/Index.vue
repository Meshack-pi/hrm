<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import StatusBadge from '@/Components/StatusBadge.vue';
import Pagination from '@/Components/Pagination.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import LeaveDetailPanel from './Partials/LeaveDetailPanel.vue';
import { usePermissions } from '@/composables/usePermissions';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    requests: { type: Object, required: true },
    leaveTypes: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const { can } = usePermissions();

const status = ref(props.filters.status ?? '');
const leaveTypeId = ref(props.filters.leave_type_id ?? '');

const showApply = ref(false);
const rejecting = ref(null);
const cancelling = ref(null);

// The open request is looked up by id, so the panel reflects fresh data after approve/reject.
const viewingId = ref(null);
const viewing = computed(() => props.requests.data.find((r) => r.id === viewingId.value) ?? null);

watch([status, leaveTypeId], () => {
    router.get(
        route('leave.requests.index'),
        { status: status.value || undefined, leave_type_id: leaveTypeId.value || undefined },
        { preserveState: true, replace: true }
    );
});

const applyForm = useForm({
    leave_type_id: '',
    start_date: '',
    end_date: '',
    reason: '',
});

const rejectForm = useForm({
    rejection_reason: '',
});

const field = 'mt-1 w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-[#4CAF50] focus:ring-[#4CAF50]';

function submitApply() {
    applyForm.post(route('leave.requests.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showApply.value = false;
            applyForm.reset();
        },
    });
}

function approve(req) {
    router.post(route('leave.requests.approve', req.id), {}, { preserveScroll: true });
}

function submitReject() {
    rejectForm.post(route('leave.requests.reject', rejecting.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            rejecting.value = null;
            rejectForm.reset();
        },
    });
}

function confirmCancel() {
    router.post(route('leave.requests.cancel', cancelling.value.id), {}, {
        preserveScroll: true,
        onFinish: () => (cancelling.value = null),
    });
}

function canActOn(req) {
    return req.status === 'pending' && (can('leave.approve_all') || can('leave.approve_team'));
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('en-KE', {
        year: 'numeric', month: 'short', day: 'numeric',
    });
}

const columns = [
    { key: 'employee', label: 'Employee' },
    { key: 'type', label: 'Leave Type' },
    { key: 'dates', label: 'Dates' },
    { key: 'days', label: 'Days' },
    { key: 'status', label: 'Status' },
    { key: 'approver', label: 'Approver' },
    { key: 'actions', label: '' },
];
</script>

<template>
    <Head title="Leave Requests" />

    <AppLayout>
        <template #header>
            <h1 class="text-lg font-semibold text-gray-900">Leave Requests</h1>
        </template>

        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="flex flex-wrap gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600">Status</label>
                    <select v-model="status" class="mt-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-[#4CAF50] focus:ring-[#4CAF50]">
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">Leave type</label>
                    <select v-model="leaveTypeId" class="mt-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-[#4CAF50] focus:ring-[#4CAF50]">
                        <option value="">All types</option>
                        <option v-for="t in leaveTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                    </select>
                </div>
            </div>

            <button
                v-if="can('leave.apply')"
                type="button"
                @click="showApply = true"
                class="inline-flex items-center justify-center gap-2 rounded-lg bg-[#1B5E20] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#2E7D32]"
            >
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Apply for Leave
            </button>
        </div>

        <div class="mt-4">
            <DataTable :columns="columns" empty="No leave requests found.">
                <tr
                    v-for="req in requests.data"
                    :key="req.id"
                    @click="viewingId = req.id"
                    class="cursor-pointer hover:bg-green-50/60"
                >
                    <td class="px-4 py-3">
                        <button
                            type="button"
                            @click.stop="viewingId = req.id"
                            class="text-left text-sm font-medium text-gray-900 hover:text-[#1B5E20] hover:underline"
                        >
                            {{ req.employee?.first_name }} {{ req.employee?.last_name }}
                        </button>
                        <div class="text-xs text-gray-500">{{ req.employee?.employee_number }}</div>
                        <p v-if="req.reason" class="mt-0.5 max-w-xs truncate text-xs text-gray-500" :title="req.reason">“{{ req.reason }}”</p>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ req.leave_type?.name ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        {{ formatDate(req.start_date) }} – {{ formatDate(req.end_date) }}
                    </td>
                    <td class="px-4 py-3 text-sm tabular-nums text-gray-600">{{ req.days }}</td>
                    <td class="px-4 py-3"><StatusBadge :status="req.status" /></td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ req.approver?.name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex justify-end gap-3" @click.stop>
                            <template v-if="canActOn(req)">
                                <button type="button" @click="approve(req)" class="text-sm font-medium text-[#1B5E20] hover:underline">
                                    Approve
                                </button>
                                <button type="button" @click="rejecting = req" class="text-sm font-medium text-red-600 hover:underline">
                                    Reject
                                </button>
                            </template>
                            <button
                                v-else-if="req.status === 'pending'"
                                type="button"
                                @click="cancelling = req"
                                class="text-sm font-medium text-gray-600 hover:underline"
                            >
                                Cancel
                            </button>
                            <span v-else class="text-sm text-gray-400">—</span>
                        </div>
                    </td>
                </tr>
            </DataTable>

            <Pagination v-if="requests.links" :links="requests.links" class="mt-4" />
        </div>

        <!-- Apply modal -->
        <Teleport to="body">
            <Transition
                enter-active-class="ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="showApply" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/50" @click="showApply = false" />
                    <div class="relative w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
                        <h3 class="text-lg font-semibold text-gray-900">Apply for leave</h3>

                        <form @submit.prevent="submitApply" class="mt-5 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">
                                    Leave type <span class="text-red-500">*</span>
                                </label>
                                <select v-model="applyForm.leave_type_id" :class="field" required>
                                    <option value="">Select a leave type</option>
                                    <option v-for="t in leaveTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
                                </select>
                                <p v-if="applyForm.errors.leave_type_id" class="mt-1 text-sm text-red-600">
                                    {{ applyForm.errors.leave_type_id }}
                                </p>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">
                                        Start date <span class="text-red-500">*</span>
                                    </label>
                                    <input v-model="applyForm.start_date" type="date" :class="field" required />
                                    <p v-if="applyForm.errors.start_date" class="mt-1 text-sm text-red-600">
                                        {{ applyForm.errors.start_date }}
                                    </p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">
                                        End date <span class="text-red-500">*</span>
                                    </label>
                                    <input v-model="applyForm.end_date" type="date" :class="field" required />
                                    <p v-if="applyForm.errors.end_date" class="mt-1 text-sm text-red-600">
                                        {{ applyForm.errors.end_date }}
                                    </p>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">Reason</label>
                                <textarea v-model="applyForm.reason" rows="3" :class="field" />
                            </div>

                            <div class="flex justify-end gap-3 pt-2">
                                <button
                                    type="button"
                                    @click="showApply = false"
                                    class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    :disabled="applyForm.processing"
                                    class="rounded-lg bg-[#1B5E20] px-5 py-2 text-sm font-medium text-white transition hover:bg-[#2E7D32] disabled:opacity-50"
                                >
                                    {{ applyForm.processing ? 'Submitting…' : 'Submit Request' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Reject modal -->
        <Teleport to="body">
            <div v-if="rejecting" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="fixed inset-0 bg-black/50" @click="rejecting = null" />
                <div class="relative w-full max-w-md rounded-xl bg-white p-6 shadow-xl">
                    <h3 class="text-lg font-semibold text-gray-900">Reject leave request</h3>
                    <p class="mt-1 text-sm text-gray-600">
                        {{ rejecting.employee?.first_name }} {{ rejecting.employee?.last_name }} —
                        {{ rejecting.leave_type?.name }}
                    </p>

                    <form @submit.prevent="submitReject" class="mt-4 space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                Reason <span class="text-red-500">*</span>
                            </label>
                            <textarea v-model="rejectForm.rejection_reason" rows="3" :class="field" required />
                            <p v-if="rejectForm.errors.rejection_reason" class="mt-1 text-sm text-red-600">
                                {{ rejectForm.errors.rejection_reason }}
                            </p>
                        </div>

                        <div class="flex justify-end gap-3">
                            <button
                                type="button"
                                @click="rejecting = null"
                                class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                :disabled="rejectForm.processing"
                                class="rounded-lg bg-red-600 px-5 py-2 text-sm font-medium text-white transition hover:bg-red-700 disabled:opacity-50"
                            >
                                Reject Request
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <LeaveDetailPanel
            v-if="viewing"
            :request="viewing"
            :can-decide="canActOn(viewing)"
            :can-cancel="viewing.status === 'pending' && !canActOn(viewing)"
            @close="viewingId = null"
            @approve="approve"
            @reject="rejecting = $event"
            @cancel="cancelling = $event"
        />

        <ConfirmModal
            :show="!!cancelling"
            title="Cancel leave request"
            message="Cancel this leave request? Any days held against your balance will be released."
            confirm-label="Cancel Request"
            @confirm="confirmCancel"
            @cancel="cancelling = null"
        />
    </AppLayout>
</template>
