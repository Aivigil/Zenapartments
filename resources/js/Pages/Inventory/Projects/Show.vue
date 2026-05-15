<template>
    <AppLayout :title="project.name">
        <div class="flex items-start justify-between">
            <div>
                <div class="text-sm text-slate-500 font-mono">{{ project.code }}</div>
                <h1 class="text-2xl font-semibold text-slate-900">{{ project.name }}</h1>
                <div class="mt-1 text-sm text-slate-600">{{ project.location }} · {{ project.city }} · {{ project.country }}</div>
                <div class="mt-2"><StatusBadge :status="project.status" :label="project.status" /></div>
            </div>
            <div class="flex gap-2">
                <Link :href="`/inventory/projects/${project.id}/grid`" class="btn-secondary">View grid</Link>
                <Link :href="`/inventory/projects/${project.id}/edit`" class="btn-secondary">Edit</Link>
                <Link :href="`/inventory/projects/${project.id}/blocks/create`" class="btn-primary">+ Add block</Link>
            </div>
        </div>

        <h2 class="mt-8 text-lg font-semibold text-slate-900">Blocks</h2>
        <div class="mt-3 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <Link v-for="b in project.blocks" :key="b.id" :href="`/inventory/blocks/${b.id}`"
                  class="card p-5 hover:shadow-md transition">
                <div class="text-sm font-mono text-slate-500">{{ b.code }}</div>
                <div class="mt-1 text-lg font-semibold text-slate-900">{{ b.name }}</div>
                <div class="mt-2 text-sm text-slate-600">{{ b.block_type }} · {{ b.units_count }} units</div>
            </Link>
            <div v-if="project.blocks.length === 0" class="md:col-span-2 lg:col-span-3 card p-6 text-center text-sm text-slate-500">
                No blocks yet. Add the first one to start placing units.
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

defineProps({
    project: { type: Object, required: true },
});
</script>
