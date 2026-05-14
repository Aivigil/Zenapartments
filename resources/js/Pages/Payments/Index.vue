<template>
    <AppLayout title="Payments">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Payments</h1>
                <p class="mt-1 text-sm text-slate-500">Receipts recorded against active bookings. FIFO-allocated to schedules.</p>
            </div>
            <Link href="/payments/create" class="btn-primary">+ Record payment</Link>
        </div>

        <div class="mt-6 card p-4 grid grid-cols-1 md:grid-cols-4 gap-3">
            <input v-model="filters.q" @keyup.enter="apply" class="input md:col-span-2" placeholder="Search by code, client, or bank ref…" />
            <select v-model="filters.channel" @change="apply" class="input">
                <option :value="''">Any channel</option>
                <option v-for="c in lookups.channels" :key="c.value" :value="c.value">{{ c.label }}</option>
            </select>
            <select v-model="filters.status" @change="apply" class="input">
                <option :value="''">Any status</option>
                <option v-for="s in lookups.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
        </div>

        <div class="mt-4 card overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Receipt</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Client</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Booking</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Channel</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="p in payments.data" :key="p.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm font-mono text-slate-700">{{ p.code }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ p.received_at }}</td>
                        <td class="px-4 py-3 text-sm">{{ p.client?.full_name }}</td>
                        <td class="px-4 py-3 text-sm font-mono text-slate-600">{{ p.booking_code }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ p.channel_label }}</td>
                        <td class="px-4 py-3 text-sm text-right"><Money :minor="p.pkr_amount_minor || p.amount_minor" :currency="'PKR'" /></td>
                        <td class="px-4 py-3 text-sm">
                            <span :class="['badge', p.status === 'posted' ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-red-50 text-red-700 ring-red-600/20']">
                                {{ p.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="`/payments/${p.id}`" class="text-sm text-brand hover:text-brand-dark">View</Link>
                        </td>
                    </tr>
                    <tr v-if="payments.data.length === 0">
                        <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">
                            No payments yet. <Link href="/payments/create" class="text-brand">Record one.</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Money from '@/Components/Money.vue';

const props = defineProps({
    payments: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    lookups: { type: Object, required: true },
});

const filters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
    channel: props.filters.channel ?? '',
});

function apply() {
    router.get('/payments', filters, { preserveState: true, preserveScroll: true });
}
</script>
