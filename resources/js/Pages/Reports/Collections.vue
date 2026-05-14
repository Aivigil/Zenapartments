<template>
    <AppLayout title="Collections">
        <div class="flex items-end justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Collections</h1>
                <p class="mt-1 text-sm text-slate-500">Aging buckets, cash trend, and the clients to chase first.</p>
            </div>
            <div class="text-xs text-slate-500">As of {{ today }}</div>
        </div>

        <!-- Top-line totals -->
        <div class="mt-6 grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="card p-5">
                <div class="text-xs uppercase tracking-wide text-slate-500">Total overdue</div>
                <div class="mt-1 text-2xl font-semibold text-red-700"><Money :minor="totals.overdue_minor" currency="PKR" /></div>
                <div class="text-xs text-slate-500 mt-1">Sum of unpaid schedule items past their due date.</div>
            </div>
            <div class="card p-5">
                <div class="text-xs uppercase tracking-wide text-slate-500">All open obligations</div>
                <div class="mt-1 text-2xl font-semibold text-slate-900"><Money :minor="totals.all_open_minor" currency="PKR" /></div>
                <div class="text-xs text-slate-500 mt-1">Including future installments.</div>
            </div>
            <div class="card p-5">
                <div class="text-xs uppercase tracking-wide text-slate-500">Cash collected — last 12 mo</div>
                <div class="mt-1 text-2xl font-semibold text-emerald-700"><Money :minor="totals.cash_in_12mo" currency="PKR" /></div>
                <div class="text-xs text-slate-500 mt-1">Posted payments only; reversed excluded.</div>
            </div>
        </div>

        <!-- Aging breakdown -->
        <div class="mt-8">
            <h2 class="text-base font-semibold text-slate-900">Aging breakdown</h2>
            <div class="mt-3 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                <div v-for="b in buckets" :key="b.key" :class="['card p-4', bucketBorder(b.tone)]">
                    <div class="text-xs font-medium uppercase tracking-wide" :class="bucketText(b.tone)">{{ b.label }}</div>
                    <div class="mt-1 text-xl font-semibold text-slate-900"><Money :minor="b.owed_minor" currency="PKR" /></div>
                    <div class="text-xs text-slate-500 mt-0.5">{{ b.count }} item{{ b.count === 1 ? '' : 's' }}</div>
                </div>
            </div>
        </div>

        <!-- Top overdue clients -->
        <div class="mt-8">
            <div class="flex items-end justify-between">
                <h2 class="text-base font-semibold text-slate-900">Top 10 overdue clients</h2>
                <div class="text-xs text-slate-500">Ordered by total overdue, oldest item highlighted</div>
            </div>
            <div class="mt-3 card overflow-hidden">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">#</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Client</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Phone</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Overdue items</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Total overdue</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Oldest</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="(c, idx) in top_overdue" :key="c.client_id" class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-sm text-slate-500">{{ idx + 1 }}</td>
                            <td class="px-4 py-3 text-sm">
                                <div class="font-medium">{{ c.client_name }}</div>
                                <div class="text-xs text-slate-500 font-mono">{{ c.client_code }}</div>
                            </td>
                            <td class="px-4 py-3 text-sm text-slate-600">{{ c.phone }}</td>
                            <td class="px-4 py-3 text-sm text-right">{{ c.overdue_items }}</td>
                            <td class="px-4 py-3 text-sm text-right font-medium text-red-700"><Money :minor="c.overdue_minor" currency="PKR" /></td>
                            <td class="px-4 py-3 text-sm text-slate-600">
                                <div>{{ c.oldest_due }}</div>
                                <div class="text-xs text-red-600">{{ c.days_overdue }} days overdue</div>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Link :href="`/clients/${c.client_id}`" class="text-sm text-brand hover:text-brand-dark">View</Link>
                            </td>
                        </tr>
                        <tr v-if="top_overdue.length === 0">
                            <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">
                                No overdue clients. Everyone's current 🎉
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Cash-in trend -->
        <div class="mt-8">
            <h2 class="text-base font-semibold text-slate-900">Cash collected — last 12 months</h2>
            <div class="mt-3 card p-5">
                <div class="space-y-2">
                    <div v-for="m in cash_in.months" :key="m.month" class="flex items-center gap-3">
                        <div class="w-20 text-xs text-slate-500">{{ m.label }}</div>
                        <div class="flex-1 h-7 bg-slate-100 rounded overflow-hidden relative">
                            <div class="h-full bg-brand transition-all"
                                 :style="{ width: barWidth(m.total_minor) }"></div>
                            <div class="absolute inset-0 flex items-center px-2 text-xs font-medium text-slate-900">
                                <Money :minor="m.total_minor" currency="PKR" />
                            </div>
                        </div>
                    </div>
                </div>
                <div v-if="cash_in.months.every(m => m.total_minor === 0)" class="mt-2 text-center text-sm text-slate-500">
                    No payments posted yet in the last 12 months.
                </div>
            </div>
        </div>

        <!-- Booking pipeline -->
        <div class="mt-8 mb-12">
            <h2 class="text-base font-semibold text-slate-900">Booking pipeline</h2>
            <div class="mt-3 grid grid-cols-3 gap-3">
                <div class="card p-4">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Active</div>
                    <div class="mt-1 text-2xl font-semibold text-emerald-700">{{ bookings.active }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Completed</div>
                    <div class="mt-1 text-2xl font-semibold text-sky-700">{{ bookings.completed }}</div>
                </div>
                <div class="card p-4">
                    <div class="text-xs uppercase tracking-wide text-slate-500">Cancelled</div>
                    <div class="mt-1 text-2xl font-semibold text-slate-700">{{ bookings.cancelled }}</div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Money from '@/Components/Money.vue';

const props = defineProps({
    today: { type: String, required: true },
    buckets: { type: Array, required: true },
    totals: { type: Object, required: true },
    top_overdue: { type: Array, required: true },
    cash_in: { type: Object, required: true },
    bookings: { type: Object, required: true },
});

function bucketBorder(tone) {
    return {
        sky:    'ring-2 ring-sky-200',
        slate:  'ring-1 ring-slate-200',
        amber:  'ring-2 ring-amber-200',
        orange: 'ring-2 ring-orange-300',
        red:    'ring-2 ring-red-300',
    }[tone] || 'ring-1 ring-slate-200';
}
function bucketText(tone) {
    return {
        sky:    'text-sky-700',
        slate:  'text-slate-500',
        amber:  'text-amber-700',
        orange: 'text-orange-700',
        red:    'text-red-700',
    }[tone] || 'text-slate-500';
}
function barWidth(value) {
    const max = props.cash_in.max || 1;
    return Math.max(2, Math.round((value / max) * 100)) + '%';
}
</script>
