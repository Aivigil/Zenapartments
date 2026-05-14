<template>
    <AppLayout :title="`Reconcile — ${import_.source_filename}`">
        <div class="flex items-start justify-between">
            <div>
                <div class="text-sm text-slate-500">
                    <Link href="/reconciliation" class="hover:text-brand">Reconciliation</Link>
                    <span class="mx-1">/</span>
                    {{ import_.source_filename }}
                </div>
                <h1 class="text-2xl font-semibold text-slate-900">{{ import_.bank_account }}</h1>
                <div class="mt-1 text-sm text-slate-600">
                    {{ import_.period_start }} → {{ import_.period_end }} · {{ import_.total_lines }} lines · {{ import_.matched_lines }} auto-matched
                </div>
            </div>
        </div>

        <div class="mt-4 flex gap-2 text-sm">
            <Link :href="`/reconciliation/${import_.id}?status=pending_or_matched`" :class="filterBtn('pending_or_matched')">Queue</Link>
            <Link :href="`/reconciliation/${import_.id}?status=confirmed`" :class="filterBtn('confirmed')">Confirmed</Link>
            <Link :href="`/reconciliation/${import_.id}?status=ignored`" :class="filterBtn('ignored')">Ignored</Link>
            <Link :href="`/reconciliation/${import_.id}?status=all`" :class="filterBtn('all')">All</Link>
        </div>

        <div class="mt-3 card overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Direction</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Amount</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Description</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Suggestions</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="l in lines.data" :key="l.id" :class="rowClass(l)">
                        <td class="px-4 py-3 text-sm text-slate-700 whitespace-nowrap">{{ l.txn_date }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span :class="['badge', l.direction === 'credit' ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-slate-100 text-slate-700 ring-slate-600/20']">
                                {{ l.direction === 'credit' ? '↓ Credit' : '↑ Debit' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-right"><Money :minor="l.amount_minor" :currency="l.currency" /></td>
                        <td class="px-4 py-3 text-sm text-slate-700 max-w-[300px]">
                            <div class="truncate" :title="l.description">{{ l.description }}</div>
                            <div v-if="l.counterparty || l.reference" class="text-xs text-slate-500 mt-0.5">
                                <span v-if="l.counterparty">{{ l.counterparty }}</span>
                                <span v-if="l.counterparty && l.reference"> · </span>
                                <span v-if="l.reference" class="font-mono">{{ l.reference }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <div v-if="l.status === 'confirmed'" class="text-emerald-700 font-medium">✓ Confirmed</div>
                            <div v-else-if="l.status === 'ignored'" class="text-slate-500">Ignored</div>
                            <div v-else-if="l.suggested_matches.length === 0 && l.direction === 'credit'" class="text-slate-400 italic text-xs">No suggestions</div>
                            <div v-else-if="l.direction === 'debit'" class="text-slate-400 italic text-xs">Outbound — no match needed</div>
                            <ul v-else class="space-y-1">
                                <li v-for="s in l.suggested_matches" :key="s.client_id" class="text-xs">
                                    <span class="font-medium">{{ s.client_name }}</span>
                                    <span class="text-slate-500 font-mono">{{ s.client_code }}</span>
                                    <span class="text-slate-500"> · {{ s.score }} pts</span>
                                </li>
                            </ul>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div v-if="l.status === 'confirmed' || l.status === 'ignored'" class="text-xs text-slate-400">—</div>
                            <div v-else-if="l.direction !== 'credit'" class="text-xs text-slate-400">—</div>
                            <div v-else class="flex justify-end gap-1">
                                <button @click="openConfirm(l)" class="text-sm text-brand hover:text-brand-dark">Confirm</button>
                                <button @click="ignore(l)" class="text-sm text-slate-500 hover:text-slate-800">Ignore</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="lines.data.length === 0">
                        <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">No lines match this filter.</td>
                    </tr>
                </tbody>
            </table>

            <div v-if="lines.total > lines.per_page" class="border-t border-slate-200 px-4 py-3 flex items-center justify-between text-sm text-slate-600">
                <div>{{ lines.from || 0 }}–{{ lines.to || 0 }} of {{ lines.total }}</div>
                <div class="flex gap-2">
                    <Link v-for="link in lines.links" :key="link.label" :href="link.url || ''"
                          :class="['px-3 py-1 rounded-md', link.active ? 'bg-brand text-white' : 'bg-white ring-1 ring-slate-300 text-slate-700', !link.url ? 'opacity-50 pointer-events-none' : '']"
                          v-html="link.label" />
                </div>
            </div>
        </div>

        <!-- Confirm modal -->
        <div v-if="confirming" class="fixed inset-0 bg-slate-900/40 flex items-center justify-center z-50" @click.self="confirming = null">
            <div class="bg-white rounded-lg shadow-xl max-w-lg w-full p-6 m-4">
                <h2 class="text-lg font-semibold text-slate-900">Confirm bank line</h2>
                <p class="mt-1 text-sm text-slate-500">
                    {{ confirming.txn_date }} · <Money :minor="confirming.amount_minor" :currency="confirming.currency" />
                </p>
                <p class="mt-1 text-sm text-slate-600 truncate">{{ confirming.description }}</p>

                <div class="mt-4">
                    <label class="label">Apply this payment against booking</label>
                    <select v-model.number="confirmForm.booking_id" class="input mt-1" required>
                        <option :value="null">— pick a booking —</option>
                        <option v-if="confirming.suggested_matches.length" disabled>— Top matches (from suggestions) —</option>
                        <optgroup v-if="suggestedBookings.length" label="Suggested">
                            <option v-for="b in suggestedBookings" :key="b.id" :value="b.id">
                                {{ b.code }} — {{ b.client_name }}
                            </option>
                        </optgroup>
                    </select>
                    <p class="mt-1 text-xs text-slate-500">
                        Not in the list? Pick "Apply manually" → goes to the regular Payments form which lets you pick any booking.
                    </p>
                </div>

                <div class="mt-5 flex justify-end gap-2">
                    <button @click="confirming = null" class="btn-secondary text-sm">Cancel</button>
                    <Link v-if="confirming && !confirmForm.booking_id" :href="`/payments/create?bank_line=${confirming.id}`" class="btn-secondary text-sm">Apply manually</Link>
                    <button @click="submitConfirm" :disabled="!confirmForm.booking_id || confirmForm.processing" class="btn-primary text-sm">Confirm + post payment</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Money from '@/Components/Money.vue';

const props = defineProps({
    import: { type: Object, required: true },
    lines: { type: Object, required: true },
    filter: { type: String, default: 'pending_or_matched' },
});

// Vue templates can't use `import` directly as a variable name
const import_ = computed(() => props.import);

const confirming = ref(null);
const confirmForm = useForm({
    booking_id: null,
});

// Bookings to suggest in the modal dropdown — fetched lazily per line.
// For MVP, we just show the top-suggested clients' active bookings inline.
const suggestedBookings = ref([]);

function filterBtn(name) {
    return ['px-3 py-1 rounded-md text-sm',
        props.filter === name
            ? 'bg-brand text-white'
            : 'bg-white ring-1 ring-slate-300 text-slate-700 hover:bg-slate-50'
    ];
}

function rowClass(l) {
    if (l.status === 'confirmed') return 'bg-emerald-50/40';
    if (l.status === 'ignored') return 'opacity-60';
    return '';
}

async function openConfirm(line) {
    confirming.value = line;
    confirmForm.reset();
    suggestedBookings.value = [];

    // Fetch active bookings for the top-suggested clients to populate the dropdown
    if (line.suggested_matches.length) {
        const ids = line.suggested_matches.map(s => s.client_id).join(',');
        try {
            const res = await fetch(`/reconciliation/suggested-bookings?client_ids=${ids}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (res.ok) suggestedBookings.value = await res.json();
        } catch (_) { /* ignore */ }
    }
}

function submitConfirm() {
    confirmForm.post(`/reconciliation/lines/${confirming.value.id}/confirm`, {
        preserveScroll: true,
        onSuccess: () => { confirming.value = null; },
    });
}

function ignore(line) {
    if (!confirm('Mark this line as ignored? Won\'t appear in the queue anymore.')) return;
    router.post(`/reconciliation/lines/${line.id}/ignore`, {}, { preserveScroll: true });
}
</script>
