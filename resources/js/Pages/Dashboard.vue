<template>
    <AppLayout title="Dashboard">
        <h1 class="text-2xl font-semibold text-slate-900">Welcome back, {{ $page.props.auth.user.name }}</h1>
        <p class="mt-1 text-sm text-slate-500">Operational snapshot for Zen Retreats.</p>

        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="card p-5">
                <div class="text-xs uppercase tracking-wide text-slate-500">Projects</div>
                <div class="mt-1 text-2xl font-semibold text-slate-900">{{ totals.projects }}</div>
            </div>
            <div class="card p-5">
                <div class="text-xs uppercase tracking-wide text-slate-500">Total units</div>
                <div class="mt-1 text-2xl font-semibold text-slate-900">{{ totals.units }}</div>
            </div>
            <div class="card p-5">
                <div class="text-xs uppercase tracking-wide text-slate-500">Units available</div>
                <div class="mt-1 text-2xl font-semibold text-emerald-700">{{ totals.units_available }}</div>
            </div>
            <div class="card p-5">
                <div class="text-xs uppercase tracking-wide text-slate-500">Units sold</div>
                <div class="mt-1 text-2xl font-semibold text-sky-700">{{ totals.units_sold }}</div>
            </div>
            <div class="card p-5">
                <div class="text-xs uppercase tracking-wide text-slate-500">Active bookings</div>
                <div class="mt-1 text-2xl font-semibold text-slate-900">{{ totals.bookings_active }}</div>
            </div>
            <div class="card p-5">
                <div class="text-xs uppercase tracking-wide text-slate-500">Payments (30 days)</div>
                <div class="mt-1 text-2xl font-semibold text-brand">
                    <Money :minor="totals.payments_30d || 0" />
                </div>
            </div>
        </div>

        <div class="mt-10 card p-6">
            <h2 class="text-lg font-semibold text-slate-900">Next up</h2>
            <p class="mt-1 text-sm text-slate-500">Phase 1 modules to build out, in order:</p>
            <ul class="mt-3 space-y-1 text-sm text-slate-700 list-disc list-inside">
                <li>Clients module — KYC, nominees, contact</li>
                <li>Bookings + auto-instantiated payment schedule from plan template</li>
                <li>Payments + allocations + receipt PDFs</li>
                <li>Bank reconciliation import + suggested matches</li>
                <li>Statement PDF generator (on-demand + monthly cron)</li>
                <li>Notifications engine + reminder schedules + dunning escalation</li>
            </ul>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/Layouts/AppLayout.vue';
import Money from '@/Components/Money.vue';

defineProps({
    totals: { type: Object, required: true },
});
</script>
