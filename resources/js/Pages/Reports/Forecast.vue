<template>
    <AppLayout title="Sales forecast">
        <div>
            <div class="text-sm text-slate-500"><Link href="/reports" class="hover:text-brand">Reports</Link> / Forecast</div>
            <div class="flex items-end justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">Forecast — next {{ horizon_days }} days</h1>
                    <p class="mt-1 text-sm text-slate-500">Expected cash-in from open schedule rows. Adjusted forecast applies last-30-day collection rate as a realism filter.</p>
                </div>
                <select :value="horizon_days" @change="setHorizon($event.target.value)" class="input w-auto">
                    <option :value="30">30 days</option>
                    <option :value="60">60 days</option>
                    <option :value="90">90 days</option>
                    <option :value="120">120 days</option>
                    <option :value="180">180 days</option>
                </select>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-3">
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Total expected</div>
                <div class="mt-1 text-xl font-semibold text-slate-900"><Money :minor="total_expected_minor" currency="PKR" /></div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wide text-amber-600">Adjusted (collection rate)</div>
                <div class="mt-1 text-xl font-semibold text-amber-700">
                    <Money v-if="benchmark.adjusted_forecast_minor !== null" :minor="benchmark.adjusted_forecast_minor" currency="PKR" />
                    <span v-else class="text-slate-400">—</span>
                </div>
                <div class="text-xs text-slate-500 mt-1">
                    Rate: {{ benchmark.collection_rate !== null ? (benchmark.collection_rate * 100).toFixed(1) + '%' : 'n/a' }}
                </div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wide text-emerald-600">Actual last 30d</div>
                <div class="mt-1 text-xl font-semibold text-emerald-700"><Money :minor="benchmark.actual_last_30_minor" currency="PKR" /></div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Scheduled last 30d</div>
                <div class="mt-1 text-xl font-semibold text-slate-900"><Money :minor="benchmark.scheduled_last_30_minor" currency="PKR" /></div>
            </div>
        </div>

        <div class="mt-6 card p-5">
            <h2 class="text-base font-semibold text-slate-900">Weekly</h2>
            <div class="mt-3 space-y-2">
                <div v-for="w in weeks" :key="w.key" class="flex items-center gap-3">
                    <div class="w-36 text-xs text-slate-500">{{ w.label }}</div>
                    <div class="flex-1 h-7 bg-slate-100 rounded overflow-hidden relative">
                        <div class="h-full bg-brand transition-all" :style="{ width: barWidth(w.expected_minor) }"></div>
                        <div class="absolute inset-0 flex items-center px-2 text-xs font-medium text-slate-900">
                            <Money :minor="w.expected_minor" currency="PKR" />
                            <span v-if="w.count > 0" class="text-slate-500 ml-2">({{ w.count }} items)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Money from '@/Components/Money.vue';

const props = defineProps({
    today: String,
    horizon_days: Number,
    weeks: { type: Array, required: true },
    total_expected_minor: Number,
    max_weekly_minor: Number,
    benchmark: { type: Object, required: true },
});

function barWidth(v) {
    return Math.max(2, Math.round((v / Math.max(1, props.max_weekly_minor)) * 100)) + '%';
}
function setHorizon(d) {
    router.get('/reports/forecast', { days: d }, { preserveScroll: true });
}
</script>
