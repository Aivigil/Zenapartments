<template>
    <!-- Trigger button (top bar) -->
    <button @click="open = true"
        class="hidden md:inline-flex items-center gap-2 text-sm text-slate-500 bg-white hover:bg-slate-50 ring-1 ring-slate-200 rounded-md px-3 py-1.5 w-72">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14z"/></svg>
        <span class="flex-1 text-left">Search…</span>
        <kbd class="text-[10px] bg-slate-100 ring-1 ring-slate-200 rounded px-1.5 py-0.5">⌘K</kbd>
    </button>
    <button @click="open = true" class="md:hidden p-2 text-slate-500 hover:text-slate-700" aria-label="Search">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14z"/></svg>
    </button>

    <!-- Modal -->
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 overflow-y-auto" @keydown.esc="close">
            <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm" @click="close"></div>
            <div class="relative max-w-2xl mx-auto mt-24 px-4">
                <div class="bg-white rounded-lg shadow-2xl ring-1 ring-slate-200 overflow-hidden">
                    <!-- Input -->
                    <div class="flex items-center gap-3 px-4 py-3 border-b border-slate-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M11 18a7 7 0 1 1 0-14 7 7 0 0 1 0 14z"/></svg>
                        <input ref="inputEl" v-model="q" @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="commit"
                               placeholder="Search clients, bookings, units, payments…"
                               class="flex-1 outline-none text-base placeholder:text-slate-400" />
                        <span v-if="loading" class="text-xs text-slate-400">searching…</span>
                        <button @click="close" class="text-xs text-slate-400 hover:text-slate-700">ESC</button>
                    </div>

                    <!-- Results -->
                    <div class="max-h-[60vh] overflow-y-auto">
                        <div v-if="q.length < 2" class="px-4 py-8 text-center text-sm text-slate-500">
                            Type 2+ characters. Examples: <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">ZPL-101</code>, <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">Ahmed</code>, <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs">ZA-C-00001</code>
                        </div>
                        <div v-else-if="groups.length === 0 && !loading" class="px-4 py-8 text-center text-sm text-slate-500">
                            No matches for "<span class="font-semibold">{{ q }}</span>"
                        </div>
                        <div v-for="group in groups" :key="group.label" class="border-b border-slate-100 last:border-b-0">
                            <div class="px-4 py-1.5 text-[11px] uppercase tracking-wide text-slate-500 bg-slate-50">{{ group.label }}</div>
                            <Link v-for="(item, idx) in group.items" :key="item.id"
                                :href="item.url" @click="close"
                                :class="['flex items-center gap-3 px-4 py-2.5 hover:bg-brand/5',
                                          flatIdx(group, idx) === cursor ? 'bg-brand/10' : '']">
                                <div class="text-slate-400">
                                    <span aria-hidden>{{ iconFor(group.icon) }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-slate-900 truncate">{{ item.title }}</div>
                                    <div class="text-xs text-slate-500 truncate">{{ item.subtitle }}</div>
                                </div>
                                <span class="text-xs text-slate-300">↵</span>
                            </Link>
                        </div>
                    </div>

                    <div class="px-4 py-2 bg-slate-50 text-[11px] text-slate-500 flex items-center justify-between border-t border-slate-200">
                        <span><kbd class="bg-white ring-1 ring-slate-200 rounded px-1 py-0.5">↑↓</kbd> navigate · <kbd class="bg-white ring-1 ring-slate-200 rounded px-1 py-0.5">↵</kbd> open · <kbd class="bg-white ring-1 ring-slate-200 rounded px-1 py-0.5">esc</kbd> close</span>
                        <span>Global search</span>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { Link, router } from '@inertiajs/vue3';

const open = ref(false);
const q = ref('');
const groups = ref([]);
const loading = ref(false);
const cursor = ref(0);
const inputEl = ref(null);
let debounceTimer = null;

function close() {
    open.value = false;
    q.value = '';
    groups.value = [];
    cursor.value = 0;
}

function flatItems() {
    return groups.value.flatMap(g => g.items);
}

function flatIdx(group, idx) {
    let n = 0;
    for (const g of groups.value) {
        if (g === group) return n + idx;
        n += g.items.length;
    }
    return -1;
}

function move(d) {
    const total = flatItems().length;
    if (total === 0) return;
    cursor.value = (cursor.value + d + total) % total;
}

function commit() {
    const items = flatItems();
    if (items.length === 0) return;
    const item = items[cursor.value];
    if (item) {
        router.visit(item.url);
        close();
    }
}

function iconFor(name) {
    return { users: '👤', document: '📄', building: '🏢', banknotes: '💵' }[name] || '·';
}

watch(q, () => {
    cursor.value = 0;
    if (debounceTimer) clearTimeout(debounceTimer);
    if (q.value.length < 2) {
        groups.value = [];
        loading.value = false;
        return;
    }
    loading.value = true;
    debounceTimer = setTimeout(async () => {
        try {
            const res = await fetch(`/search?q=${encodeURIComponent(q.value)}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await res.json();
            groups.value = data.groups || [];
        } finally {
            loading.value = false;
        }
    }, 180);
});

watch(open, async (val) => {
    if (val) {
        await nextTick();
        inputEl.value?.focus();
    }
});

function onKey(e) {
    const isCmd = e.metaKey || e.ctrlKey;
    if (isCmd && (e.key === 'k' || e.key === 'K')) {
        e.preventDefault();
        open.value = true;
    }
    // '/' opens search (when not focused in another input)
    if (e.key === '/' && document.activeElement?.tagName !== 'INPUT' && document.activeElement?.tagName !== 'TEXTAREA') {
        e.preventDefault();
        open.value = true;
    }
}

onMounted(() => window.addEventListener('keydown', onKey));
onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
</script>
