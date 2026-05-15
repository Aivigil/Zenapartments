<template>
    <AppLayout title="Possession tracker">
        <div>
            <div class="text-sm text-slate-500"><Link href="/reports" class="hover:text-brand">Reports</Link> / Possession</div>
            <h1 class="text-2xl font-semibold text-slate-900">Possession tracker</h1>
            <p class="mt-1 text-sm text-slate-500">
                Bookings ≥ 90% paid OR with final installment due in the next 60 days ({{ today }} → {{ window_end }}).
                "Eligible" means 100% paid AND no overdue items.
            </p>
        </div>

        <div class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Bookings in window</div>
                <div class="mt-1 text-xl font-semibold text-slate-900">{{ totals.count }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wide text-emerald-600">Eligible to transfer</div>
                <div class="mt-1 text-xl font-semibold text-emerald-700">{{ totals.eligible_count }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wide text-amber-600">Over 90% paid</div>
                <div class="mt-1 text-xl font-semibold text-amber-700">{{ totals.over_90_count }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wide text-red-600">Outstanding</div>
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
                        <th class="px-3 py-3 text-right text-xs font-medium uppercase tracking-wide text-slate-500">% Paid</th>
                        <th class="px-3 py-3 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Outstanding</th>
                        <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Final due</th>
                        <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Overdue</th>
                        <th class="px-3 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="b in bookings" :key="b.id" class="hover:bg-slate-50">
                        <td class="px-3 py-2 text-sm font-mono">
                            <Link :href="`/bookings/${b.id}`" class="text-brand">{{ b.code }}</Link>
                        </td>
                        <td class="px-3 py-2 text-sm">
                            <Link :href="`/clients/${b.client_id}`" class="hover:text-brand">{{ b.client_name }}</Link>
                            <div class="text-xs text-slate-500">{{ b.phone }}</div>
                        </td>
                        <td class="px-3 py-2 text-sm font-mono">{{ b.unit_code }}<div class="text-xs text-slate-500 font-sans">{{ b.unit_category }}</div></td>
                        <td class="px-3 py-2 text-sm text-right">
                            <div class="flex items-center justify-end gap-2">
                                <div class="w-16 h-1.5 bg-slate-100 rounded">
                                    <div class="h-full rounded bg-emerald-500" :style="{ width: `${Math.min(100, b.pct_paid)}%` }"></div>
                                </div>
                                <span>{{ b.pct_paid }}%</span>
                            </div>
                        </td>
                        <td class="px-3 py-2 text-sm text-right font-medium"><Money :minor="b.outstanding_minor" currency="PKR" /></td>
                        <td class="px-3 py-2 text-sm">
                            {{ b.final_due_date || '—' }}
                            <div v-if="b.days_to_final !== null" class="text-xs"
                                :class="b.days_to_final < 0 ? 'text-red-600' : b.days_to_final <= 30 ? 'text-amber-600' : 'text-slate-500'">
                                {{ daysLabel(b.days_to_final) }}
                            </div>
                        </td>
                        <td class="px-3 py-2 text-sm">
                            <span v-if="b.overdue_items > 0" class="badge bg-red-50 text-red-700 ring-red-600/20">
                                {{ b.overdue_items }} (<Money :minor="b.overdue_amount_minor" currency="PKR" />)
                            </span>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-3 py-2 text-sm">
                            <a v-if="b.eligible" :href="`/bookings/${b.id}/possession-letter.pdf`" target="_blank"
                                class="text-xs px-2.5 py-1 rounded-md bg-emerald-600 text-white hover:bg-emerald-700">
                                Possession letter
                            </a>
                            <span v-else class="text-xs text-slate-400">{{ b.overdue_items > 0 ? 'Clear overdues first' : 'Pending payment' }}</span>
                        </td>
                    </tr>
                    <tr v-if="bookings.length === 0">
                        <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">No bookings in the possession window.</td>
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
    today: String,
    window_end: String,
    bookings: { type: Array, required: true },
    totals: { type: Object, required: true },
});

function daysLabel(d) {
    if (d === null) return '';
    if (d < 0) return `${Math.abs(d)} days overdue`;
    if (d === 0) return 'Today';
    return `in ${d} days`;
}
</script>
