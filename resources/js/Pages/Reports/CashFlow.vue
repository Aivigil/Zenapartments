<template>
    <AppLayout title="Cash flow">
        <div>
            <div class="text-sm text-slate-500"><Link href="/reports" class="hover:text-brand">Reports</Link> / Cash flow</div>
            <h1 class="text-2xl font-semibold text-slate-900">Cash flow — last 12 months</h1>
            <p class="mt-1 text-sm text-slate-500">Posted payments by month and channel. Reversed payments excluded.</p>
        </div>

        <div class="mt-6 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">12-month total</div>
                <div class="mt-1 text-xl font-semibold text-emerald-700"><Money :minor="total_minor" currency="PKR" /></div>
            </div>
            <div v-for="c in channels" :key="c" class="card p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">{{ channelLabel(c) }}</div>
                <div class="mt-1 text-xl font-semibold text-slate-900"><Money :minor="channel_totals[c]" currency="PKR" /></div>
            </div>
        </div>

        <div class="mt-6 card p-5">
            <h2 class="text-base font-semibold text-slate-900">Monthly</h2>
            <div class="mt-3 space-y-2">
                <div v-for="m in months" :key="m.month" class="flex items-center gap-3">
                    <div class="w-24 text-xs text-slate-500">{{ m.label }}</div>
                    <div class="flex-1 h-7 bg-slate-100 rounded overflow-hidden relative">
                        <div class="h-full bg-brand transition-all" :style="{ width: barWidth(m.total_minor) }"></div>
                        <div class="absolute inset-0 flex items-center px-2 text-xs font-medium text-slate-900">
                            <Money :minor="m.total_minor" currency="PKR" />
                            <span v-if="m.count > 0" class="text-slate-500 ml-2">({{ m.count }} txns)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 card overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Month</th>
                        <th v-for="c in channels" :key="c" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-slate-500">{{ channelLabel(c) }}</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="m in months" :key="m.month" class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm">{{ m.label }}</td>
                        <td v-for="c in channels" :key="c" class="px-4 py-3 text-sm text-right">
                            <Money v-if="m.by_channel[c] > 0" :minor="m.by_channel[c]" currency="PKR" />
                            <span v-else class="text-slate-300">—</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-right font-medium">
                            <Money :minor="m.total_minor" currency="PKR" />
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

const props = defineProps({
    months: { type: Array, required: true },
    total_minor: { type: Number, required: true },
    max_minor: { type: Number, required: true },
    channel_totals: { type: Object, required: true },
    channels: { type: Array, required: true },
});

function channelLabel(c) {
    return { bank_transfer: 'Bank transfer', cash: 'Cash', cheque: 'Cheque', online_gateway: 'Online', foreign_wire: 'Foreign wire' }[c] || c;
}
function barWidth(v) {
    return Math.max(2, Math.round((v / Math.max(1, props.max_minor)) * 100)) + '%';
}
</script>
