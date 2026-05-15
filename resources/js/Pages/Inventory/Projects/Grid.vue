<template>
    <AppLayout :title="`${project.name} — inventory grid`">
        <div>
            <div class="text-sm text-slate-500">
                <Link href="/inventory/projects" class="hover:text-brand">Inventory</Link>
                / <Link :href="`/inventory/projects/${project.id}`" class="hover:text-brand">{{ project.name }}</Link>
                / Grid
            </div>
            <div class="flex items-end justify-between flex-wrap gap-3">
                <div>
                    <h1 class="text-2xl font-semibold text-slate-900">{{ project.name }}</h1>
                    <p class="mt-1 text-sm text-slate-500">{{ project.location }} · color-coded inventory map</p>
                </div>
                <div class="flex items-center gap-2 text-xs">
                    <label class="text-slate-500 mr-1">Filter status:</label>
                    <button v-for="opt in filterOpts" :key="opt.key"
                        @click="toggleFilter(opt.key)"
                        :class="['px-2.5 py-1 rounded-md ring-1 transition',
                                 statusFilter.has(opt.key)
                                   ? `${opt.activeRing} ${opt.activeBg} ${opt.activeText}`
                                   : 'ring-slate-200 bg-white text-slate-600 hover:bg-slate-50']">
                        <span class="inline-block w-2 h-2 rounded-full mr-1.5 align-middle" :class="opt.dot"></span>{{ opt.label }}
                    </button>
                </div>
            </div>
        </div>

        <!-- KPI row -->
        <div class="mt-6 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Total units</div>
                <div class="mt-1 text-xl font-semibold text-slate-900">{{ totals.count }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wide text-emerald-600">Available</div>
                <div class="mt-1 text-xl font-semibold text-emerald-700">{{ totals.available }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wide text-blue-600">Sold</div>
                <div class="mt-1 text-xl font-semibold text-blue-700">{{ totals.sold }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wide text-amber-600">Blocked</div>
                <div class="mt-1 text-xl font-semibold text-amber-700">{{ totals.blocked }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wide text-purple-600">Possession</div>
                <div class="mt-1 text-xl font-semibold text-purple-700">{{ totals.possession }}</div>
            </div>
            <div class="card p-4">
                <div class="text-xs uppercase tracking-wide text-slate-500">Outstanding</div>
                <div class="mt-1 text-xl font-semibold text-red-700"><Money :minor="totals.outstanding_minor" currency="PKR" /></div>
            </div>
        </div>

        <!-- Blocks -->
        <div v-for="block in blocks" :key="block.id" class="mt-8">
            <div class="flex items-end justify-between mb-3">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Block {{ block.code }}<span v-if="block.name && block.name !== block.code" class="text-slate-500 font-normal"> — {{ block.name }}</span></h2>
                    <div class="text-xs text-slate-500">
                        {{ block.totals.count }} units · {{ block.totals.available }} available · {{ block.totals.sold }} sold
                    </div>
                </div>
            </div>

            <div v-if="filteredUnits(block).length === 0" class="card p-6 text-center text-sm text-slate-400">
                No units match the current filter.
            </div>

            <div v-else class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6 xl:grid-cols-8 gap-2">
                <Link v-for="u in filteredUnits(block)" :key="u.id"
                    :href="`/inventory/units/${u.id}`"
                    :class="['group relative rounded-md p-3 ring-1 transition hover:scale-[1.02] hover:shadow-md cursor-pointer',
                              tileClass(u.status)]">
                    <div class="font-mono text-xs font-semibold">{{ u.code }}</div>
                    <div class="text-[10px] uppercase tracking-wide opacity-80 mt-0.5">{{ u.category }}</div>
                    <div v-if="u.size" class="text-[10px] opacity-70">{{ u.size }}</div>
                    <div v-if="u.outstanding_minor !== null && u.outstanding_minor > 0" class="mt-1 text-[10px] font-medium">
                        <Money :minor="u.outstanding_minor" currency="PKR" />
                    </div>
                    <div v-else-if="u.status === 'available'" class="mt-1 text-[10px] font-medium">
                        <Money :minor="u.base_price_minor" currency="PKR" />
                    </div>

                    <!-- Tooltip -->
                    <div class="absolute z-20 hidden group-hover:block left-1/2 -translate-x-1/2 top-full mt-1 w-56 bg-slate-900 text-white text-xs rounded-md shadow-lg p-2 pointer-events-none">
                        <div class="font-mono font-semibold">{{ u.code }}</div>
                        <div class="text-slate-300">{{ u.status_label }} · {{ u.category }}</div>
                        <template v-if="u.client_name">
                            <div class="mt-1 border-t border-slate-700 pt-1">
                                <div class="font-medium">{{ u.client_name }}</div>
                                <div class="text-slate-300 font-mono">{{ u.booking_code }}</div>
                                <div v-if="u.outstanding_minor !== null" class="mt-0.5">
                                    Outstanding: <Money :minor="u.outstanding_minor" currency="PKR" />
                                </div>
                            </div>
                        </template>
                        <template v-else>
                            <div class="mt-1 text-slate-300">
                                Price: <Money :minor="u.base_price_minor" currency="PKR" />
                            </div>
                        </template>
                    </div>
                </Link>
            </div>
        </div>

        <!-- Legend -->
        <div class="mt-8 card p-4 text-xs text-slate-500">
            <div class="flex flex-wrap items-center gap-4">
                <span class="font-medium text-slate-700">Legend:</span>
                <span v-for="opt in filterOpts" :key="opt.key" class="flex items-center gap-1.5">
                    <span class="inline-block w-3 h-3 rounded-sm ring-1" :class="`${opt.activeBg} ${opt.activeRing}`"></span>
                    {{ opt.label }}
                </span>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Money from '@/Components/Money.vue';

const props = defineProps({
    project: { type: Object, required: true },
    blocks: { type: Array, required: true },
    totals: { type: Object, required: true },
});

const filterOpts = [
    { key: 'available', label: 'Available', dot: 'bg-emerald-500', activeRing: 'ring-emerald-300', activeBg: 'bg-emerald-50', activeText: 'text-emerald-800' },
    { key: 'sold', label: 'Sold', dot: 'bg-blue-500', activeRing: 'ring-blue-300', activeBg: 'bg-blue-50', activeText: 'text-blue-800' },
    { key: 'blocked', label: 'Blocked', dot: 'bg-amber-500', activeRing: 'ring-amber-300', activeBg: 'bg-amber-50', activeText: 'text-amber-800' },
    { key: 'possession_transferred', label: 'Possession', dot: 'bg-purple-500', activeRing: 'ring-purple-300', activeBg: 'bg-purple-50', activeText: 'text-purple-800' },
    { key: 'cancelled', label: 'Cancelled', dot: 'bg-red-500', activeRing: 'ring-red-300', activeBg: 'bg-red-50', activeText: 'text-red-800' },
];

const statusFilter = ref(new Set());

function toggleFilter(key) {
    if (statusFilter.value.has(key)) statusFilter.value.delete(key);
    else statusFilter.value.add(key);
    statusFilter.value = new Set(statusFilter.value); // trigger reactivity
}

function filteredUnits(block) {
    if (statusFilter.value.size === 0) return block.units;
    return block.units.filter(u => statusFilter.value.has(u.status));
}

function tileClass(status) {
    return {
        available: 'bg-emerald-50 ring-emerald-200 text-emerald-900 hover:bg-emerald-100',
        sold: 'bg-blue-50 ring-blue-200 text-blue-900 hover:bg-blue-100',
        blocked: 'bg-amber-50 ring-amber-200 text-amber-900 hover:bg-amber-100',
        possession_transferred: 'bg-purple-50 ring-purple-200 text-purple-900 hover:bg-purple-100',
        cancelled: 'bg-red-50 ring-red-200 text-red-900 hover:bg-red-100',
    }[status] || 'bg-slate-50 ring-slate-200 text-slate-700';
}
</script>
