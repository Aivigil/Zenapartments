<template>
    <AppLayout title="Reports">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Reports</h1>
            <p class="mt-1 text-sm text-slate-500">Pick a report. Operational dashboards on the left, analytics on the right.</p>
        </div>

        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <Link v-for="r in reports" :key="r.href"
                :href="r.href"
                class="card p-5 hover:ring-2 hover:ring-brand transition group">
                <div class="flex items-start gap-3">
                    <div :class="['w-9 h-9 rounded-md flex items-center justify-center text-base', r.tone]">{{ r.icon }}</div>
                    <div class="flex-1">
                        <div class="font-semibold text-slate-900 group-hover:text-brand">{{ r.name }}</div>
                        <div class="mt-1 text-sm text-slate-500">{{ r.description }}</div>
                    </div>
                </div>
            </Link>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const reports = [
    { name: 'Collections',     icon: '💰', tone: 'bg-emerald-50 text-emerald-700', href: '/reports/collections',     description: 'Aging buckets, cash trend, top overdue clients to chase.' },
    { name: 'Cash flow',       icon: '📊', tone: 'bg-blue-50 text-blue-700',       href: '/reports/cash-flow',       description: 'Last 12 months of posted payments by month and channel.' },
    { name: 'Forecast',        icon: '🔮', tone: 'bg-violet-50 text-violet-700',   href: '/reports/forecast',        description: 'Expected cash-in over next 30–180 days, weekly breakdown.' },
    { name: 'Booking summary', icon: '📄', tone: 'bg-slate-100 text-slate-700',    href: '/reports/booking-summary', description: 'One row per active booking. Morning-coffee receivables view.' },
    { name: 'Possession',      icon: '🔑', tone: 'bg-amber-50 text-amber-700',     href: '/reports/possession',      description: 'Bookings ≥90% paid or close to final installment. Possession-ready.' },
    { name: 'Opening balances',icon: '📋', tone: 'bg-orange-50 text-orange-700',   href: '/reports/opening-balances',description: 'Snapshot import variance vs. OneDrive master. Cutover audit.' },
];
</script>
