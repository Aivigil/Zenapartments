<template>
    <AppLayout :title="block.name">
        <div class="flex items-start justify-between">
            <div>
                <div class="text-sm text-slate-500">
                    <Link :href="`/inventory/projects/${block.project.id}`" class="hover:text-brand">{{ block.project.name }}</Link>
                    <span class="mx-1">/</span>
                    <span class="font-mono">{{ block.code }}</span>
                </div>
                <h1 class="text-2xl font-semibold text-slate-900">{{ block.name }}</h1>
                <div class="mt-1 text-sm text-slate-600">{{ block.block_type }}</div>
            </div>
            <Link :href="`/inventory/blocks/${block.id}/edit`" class="btn-secondary">Edit</Link>
        </div>

        <h2 class="mt-8 text-lg font-semibold text-slate-900">Units in this block</h2>
        <div class="mt-3 card overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Category</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Size</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Base price</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="u in block.units" :key="u.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm font-mono text-slate-600">
                            <Link :href="`/inventory/units/${u.id}`" class="hover:text-brand">{{ u.code }}</Link>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ u.category }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ u.size_value }} {{ u.size_unit }}</td>
                        <td class="px-4 py-3 text-sm text-right"><Money :minor="u.base_price_minor" :currency="u.currency" /></td>
                        <td class="px-4 py-3"><StatusBadge :status="u.status" :label="u.status" /></td>
                    </tr>
                    <tr v-if="block.units.length === 0">
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No units in this block yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Money from '@/Components/Money.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

defineProps({
    block: { type: Object, required: true },
});
</script>
