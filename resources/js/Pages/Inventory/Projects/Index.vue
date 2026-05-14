<template>
    <AppLayout title="Projects">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Projects</h1>
                <p class="mt-1 text-sm text-slate-500">Top-level developments. Each contains blocks and units.</p>
            </div>
            <Link href="/inventory/projects/create" class="btn-primary">+ New project</Link>
        </div>

        <div class="mt-6 card overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Location</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Blocks</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Units</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="p in projects" :key="p.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm font-mono text-slate-600">{{ p.code }}</td>
                        <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ p.name }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ p.location }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ p.blocks_count }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ p.units_count }}</td>
                        <td class="px-4 py-3"><StatusBadge :status="p.status" :label="p.status" /></td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="`/inventory/projects/${p.id}`" class="text-sm text-brand hover:text-brand-dark">View</Link>
                        </td>
                    </tr>
                    <tr v-if="projects.length === 0">
                        <td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">
                            No projects yet. <Link href="/inventory/projects/create" class="text-brand">Create the first one.</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

defineProps({
    projects: { type: Array, required: true },
});
</script>
