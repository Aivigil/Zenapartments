<template>
    <AppLayout :title="booking.code">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-sm text-slate-500 font-mono">{{ booking.code }}</div>
                <h1 class="text-2xl font-semibold text-slate-900">
                    {{ booking.client?.full_name }} → {{ booking.unit?.code }}
                </h1>
                <div class="mt-1 text-sm text-slate-600 flex items-center gap-3">
                    <span>Booked {{ booking.booking_date }}</span>
                    <StatusBadge :status="booking.status" :label="booking.status" />
                </div>
            </div>
            <div class="flex gap-2">
                <Link :href="`/payments/create?booking_id=${booking.id}`" class="btn-primary">+ Record payment</Link>
                <button v-if="booking.status === 'active'" @click="cancelBooking" class="btn-danger">Cancel</button>
            </div>
        </div>

        <!-- Totals -->
        <div class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="card p-5">
                <div class="text-xs uppercase tracking-wide text-slate-500">Scheduled total</div>
                <div class="mt-1 text-2xl font-semibold text-slate-900"><Money :minor="booking.totals.scheduled_minor" :currency="booking.currency" /></div>
            </div>
            <div class="card p-5">
                <div class="text-xs uppercase tracking-wide text-slate-500">Paid</div>
                <div class="mt-1 text-2xl font-semibold text-emerald-700"><Money :minor="booking.totals.paid_minor" :currency="booking.currency" /></div>
            </div>
            <div class="card p-5">
                <div class="text-xs uppercase tracking-wide text-slate-500">Outstanding</div>
                <div class="mt-1 text-2xl font-semibold text-red-700"><Money :minor="booking.totals.outstanding_minor" :currency="booking.currency" /></div>
            </div>
        </div>

        <!-- Summary -->
        <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-4">
            <div class="card p-5">
                <h2 class="text-sm font-semibold text-slate-900">Client</h2>
                <Link :href="`/clients/${booking.client.id}`" class="mt-2 block text-sm text-brand hover:text-brand-dark">
                    {{ booking.client.code }} — {{ booking.client.full_name }}
                </Link>
                <div class="text-sm text-slate-600">{{ booking.client.primary_phone }}</div>
            </div>
            <div class="card p-5">
                <h2 class="text-sm font-semibold text-slate-900">Unit</h2>
                <Link :href="`/inventory/units/${booking.unit.id}`" class="mt-2 block text-sm text-brand hover:text-brand-dark">
                    {{ booking.unit.code }} — {{ booking.unit.name }}
                </Link>
                <div class="text-sm text-slate-600">{{ booking.unit.project_name }} · {{ booking.unit.category_name }}</div>
            </div>
            <div class="card p-5">
                <h2 class="text-sm font-semibold text-slate-900">Plan</h2>
                <div class="mt-2 text-sm text-slate-700 font-mono">{{ booking.plan.code }}</div>
                <div class="text-sm text-slate-600">{{ booking.plan.name }}</div>
            </div>
        </div>

        <!-- Schedule -->
        <div class="mt-6 card">
            <div class="p-5 border-b border-slate-200">
                <h2 class="text-base font-semibold text-slate-900">Payment schedule</h2>
                <p class="text-xs text-slate-500 mt-1">Auto-generated from the plan template. {{ booking.schedules.length }} rows.</p>
            </div>
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-slate-500">#</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Due date</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Item</th>
                        <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Amount</th>
                        <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Paid</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="s in booking.schedules" :key="s.id" :class="{ 'bg-red-50/30': s.is_overdue }">
                        <td class="px-4 py-2 text-sm text-slate-500">{{ s.sequence_no }}</td>
                        <td class="px-4 py-2 text-sm text-slate-700">{{ s.due_date }}</td>
                        <td class="px-4 py-2 text-sm">{{ s.label }}</td>
                        <td class="px-4 py-2 text-sm text-right"><Money :minor="s.amount_minor" :currency="booking.currency" /></td>
                        <td class="px-4 py-2 text-sm text-right text-emerald-700"><Money :minor="s.paid_minor" :currency="booking.currency" /></td>
                        <td class="px-4 py-2 text-sm">
                            <span :class="['badge', statusClass(s)]">{{ statusLabel(s) }}</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Payments -->
        <div class="mt-6 card">
            <div class="p-5 border-b border-slate-200">
                <h2 class="text-base font-semibold text-slate-900">Payments</h2>
            </div>
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Receipt</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Date</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Channel</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Reference</th>
                        <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="p in booking.payments" :key="p.id">
                        <td class="px-4 py-2 text-sm font-mono">
                            <Link :href="`/payments/${p.id}`" class="text-brand">{{ p.code }}</Link>
                        </td>
                        <td class="px-4 py-2 text-sm">{{ p.received_at }}</td>
                        <td class="px-4 py-2 text-sm">{{ p.channel }}</td>
                        <td class="px-4 py-2 text-sm text-slate-600">{{ p.bank_reference || '—' }}</td>
                        <td class="px-4 py-2 text-sm text-right"><Money :minor="p.amount_minor" :currency="p.currency" /></td>
                    </tr>
                    <tr v-if="booking.payments.length === 0">
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">No payments recorded yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="booking.notes" class="mt-6 card p-5">
            <h2 class="text-sm font-semibold text-slate-900">Notes</h2>
            <p class="mt-2 text-sm text-slate-700 whitespace-pre-line">{{ booking.notes }}</p>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Money from '@/Components/Money.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    booking: { type: Object, required: true },
});

function statusClass(s) {
    if (s.status === 'paid') return 'bg-emerald-50 text-emerald-700 ring-emerald-600/20';
    if (s.status === 'partially_paid') return 'bg-amber-50 text-amber-700 ring-amber-600/20';
    if (s.status === 'waived' || s.status === 'written_off') return 'bg-slate-100 text-slate-700 ring-slate-600/20';
    if (s.is_overdue) return 'bg-red-50 text-red-700 ring-red-600/20';
    return 'bg-slate-100 text-slate-700 ring-slate-600/20';
}

function statusLabel(s) {
    if (s.is_overdue && s.status !== 'paid' && s.status !== 'waived') return 'Overdue';
    return s.status.replace('_', ' ');
}

function cancelBooking() {
    const reason = prompt('Reason for cancellation?');
    if (!reason) return;
    router.delete(`/bookings/${props.booking.id}`, { data: { reason }, preserveScroll: true });
}
</script>
