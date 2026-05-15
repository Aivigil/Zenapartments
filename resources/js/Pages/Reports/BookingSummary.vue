<template>
    <AppLayout title="Booking summary">
        <div>
            <div class="text-sm text-slate-500"><Link href="/reports" class="hover:text-brand">Reports</Link> / Bookings summary</div>
            <h1 class="text-2xl font-semibold text-slate-900">Active bookings — finance summary</h1>
            <p class="mt-1 text-sm text-slate-500">One row per active booking. The morning-coffee view of the entire receivables book.</p>
        </div>

        <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Active bookings</div>
                <div class="mt-1 text-xl font-semibold text-slate-900">{{ totals.count }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Contract total</div>
                <div class="mt-1 text-xl font-semibold text-slate-900"><Money :minor="totals.contract_total" currency="PKR" /></div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Paid to date</div>
                <div class="mt-1 text-xl font-semibold text-emerald-700"><Money :minor="totals.paid_total" currency="PKR" /></div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Outstanding</div>
                <div class="mt-1 text-xl font-semibold text-red-700"><Money :minor="totals.outstanding_total" currency="PKR" /></div>
            </div>
        </div>

        <div class="mt-6 card overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Booking</th>
                        <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Client</th>
                        <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Unit</th>
                        <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Category</th>
                        <th class="px-3 py-3 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Total</th>
                        <th class="px-3 py-3 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Paid</th>
                        <th class="px-3 py-3 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Outstanding</th>
                        <th class="px-3 py-3 text-right text-xs font-medium uppercase tracking-wide text-slate-500">% Paid</th>
                        <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Overdue</th>
                        <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Next due</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="b in bookings" :key="b.id" class="hover:bg-slate-50">
                        <td class="px-3 py-2 text-sm font-mono">
                            <Link :href="`/bookings/${b.id}`" class="text-brand">{{ b.code }}</Link>
                        </td>
                        <td class="px-3 py-2 text-sm">
                            <Link :href="`/clients/${b.client_id}`" class="hover:text-brand">{{ b.client_name }}</Link>
                            <div class="text-xs text-slate-500 font-mono">{{ b.client_code }}</div>
                        </td>
                        <td class="px-3 py-2 text-sm font-mono">{{ b.unit_code }}</td>
                        <td class="px-3 py-2 text-sm text-slate-600">{{ b.unit_category }}</td>
                        <td class="px-3 py-2 text-sm text-right"><Money :minor="b.total_minor" currency="PKR" /></td>
                        <td class="px-3 py-2 text-sm text-right text-emerald-700"><Money :minor="b.paid_minor" currency="PKR" /></td>
                        <td class="px-3 py-2 text-sm text-right font-medium"><Money :minor="b.outstanding_minor" currency="PKR" /></td>
                        <td class="px-3 py-2 text-sm text-right">{{ percentPaid(b) }}%</td>
                        <td class="px-3 py-2 text-sm">
                            <span v-if="b.overdue_items > 0" class="badge bg-red-50 text-red-700 ring-red-600/20">
                                {{ b.overdue_items }} overdue
                            </span>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-3 py-2 text-sm">
                            <span v-if="b.next_due_date">
                                {{ b.next_due_date }}
                                <div class="text-xs text-slate-500"><Money :minor="b.next_due_amount_minor" currency="PKR" /></div>
                            </span>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Money from '@/Components/Money.vue';

defineProps({
    bookings: { type: Array, required: true },
    totals: { type: Object, required: true },
});

function percentPaid(b) {
    if (!b.total_minor) return 0;
    return Math.round((b.paid_minor / b.total_minor) * 100);
}
</script>
