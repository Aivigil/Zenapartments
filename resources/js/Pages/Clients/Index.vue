<template>
    <AppLayout title="Clients">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Clients</h1>
                <p class="mt-1 text-sm text-slate-500">Every buyer, with KYC status and booking activity.</p>
            </div>
            <Link href="/clients/create" class="btn-primary">+ New client</Link>
        </div>

        <div class="mt-6 card p-4 grid grid-cols-1 md:grid-cols-3 gap-3">
            <input v-model="filters.q" @keyup.enter="apply" class="input md:col-span-2" placeholder="Search by name, code, phone, or email…" />
            <select v-model="filters.kyc_status" @change="apply" class="input">
                <option :value="''">Any KYC status</option>
                <option v-for="s in lookups.kyc_statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
        </div>

        <div class="mt-4 card overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Phone</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Resides</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">KYC</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Bookings</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="c in clients.data" :key="c.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm font-mono text-slate-600">{{ c.code }}</td>
                        <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ c.full_name }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ c.primary_phone }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ c.email || '—' }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ c.country_of_residence || '—' }}</td>
                        <td class="px-4 py-3"><KycBadge :status="c.kyc_status" /></td>
                        <td class="px-4 py-3 text-sm text-right text-slate-600">{{ c.bookings_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="`/clients/${c.id}`" class="text-sm text-brand hover:text-brand-dark">View</Link>
                        </td>
                    </tr>
                    <tr v-if="clients.data.length === 0">
                        <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">
                            No clients match. <Link href="/clients/create" class="text-brand">Add the first one.</Link>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="clients.total > clients.per_page" class="border-t border-slate-200 px-4 py-3 flex items-center justify-between text-sm text-slate-600">
                <div>{{ clients.from || 0 }}–{{ clients.to || 0 }} of {{ clients.total }}</div>
                <div class="flex gap-2">
                    <Link v-for="link in clients.links" :key="link.label" :href="link.url || ''"
                          :class="['px-3 py-1 rounded-md', link.active ? 'bg-brand text-white' : 'bg-white ring-1 ring-slate-300 text-slate-700', !link.url ? 'opacity-50 pointer-events-none' : '']"
                          v-html="link.label" />
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import KycBadge from '@/Components/KycBadge.vue';

const props = defineProps({
    clients: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    lookups: { type: Object, required: true },
});

const filters = reactive({
    q: props.filters.q ?? '',
    kyc_status: props.filters.kyc_status ?? '',
});

function apply() {
    router.get('/clients', filters, { preserveState: true, preserveScroll: true });
}
</script>
