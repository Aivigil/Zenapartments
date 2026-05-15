<template>
    <AppLayout title="Bookings">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-slate-900">Bookings</h1>
                <p class="mt-1 text-sm text-slate-500">Client → unit → plan, with auto-generated schedule.</p>
            </div>
            <div class="flex items-center gap-2">
                <a :href="`/bookings/export.csv?${exportQs}`" class="text-sm text-slate-600 hover:text-brand bg-white ring-1 ring-slate-300 rounded-md px-3 py-1.5">
                    Export CSV
                </a>
                <Link href="/bookings/create" class="btn-primary">+ New booking</Link>
            </div>
        </div>

        <div class="mt-6 card p-4 grid grid-cols-1 md:grid-cols-3 gap-3">
            <input v-model="filters.q" @keyup.enter="apply" class="input md:col-span-2" placeholder="Search by booking code, client, or unit…" />
            <select v-model="filters.status" @change="apply" class="input">
                <option :value="''">Any status</option>
                <option v-for="s in lookups.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
        </div>

        <!-- Bulk action bar -->
        <div v-if="selectedIds.length > 0"
             class="mt-4 sticky top-16 z-10 card p-3 flex flex-wrap items-center gap-3 bg-brand text-white ring-1 ring-brand-700">
            <span class="text-sm font-medium">{{ selectedIds.length }} selected</span>
            <span class="text-xs text-white/70">|</span>
            <button @click="openRemindersDialog" class="text-sm bg-white/20 hover:bg-white/30 rounded-md px-3 py-1">
                Queue reminders
            </button>
            <button @click="downloadStatementsZip" class="text-sm bg-white/20 hover:bg-white/30 rounded-md px-3 py-1">
                Download statements (.zip)
            </button>
            <button @click="clearSelection" class="ml-auto text-xs text-white/80 hover:text-white">Clear</button>
        </div>

        <div class="mt-4 card overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-3 py-3 w-8">
                            <input type="checkbox" :checked="allOnPageSelected" @change="toggleAllOnPage($event.target.checked)" class="rounded border-slate-300" />
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Code</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Client</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Unit</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Plan</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Date</th>
                        <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Total</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="b in bookings.data" :key="b.id" class="hover:bg-slate-50">
                        <td class="px-3 py-3">
                            <input type="checkbox" :value="b.id" v-model="selectedIds" class="rounded border-slate-300" />
                        </td>
                        <td class="px-4 py-3 text-sm font-mono text-slate-700">{{ b.code }}</td>
                        <td class="px-4 py-3 text-sm">{{ b.client?.full_name }}</td>
                        <td class="px-4 py-3 text-sm font-mono text-slate-600">{{ b.unit?.code }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ b.plan?.name }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ b.booking_date }}</td>
                        <td class="px-4 py-3 text-sm text-right"><Money :minor="b.total_price_minor" :currency="b.currency" /></td>
                        <td class="px-4 py-3"><StatusBadge :status="b.status" :label="b.status" /></td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="`/bookings/${b.id}`" class="text-sm text-brand hover:text-brand-dark">View</Link>
                        </td>
                    </tr>
                    <tr v-if="bookings.data.length === 0">
                        <td colspan="9" class="px-4 py-8 text-center text-sm text-slate-500">
                            No bookings yet. <Link href="/bookings/create" class="text-brand">Create the first one.</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Reminders modal -->
        <div v-if="remindersDialog" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4" @click.self="remindersDialog = false">
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full p-5">
                <h3 class="text-base font-semibold text-slate-900">Queue reminders for {{ selectedIds.length }} bookings</h3>
                <p class="mt-1 text-xs text-slate-500">For each booking we pick the most-overdue (or next-due) item and send the chosen template.</p>
                <div class="mt-4 space-y-3">
                    <div>
                        <label class="text-xs uppercase tracking-wide text-slate-500">Channel</label>
                        <select v-model="reminderForm.channel" class="input mt-1">
                            <option value="whatsapp">WhatsApp</option>
                            <option value="sms">SMS</option>
                            <option value="email">Email</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs uppercase tracking-wide text-slate-500">Template</label>
                        <select v-model="reminderForm.template_code" class="input mt-1">
                            <option value="reminder_t_minus_7">7 days before due</option>
                            <option value="reminder_t_minus_1">1 day before due</option>
                            <option value="reminder_t_plus_3">3 days overdue</option>
                            <option value="reminder_t_plus_14">14 days overdue</option>
                        </select>
                    </div>
                </div>
                <div class="mt-5 flex items-center justify-end gap-2">
                    <button @click="remindersDialog = false" class="text-sm px-3 py-1.5 rounded-md bg-white ring-1 ring-slate-300 text-slate-700">Cancel</button>
                    <button @click="submitReminders" :disabled="busy" class="btn-primary text-sm">{{ busy ? 'Queuing…' : 'Queue reminders' }}</button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { reactive, ref, computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Money from '@/Components/Money.vue';
import StatusBadge from '@/Components/StatusBadge.vue';

const props = defineProps({
    bookings: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    lookups: { type: Object, required: true },
});

const filters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
});

const selectedIds = ref([]);
const remindersDialog = ref(false);
const reminderForm = reactive({ channel: 'whatsapp', template_code: 'reminder_t_plus_3' });
const busy = ref(false);

const exportQs = computed(() => {
    const p = new URLSearchParams();
    if (filters.q) p.set('q', filters.q);
    if (filters.status) p.set('status', filters.status);
    return p.toString();
});

const allOnPageSelected = computed(() => {
    if (props.bookings.data.length === 0) return false;
    return props.bookings.data.every(b => selectedIds.value.includes(b.id));
});

function toggleAllOnPage(checked) {
    if (checked) {
        const ids = new Set(selectedIds.value);
        props.bookings.data.forEach(b => ids.add(b.id));
        selectedIds.value = Array.from(ids);
    } else {
        const pageIds = new Set(props.bookings.data.map(b => b.id));
        selectedIds.value = selectedIds.value.filter(id => !pageIds.has(id));
    }
}

function clearSelection() { selectedIds.value = []; }

function openRemindersDialog() { remindersDialog.value = true; }

function submitReminders() {
    busy.value = true;
    router.post('/bookings/bulk/reminders', {
        booking_ids: selectedIds.value,
        channel: reminderForm.channel,
        template_code: reminderForm.template_code,
    }, {
        preserveScroll: true,
        onFinish: () => { busy.value = false; remindersDialog.value = false; },
    });
}

function downloadStatementsZip() {
    // Build form + submit so the browser handles the file download
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '/bookings/bulk/statements.zip';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    if (csrf) {
        const f = document.createElement('input');
        f.type = 'hidden'; f.name = '_token'; f.value = csrf;
        form.appendChild(f);
    }
    selectedIds.value.forEach(id => {
        const f = document.createElement('input');
        f.type = 'hidden'; f.name = 'booking_ids[]'; f.value = id;
        form.appendChild(f);
    });
    document.body.appendChild(form);
    form.submit();
    form.remove();
}

function apply() {
    router.get('/bookings', filters, { preserveState: true, preserveScroll: true });
}
</script>
