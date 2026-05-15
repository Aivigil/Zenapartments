<template>
    <ClientLayout :company="company">
        <!-- Welcome -->
        <div class="card p-5">
            <div class="text-xs uppercase tracking-wide text-slate-500">Welcome</div>
            <h1 class="mt-1 text-xl sm:text-2xl font-semibold text-slate-900">{{ client.full_name }}</h1>
            <div class="mt-1 text-xs text-slate-500 font-mono">{{ client.code }}</div>
        </div>

        <!-- Summary -->
        <div class="mt-4 grid grid-cols-3 gap-3">
            <div class="card p-4">
                <div class="text-[10px] sm:text-xs uppercase tracking-wide text-slate-500">Total contract</div>
                <div class="mt-1 text-base sm:text-lg font-semibold text-slate-900"><Money :minor="totals.contract_total" currency="PKR" /></div>
            </div>
            <div class="card p-4">
                <div class="text-[10px] sm:text-xs uppercase tracking-wide text-emerald-600">Paid</div>
                <div class="mt-1 text-base sm:text-lg font-semibold text-emerald-700"><Money :minor="totals.paid_total" currency="PKR" /></div>
            </div>
            <div class="card p-4">
                <div class="text-[10px] sm:text-xs uppercase tracking-wide text-red-600">Outstanding</div>
                <div class="mt-1 text-base sm:text-lg font-semibold text-red-700"><Money :minor="totals.outstanding_total" currency="PKR" /></div>
            </div>
        </div>

        <!-- Bookings -->
        <h2 class="mt-6 text-sm font-semibold text-slate-700 uppercase tracking-wide">Your booking{{ bookings.length > 1 ? 's' : '' }}</h2>
        <div class="mt-3 space-y-3">
            <div v-for="b in bookings" :key="b.id" class="card p-5">
                <div class="flex items-start justify-between flex-wrap gap-3">
                    <div>
                        <div class="text-xs text-slate-500 font-mono">{{ b.code }}</div>
                        <div class="text-lg font-semibold text-slate-900">{{ b.unit_code }}<span v-if="b.unit_name && b.unit_name !== b.unit_code" class="text-slate-500 font-normal"> — {{ b.unit_name }}</span></div>
                        <div class="text-xs text-slate-500">{{ b.unit_category }}</div>
                    </div>
                    <span :class="['badge', statusBadge(b.status)]">{{ statusLabel(b.status) }}</span>
                </div>

                <!-- Progress -->
                <div class="mt-4">
                    <div class="flex items-center justify-between text-xs text-slate-600 mb-1">
                        <span>{{ b.pct_paid }}% paid</span>
                        <span>
                            <Money :minor="b.paid_minor" currency="PKR" /> of <Money :minor="b.total_minor" currency="PKR" />
                        </span>
                    </div>
                    <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full bg-emerald-500 transition-all" :style="{ width: `${b.pct_paid}%` }"></div>
                    </div>
                </div>

                <!-- Next due / overdue -->
                <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div v-if="b.next_due_date" class="rounded-md bg-amber-50 ring-1 ring-amber-200 p-3 text-sm">
                        <div class="text-xs uppercase tracking-wide text-amber-700">Next installment due</div>
                        <div class="mt-1 font-semibold text-amber-900">{{ formatDate(b.next_due_date) }}</div>
                        <div class="text-xs text-amber-800">{{ b.next_due_label }} — <Money :minor="b.next_due_minor" currency="PKR" /></div>
                    </div>
                    <div v-if="b.overdue_items > 0" class="rounded-md bg-red-50 ring-1 ring-red-200 p-3 text-sm">
                        <div class="text-xs uppercase tracking-wide text-red-700">Overdue</div>
                        <div class="mt-1 font-semibold text-red-900">{{ b.overdue_items }} installment{{ b.overdue_items === 1 ? '' : 's' }}</div>
                        <div class="text-xs text-red-800">Please contact us to settle.</div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-4 flex flex-wrap gap-2">
                    <a :href="`/c/${token}/bookings/${b.id}/statement.pdf`" target="_blank"
                       class="text-sm px-3 py-1.5 rounded-md bg-brand text-white hover:bg-brand-700">
                        Download statement
                    </a>
                    <a v-if="company.whatsapp" :href="`https://wa.me/${company.whatsapp.replace(/[^0-9]/g, '')}?text=${whatsappPaymentText(b)}`" target="_blank"
                       class="text-sm px-3 py-1.5 rounded-md bg-emerald-600 text-white hover:bg-emerald-700">
                        I've paid — send proof on WhatsApp
                    </a>
                </div>
            </div>
        </div>

        <!-- Recent payments -->
        <h2 class="mt-6 text-sm font-semibold text-slate-700 uppercase tracking-wide">Recent payments</h2>
        <div v-if="recent_payments.length === 0" class="mt-3 card p-5 text-sm text-slate-500">
            No payments on record yet.
        </div>
        <div v-else class="mt-3 card overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Date</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Ref</th>
                        <th class="px-3 py-2 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Channel</th>
                        <th class="px-3 py-2 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="p in recent_payments" :key="p.id">
                        <td class="px-3 py-2">{{ formatDate(p.received_at) }}</td>
                        <td class="px-3 py-2 font-mono text-xs">{{ p.code }}</td>
                        <td class="px-3 py-2 text-xs text-slate-600">{{ channelLabel(p.channel) }}</td>
                        <td class="px-3 py-2 text-right text-emerald-700 font-medium">
                            <Money :minor="p.amount_minor" currency="PKR" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Help -->
        <div class="mt-6 card p-5 bg-slate-50">
            <div class="text-sm font-semibold text-slate-800">Need help?</div>
            <div class="mt-1 text-sm text-slate-600">
                Reach our sales team — we're happy to help with anything related to your booking.
            </div>
            <div class="mt-3 flex flex-wrap gap-2 text-sm">
                <a v-if="company.whatsapp" :href="`https://wa.me/${company.whatsapp.replace(/[^0-9]/g, '')}`" target="_blank"
                   class="px-3 py-1.5 rounded-md bg-emerald-600 text-white hover:bg-emerald-700">WhatsApp {{ company.whatsapp }}</a>
                <a v-if="company.phone" :href="`tel:${company.phone}`"
                   class="px-3 py-1.5 rounded-md bg-white ring-1 ring-slate-300 hover:bg-slate-50">Call {{ company.phone }}</a>
                <a v-if="company.email" :href="`mailto:${company.email}`"
                   class="px-3 py-1.5 rounded-md bg-white ring-1 ring-slate-300 hover:bg-slate-50">Email</a>
            </div>
        </div>
    </ClientLayout>
</template>

<script setup>
import ClientLayout from '@/Layouts/ClientLayout.vue';
import Money from '@/Components/Money.vue';

const props = defineProps({
    client: { type: Object, required: true },
    bookings: { type: Array, required: true },
    recent_payments: { type: Array, required: true },
    totals: { type: Object, required: true },
    token: { type: String, required: true },
    company: { type: Object, required: true },
});

function statusLabel(s) {
    return { active: 'Active', completed: 'Completed', cancelled: 'Cancelled' }[s] || s;
}
function statusBadge(s) {
    return {
        active: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        completed: 'bg-blue-50 text-blue-700 ring-blue-600/20',
        cancelled: 'bg-slate-100 text-slate-600 ring-slate-300',
    }[s] || 'bg-slate-100 text-slate-600 ring-slate-300';
}
function channelLabel(c) {
    return { bank_transfer: 'Bank transfer', cash: 'Cash', cheque: 'Cheque', online_gateway: 'Online', foreign_wire: 'Foreign wire' }[c] || c;
}
function formatDate(d) {
    if (!d) return '—';
    const date = new Date(d);
    return date.toLocaleDateString('en-PK', { day: 'numeric', month: 'short', year: 'numeric' });
}
function whatsappPaymentText(b) {
    return encodeURIComponent(`Hello, I've made a payment for booking ${b.code} (${b.unit_code}). Sending the proof now.`);
}
</script>
