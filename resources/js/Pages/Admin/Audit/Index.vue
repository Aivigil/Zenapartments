<template>
    <AppLayout title="Audit log">
        <div>
            <div class="text-sm text-slate-500"><Link href="/admin" class="hover:text-brand">Admin</Link> / Audit</div>
            <h1 class="text-2xl font-semibold text-slate-900">Audit log</h1>
            <p class="mt-1 text-sm text-slate-500">
                Two streams: <strong>Financial events</strong> (immutable, append-only, captures payment / booking / adjustment lifecycle) and <strong>Activity log</strong> (general entity changes).
            </p>
        </div>

        <!-- Tabs -->
        <div class="mt-4 flex gap-2 text-sm">
            <Link href="/admin/audit?stream=events" :class="tabBtn('events')">Financial events</Link>
            <Link href="/admin/audit?stream=activity" :class="tabBtn('activity')">Activity log</Link>
        </div>

        <!-- Filters -->
        <div class="mt-4 card p-4 grid grid-cols-1 md:grid-cols-4 gap-3">
            <input v-model="filters.q" @keyup.enter="apply" class="input md:col-span-2" placeholder="Search event / reason / subject…" />
            <select v-if="stream === 'events'" v-model="filters.event" @change="apply" class="input">
                <option :value="''">Any event</option>
                <option v-for="e in lookups.event_kinds" :key="e" :value="e">{{ e }}</option>
            </select>
            <select v-model="filters.days" @change="apply" class="input">
                <option :value="''">All time</option>
                <option value="1">Today</option>
                <option value="7">Last 7 days</option>
                <option value="30">Last 30 days</option>
                <option value="90">Last 90 days</option>
            </select>
        </div>

        <!-- Table -->
        <div class="mt-4 card overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">When</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Event</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Actor</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Subject</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Reason / details</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="l in logs.data" :key="`${stream}-${l.id}`" class="hover:bg-slate-50 align-top">
                        <td class="px-4 py-3 text-sm text-slate-700 whitespace-nowrap">{{ l.occurred_at }}</td>
                        <td class="px-4 py-3 text-sm font-mono text-slate-700">{{ l.event }}</td>
                        <td class="px-4 py-3 text-sm">
                            <div v-if="l.actor">{{ l.actor.label }}</div>
                            <div v-else class="text-slate-400 italic text-xs">system</div>
                            <div v-if="l.actor_role" class="text-xs text-slate-500">{{ l.actor_role }}</div>
                            <div v-if="l.ip" class="text-xs text-slate-400 font-mono">{{ l.ip }}</div>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            {{ l.subject_type }} <span class="text-slate-400">#{{ l.subject_id }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-600 max-w-md">
                            <div v-if="l.reason">{{ l.reason }}</div>
                            <div v-else-if="l.description" class="italic">{{ l.description }}</div>
                            <details v-if="l.before || l.after || l.properties" class="mt-1">
                                <summary class="text-xs text-brand cursor-pointer">diff</summary>
                                <pre class="mt-1 text-xs bg-slate-50 p-2 rounded ring-1 ring-slate-200 overflow-auto max-h-40">{{ JSON.stringify({ before: l.before, after: l.after, properties: l.properties }, null, 2) }}</pre>
                            </details>
                        </td>
                    </tr>
                    <tr v-if="logs.data.length === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">No entries match.</td>
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
    stream: { type: String, default: 'events' },
    logs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    lookups: { type: Object, required: true },
});

const filters = reactive({
    q: props.filters.q ?? '',
    event: props.filters.event ?? '',
    days: props.filters.days ?? '',
});

function tabBtn(name) {
    return ['px-3 py-1 rounded-md text-sm',
        props.stream === name
            ? 'bg-brand text-white'
            : 'bg-white ring-1 ring-slate-300 text-slate-700 hover:bg-slate-50'
    ];
}

function apply() {
    router.get('/admin/audit', { stream: props.stream, ...filters }, { preserveState: true, preserveScroll: true });
}
</script>
