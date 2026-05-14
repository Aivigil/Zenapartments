<template>
    <AppLayout title="Units">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Units</h1>
                <p class="mt-1 text-sm text-slate-500">Every sellable plot, chalet, or apartment.</p>
            </div>
            <Link href="/inventory/units/create" class="btn-primary">+ New unit</Link>
        </div>

        <div class="mt-6 card p-4 grid grid-cols-1 md:grid-cols-4 gap-3">
            <select v-model="filters.project_id" @change="apply" class="input">
                <option :value="''">All projects</option>
                <option v-for="p in lookups.projects" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
            <select v-model="filters.status" @change="apply" class="input">
                <option :value="''">Any status</option>
                <option v-for="s in lookups.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
            <input v-model="filters.q" @keyup.enter="apply" class="input" placeholder="Search by code or name…" />
            <button @click="apply" class="btn-secondary">Apply filters</button>
        </div>

        <div class="mt-4 card overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Project</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Block</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Category</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Size</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Base price</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="u in units.data" :key="u.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm font-mono text-slate-700">{{ u.code }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ u.project?.name }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ u.block?.name }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ u.category?.name }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ u.size }}</td>
                        <td class="px-4 py-3 text-sm text-right"><Money :minor="u.base_price_minor" :currency="u.currency" /></td>
                        <td class="px-4 py-3"><StatusBadge :status="u.status" :label="u.status_label" /></td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="`/inventory/units/${u.id}`" class="text-sm text-brand hover:text-brand-dark">View</Link>
                        </td>
                    </tr>
                    <tr v-if="units.data.length === 0">
                        <td colspan="8" class="px-4 py-8 text-center text-sm text-slate-500">
                            No units match the filters.
                        </td>
                    </tr>
                </tbody>
            </table>

            <div class="border-t border-slate-200 px-4 py-3 flex items-center justify-between text-sm text-slate-600">
                <div>{{ units.from || 0 }}–{{ units.to || 0 }} of {{ units.total }}</div>
                <div class="flex gap-2">
                    <Link v-for="link in units.links" :key="link.label" :href="link.url || ''"
                          :class="['px-3 py-1 rounded-md', link.active ? 'bg-brand text-white' : 'bg-white ring-1 ring-slate-300 text-slate-700']"
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
import Money from '@/Components/Money.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    units: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    lookups: { type: Object, required: true },
});

const filters = reactive({
    project_id: props.filters.project_id ?? '',
    status: props.filters.status ?? '',
    q: props.filters.q ?? '',
});

function apply() {
    router.get('/inventory/units', filters, { preserveState: true, preserveScroll: true });
}
</script>
