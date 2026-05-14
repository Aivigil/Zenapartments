<template>
    <AppLayout title="Bookings">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Bookings</h1>
                <p class="mt-1 text-sm text-slate-500">Client → unit → plan, with auto-generated schedule.</p>
            </div>
            <Link href="/bookings/create" class="btn-primary">+ New booking</Link>
        </div>

        <div class="mt-6 card p-4 grid grid-cols-1 md:grid-cols-3 gap-3">
            <input v-model="filters.q" @keyup.enter="apply" class="input md:col-span-2" placeholder="Search by booking code, client, or unit…" />
            <select v-model="filters.status" @change="apply" class="input">
                <option :value="''">Any status</option>
                <option v-for="s in lookups.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
        </div>

        <div class="mt-4 card overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Client</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Unit</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Plan</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Date</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Total</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="b in bookings.data" :key="b.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm font-mono text-slate-700">{{ b.code }}</td>
                        <td class="px-4 py-3 text-sm">{{ b.client?.full_name }}</td>
                        <td class="px-4 py-3 text-sm font-mono text-slate-600">{{ b.unit?.code }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ b.plan?.name }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ b.booking_date }}</td>
                        <td class="px-4 py-3 text-sm text-right"><Money :minor="b.total_price_minor" :currency="b.currency" /></td>
                        <td class="px-4 py-3"><StatusBadge :status="b.status" :label="b.status" /></td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="`/bookings/${b.id}`" class="text-sm text-brand hover:text-brand-dark">View</Link>
                        </td>
                    </tr>
                    <tr v-if="bookings.data.length === 0">
                        <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">
                            No bookings yet. <Link href="/bookings/create" class="text-brand">Create the first one.</Link>
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
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    bookings: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    lookups: { type: Object, required: true },
});

const filters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
});

function apply() {
    router.get('/bookings', filters, { preserveState: true, preserveScroll: true });
}
</script>
