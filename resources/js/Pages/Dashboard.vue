<template>
    <AppLayout title="Dashboard">
        <h1 class="text-2xl font-semibold text-slate-900">Welcome back, {{ $page.props.auth.user.name }}</h1>
        <p class="mt-1 text-sm text-slate-500">Operational snapshot for Zen Retreats.</p>

        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <Stat label="Projects"           :value="totals.projects" />
            <Stat label="Total units"        :value="totals.units" />
            <Stat label="Units available"    :value="totals.units_available" tone="emerald" />
            <Stat label="Units sold"         :value="totals.units_sold" tone="sky" />
            <Stat label="Active bookings"    :value="totals.bookings_active" />
            <Stat label="Payments (30 days)" tone="brand">
                <template #value>
                    <Money :minor="totals.payments_30d" />
                </template>
            </Stat>
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

const Stat = {
    props: ['label', 'value', 'tone'],
    template: `
        <div class="card p-5">
            <div class="text-xs uppercase tracking-wide text-slate-500">{{ label }}</div>
            <div class="mt-1 text-2xl font-semibold" :class="toneClass">
                <slot name="value">{{ value }}</slot>
            </div>
        </div>
    `,
    computed: {
        toneClass() {
            return {
                emerald: 'text-emerald-700',
                sky: 'text-sky-700',
                brand: 'text-brand',
            }[this.tone] || 'text-slate-900';
        },
    },
};
</script>
