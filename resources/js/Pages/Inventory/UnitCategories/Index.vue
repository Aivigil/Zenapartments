<template>
    <AppLayout title="Unit categories">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Unit categories</h1>
                <p class="mt-1 text-sm text-slate-500">Reusable types of sellable inventory.</p>
            </div>
            <Link href="/inventory/unit-categories/create" class="btn-primary">+ New category</Link>
        </div>

        <div class="mt-6 card overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Kind</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Units</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="c in categories" :key="c.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm font-mono text-slate-600">{{ c.code }}</td>
                        <td class="px-4 py-3 text-sm font-medium text-slate-900">{{ c.name }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ c.kind }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ c.units_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="`/inventory/unit-categories/${c.id}/edit`" class="text-sm text-brand hover:text-brand-dark">Edit</Link>
                        </td>
                    </tr>
                    <tr v-if="categories.length === 0">
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No categories yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
defineProps({ categories: { type: Array, required: true } });
</script>
