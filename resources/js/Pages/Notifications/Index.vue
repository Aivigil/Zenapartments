<template>
    <AppLayout title="Notifications">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Notification log</h1>
            <p class="mt-1 text-sm text-slate-500">Every reminder, receipt, and broadcast — by channel, status, and recipient.</p>
        </div>

        <div class="mt-6 card p-4 grid grid-cols-1 md:grid-cols-4 gap-3">
            <input v-model="filters.q" @keyup.enter="apply" class="input md:col-span-2" placeholder="Search subject / recipient / client…" />
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
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Queued</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Channel</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Template</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Recipient</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Client</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Subject</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="l in logs.data" :key="l.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm text-slate-700 whitespace-nowrap">{{ l.queued_at }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span class="badge bg-slate-100 text-slate-700 ring-slate-600/20">{{ l.channel }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm font-mono text-slate-600">{{ l.template_code }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ l.recipient }}</td>
                        <td class="px-4 py-3 text-sm">
                            <Link v-if="l.client" :href="`/clients/${l.client.id}`" class="text-brand">{{ l.client.full_name }}</Link>
                            <span v-else class="text-slate-400">—</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600 max-w-md truncate" :title="l.subject">{{ l.subject || '—' }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span :class="['badge', statusClass(l.status)]">{{ l.status }}</span>
                            <div v-if="l.failure_reason" class="text-xs text-red-600 mt-1 truncate max-w-[200px]" :title="l.failure_reason">{{ l.failure_reason }}</div>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="`/notifications/${l.id}`" class="text-sm text-brand hover:text-brand-dark">View</Link>
                        </td>
                    </tr>
                    <tr v-if="logs.data.length === 0">
                        <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">
                            No notifications yet. The scheduler runs daily at 09:00 PKT.
                            Run <code>php artisan reminders:dispatch</code> manually to backfill upcoming dues.
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="logs.total > logs.per_page" class="border-t border-slate-200 px-4 py-3 flex items-center justify-between text-sm text-slate-600">
                <div>{{ logs.from || 0 }}–{{ logs.to || 0 }} of {{ logs.total }}</div>
                <div class="flex gap-2">
                    <Link v-for="link in logs.links" :key="link.label" :href="link.url || ''"
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

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    lookups: { type: Object, required: true },
});

const filters = reactive({
    q: props.filters.q ?? '',
    channel: props.filters.channel ?? '',
    status: props.filters.status ?? '',
});

function statusClass(s) {
    return {
        queued:     'bg-slate-100 text-slate-700 ring-slate-600/20',
        sent:       'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        delivered:  'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        failed:     'bg-red-50 text-red-700 ring-red-600/20',
        suppressed: 'bg-amber-50 text-amber-700 ring-amber-600/20',
    }[s] || 'bg-slate-100 text-slate-700 ring-slate-600/20';
}

function apply() {
    router.get('/notifications', filters, { preserveState: true, preserveScroll: true });
}
</script>
