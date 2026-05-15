<template>
    <AppLayout title="Opening balances variance">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Opening balances — variance report</h1>
            <p class="mt-1 text-sm text-slate-500">
                Portal-computed outstanding vs spreadsheet <code>Total Receivable</code> from the master receivables CSV.
                Use this after every <code>migrate:snapshot</code> run to confirm variance = 0.
            </p>
        </div>

        <div v-if="!file_exists" class="mt-6 card p-5 bg-amber-50 ring-2 ring-amber-200">
            <h2 class="text-sm font-semibold text-amber-800">CSV not found</h2>
            <p class="mt-1 text-sm text-amber-700">
                Expected file at <code>{{ file }}</code>. Drop the master receivables CSV there and refresh.
            </p>
        </div>

        <!-- Totals strip -->
        <div v-if="file_exists" class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="card p-5">
                <div class="text-xs uppercase tracking-wide text-slate-500">Clean (variance = 0)</div>
                <div class="mt-1 text-2xl font-semibold text-emerald-700">{{ totals.clean }}</div>
            </div>
            <div class="card p-5">
                <div class="text-xs uppercase tracking-wide text-slate-500">Flagged</div>
                <div class="mt-1 text-2xl font-semibold" :class="totals.flagged > 0 ? 'text-red-700' : 'text-slate-400'">{{ totals.flagged }}</div>
            </div>
            <div class="card p-5">
                <div class="text-xs uppercase tracking-wide text-slate-500">Missing in portal</div>
                <div class="mt-1 text-2xl font-semibold" :class="totals.missing > 0 ? 'text-amber-700' : 'text-slate-400'">{{ totals.missing }}</div>
            </div>
            <div class="card p-5">
                <div class="text-xs uppercase tracking-wide text-slate-500">Spreadsheet vs portal</div>
                <div class="mt-1 text-sm">
                    <div>Expected: <Money :minor="totals.expected_minor" currency="PKR" /></div>
                    <div>Portal:&nbsp;&nbsp;&nbsp; <Money :minor="totals.portal_minor" currency="PKR" /></div>
                    <div :class="netVarianceColor">
                        Net: <Money :minor="totals.portal_minor - totals.expected_minor" currency="PKR" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Sign-off banner -->
        <div v-if="file_exists && totals.flagged === 0 && totals.missing === 0" class="mt-6 card p-5 bg-emerald-50 ring-2 ring-emerald-200">
            <div class="flex items-center gap-3">
                <div class="text-2xl">✅</div>
                <div>
                    <h2 class="text-sm font-semibold text-emerald-800">Reconciliation clean — variance = 0 across all customers</h2>
                    <p class="mt-1 text-sm text-emerald-700">Safe for Finance Manager sign-off. Lock spreadsheets as read-only archive; portal is system of record from today.</p>
                </div>
            </div>
        </div>

        <!-- Variance table -->
        <div v-if="file_exists" class="mt-6 card overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Apt</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Category</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Spreadsheet</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Portal</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Variance</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="r in rows" :key="r.apt_code" :class="rowClass(r)">
                        <td class="px-4 py-3 text-sm font-mono text-slate-700">{{ r.apt_code }}</td>
                        <td class="px-4 py-3 text-sm">{{ r.customer }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ r.category }}</td>
                        <td class="px-4 py-3 text-sm text-right"><Money :minor="r.expected_minor" currency="PKR" /></td>
                        <td class="px-4 py-3 text-sm text-right">
                            <Money v-if="r.portal_minor !== null" :minor="r.portal_minor" currency="PKR" />
                            <span v-else class="text-slate-400 italic">—</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-right" :class="varianceColor(r)">
                            <template v-if="r.variance_minor !== null">
                                {{ r.variance_minor > 0 ? '+' : '' }}<Money :minor="r.variance_minor" currency="PKR" />
                            </template>
                            <span v-else>—</span>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <span :class="['badge', statusClass(r.status)]">{{ statusLabel(r.status) }}</span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link v-if="r.booking_id" :href="`/bookings/${r.booking_id}`" class="text-sm text-brand hover:text-brand-dark">Open booking</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Money from '@/Components/Money.vue';

const props = defineProps({
    rows: { type: Array, required: true },
    totals: { type: Object, required: true },
    file: { type: String, required: true },
    file_exists: { type: Boolean, required: true },
});

const netVarianceColor = computed(() => {
    const v = props.totals.portal_minor - props.totals.expected_minor;
    if (v === 0) return 'text-emerald-700 font-medium';
    return Math.abs(v) > 100 ? 'text-red-700 font-medium' : 'text-amber-700';
});

function rowClass(r) {
    if (r.status === 'flagged') return 'bg-red-50/30';
    if (r.status === 'missing') return 'bg-amber-50/30';
    return '';
}
function varianceColor(r) {
    if (r.status !== 'flagged') return 'text-slate-700';
    return Math.abs(r.variance_minor) > 10000 ? 'text-red-700 font-medium' : 'text-amber-700';
}
function statusClass(s) {
    return {
        clean:   'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        flagged: 'bg-red-50 text-red-700 ring-red-600/20',
        missing: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    }[s] || 'bg-slate-100 text-slate-700 ring-slate-600/20';
}
function statusLabel(s) {
    return { clean: '✓ Clean', flagged: '⚠ Diff', missing: '⚠ Missing' }[s] || s;
}
</script>
