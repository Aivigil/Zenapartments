<template>
    <AppLayout :title="unit.code">
        <div class="flex items-start justify-between">
            <div>
                <div class="text-sm text-slate-500">
                    <Link :href="`/inventory/projects/${unit.project.id}`" class="hover:text-brand">{{ unit.project.name }}</Link>
                    <span class="mx-1">/</span>
                    <Link v-if="unit.block" :href="`/inventory/blocks/${unit.block.id}`" class="hover:text-brand">{{ unit.block.name }}</Link>
                    <span class="mx-1">/</span>
                    <span class="font-mono">{{ unit.code }}</span>
                </div>
                <h1 class="text-2xl font-semibold text-slate-900">{{ unit.name || unit.code }}</h1>
                <div class="mt-1 text-sm text-slate-600">{{ unit.category.name }} ({{ unit.category.kind }})</div>
                <div class="mt-2 flex items-center gap-2">
                    <StatusBadge :status="unit.status" :label="unit.status_label" />
                </div>
            </div>
            <Link :href="`/inventory/units/${unit.id}/edit`" class="btn-secondary">Edit</Link>
        </div>

        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="card p-5">
                <div class="text-xs uppercase tracking-wide text-slate-500">Base price</div>
                <div class="mt-1 text-2xl font-semibold text-slate-900">
                    <Money :minor="unit.base_price_minor" :currency="unit.currency" />
                </div>
            </div>
            <div class="card p-5">
                <div class="text-xs uppercase tracking-wide text-slate-500">Size</div>
                <div class="mt-1 text-2xl font-semibold text-slate-900">
                    <span v-if="unit.size_value">{{ unit.size_value }} {{ unit.size_unit }}</span>
                    <span v-else class="text-slate-400">—</span>
                </div>
            </div>
            <div class="card p-5">
                <div class="text-xs uppercase tracking-wide text-slate-500">Currency</div>
                <div class="mt-1 text-2xl font-semibold text-slate-900">{{ unit.currency }}</div>
            </div>
        </div>

        <div class="mt-6 card p-5">
            <h2 class="text-sm font-semibold text-slate-900">Attributes</h2>
            <dl class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                <template v-for="(value, key) in (unit.attributes || {})" :key="key">
                    <div>
                        <dt class="text-slate-500">{{ key }}</dt>
                        <dd class="text-slate-900">{{ value }}</dd>
                    </div>
                </template>
                <div v-if="!unit.attributes || Object.keys(unit.attributes).length === 0" class="text-slate-400">
                    No attributes recorded.
                </div>
            </dl>
        </div>

        <div v-if="unit.notes" class="mt-4 card p-5">
            <h2 class="text-sm font-semibold text-slate-900">Notes</h2>
            <p class="mt-2 text-sm text-slate-700 whitespace-pre-line">{{ unit.notes }}</p>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Money from '@/Components/Money.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

defineProps({
    unit: { type: Object, required: true },
});
</script>
