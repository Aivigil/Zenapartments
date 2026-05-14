<template>
    <AppLayout :title="payment.code">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-sm text-slate-500 font-mono">{{ payment.code }}</div>
                <h1 class="text-2xl font-semibold text-slate-900">
                    <Money :minor="payment.amount_minor" :currency="payment.currency" />
                    <span v-if="payment.currency !== 'PKR'" class="text-base text-slate-500">
                        (= <Money :minor="payment.pkr_amount_minor" currency="PKR" />)
                    </span>
                </h1>
                <div class="mt-1 text-sm text-slate-600 flex items-center gap-3">
                    <span>{{ payment.received_at }}</span>
                    <span class="badge bg-slate-100 text-slate-700 ring-slate-600/20">{{ payment.channel_label }}</span>
                    <span :class="['badge', payment.status === 'posted' ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-red-50 text-red-700 ring-red-600/20']">
                        {{ payment.status }}
                    </span>
                </div>
            </div>
            <div v-if="can.reverse && payment.status === 'posted'">
                <button @click="reverse" class="btn-danger">Reverse</button>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="card p-5">
                <h2 class="text-sm font-semibold text-slate-900">Source</h2>
                <dl class="mt-3 text-sm space-y-2">
                    <div><dt class="text-slate-500 text-xs">Client</dt>
                        <dd><Link :href="`/clients/${payment.client.id}`" class="text-brand">{{ payment.client.code }} — {{ payment.client.full_name }}</Link></dd>
                    </div>
                    <div><dt class="text-slate-500 text-xs">Booking</dt>
                        <dd><Link :href="`/bookings/${payment.booking_id}`" class="text-brand font-mono">{{ payment.booking_code }}</Link></dd>
                    </div>
                    <div v-if="payment.bank_account"><dt class="text-slate-500 text-xs">Bank account</dt><dd>{{ payment.bank_account }}</dd></div>
                    <div v-if="payment.bank_reference"><dt class="text-slate-500 text-xs">Bank reference</dt><dd class="font-mono">{{ payment.bank_reference }}</dd></div>
                    <div v-if="payment.fx_rate"><dt class="text-slate-500 text-xs">FX rate</dt><dd>{{ payment.fx_rate }}</dd></div>
                    <div v-if="payment.posted_by"><dt class="text-slate-500 text-xs">Posted by</dt><dd>{{ payment.posted_by }}</dd></div>
                </dl>
            </div>
            <div v-if="payment.status === 'reversed'" class="card p-5 ring-2 ring-red-200">
                <h2 class="text-sm font-semibold text-red-700">Reversed</h2>
                <dl class="mt-3 text-sm space-y-2">
                    <div><dt class="text-slate-500 text-xs">On</dt><dd>{{ payment.reversed_on }}</dd></div>
                    <div><dt class="text-slate-500 text-xs">Reason</dt><dd>{{ payment.reversal_reason }}</dd></div>
                </dl>
            </div>
        </div>

        <div class="mt-6 card">
            <div class="p-5 border-b border-slate-200">
                <h2 class="text-base font-semibold text-slate-900">Allocations</h2>
                <p class="text-xs text-slate-500 mt-1">How this payment was applied across schedule items.</p>
            </div>
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-slate-500">#</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Schedule item</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Due date</th>
                        <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Applied</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="a in payment.allocations" :key="a.id">
                        <td class="px-4 py-2 text-sm text-slate-500">{{ a.schedule_seq }}</td>
                        <td class="px-4 py-2 text-sm">{{ a.schedule_label }}</td>
                        <td class="px-4 py-2 text-sm text-slate-600">{{ a.schedule_due_date }}</td>
                        <td class="px-4 py-2 text-sm text-right"><Money :minor="a.amount_minor" :currency="a.currency" /></td>
                    </tr>
                    <tr v-if="payment.allocations.length === 0">
                        <td colspan="4" class="px-4 py-6 text-center text-sm text-slate-500">
                            No allocations — payment may have been reversed, or there were no open schedule items.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="payment.notes" class="mt-6 card p-5">
            <h2 class="text-sm font-semibold text-slate-900">Notes</h2>
            <p class="mt-2 text-sm text-slate-700 whitespace-pre-line">{{ payment.notes }}</p>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Money from '@/Components/Money.vue';

const props = defineProps({
    payment: { type: Object, required: true },
    can: { type: Object, required: true },
});

function reverse() {
    const reason = prompt('Reason for reversal? (required)');
    if (!reason) return;
    router.delete(`/payments/${props.payment.id}`, { data: { reason }, preserveScroll: true });
}
</script>
