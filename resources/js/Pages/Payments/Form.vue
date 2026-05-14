<template>
    <AppLayout title="Record payment">
        <div class="max-w-2xl">
            <h1 class="text-2xl font-semibold text-slate-900">Record payment</h1>
            <p class="mt-1 text-sm text-slate-500">FIFO allocates against the oldest open schedule items on the booking.</p>

            <form @submit.prevent="submit" class="mt-6 card p-6 space-y-5">
                <div v-if="preselect" class="rounded-md bg-slate-50 ring-1 ring-slate-200 p-3 text-sm">
                    Pre-selected booking <span class="font-mono">{{ preselect.code }}</span> — {{ preselect.client.full_name }}
                    · Outstanding: <Money :minor="preselect.outstanding_minor" :currency="preselect.currency" />
                </div>

                <div>
                    <label class="label">Booking</label>
                    <select v-model.number="form.booking_id" class="input mt-1" required>
                        <option :value="null">— select —</option>
                        <option v-for="b in lookups.bookings" :key="b.id" :value="b.id">
                            {{ b.code }} — {{ b.client_name }} ({{ b.currency }})
                        </option>
                    </select>
                    <p v-if="form.errors.booking_id" class="mt-1 text-sm text-red-600">{{ form.errors.booking_id }}</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="label">Received on</label>
                        <input v-model="form.received_at" type="date" class="input mt-1" required />
                    </div>
                    <div>
                        <label class="label">Channel</label>
                        <select v-model="form.channel" class="input mt-1" required>
                            <option v-for="c in lookups.channels" :key="c.value" :value="c.value">{{ c.label }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Currency</label>
                        <select v-model="form.currency" class="input mt-1">
                            <option v-for="c in lookups.currencies" :key="c" :value="c">{{ c }}</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Amount</label>
                        <input v-model="form.amount" type="number" step="0.01" class="input mt-1" required />
                    </div>
                    <div v-if="form.currency !== 'PKR'">
                        <label class="label">FX rate (1 {{ form.currency }} = ? PKR)</label>
                        <input v-model="form.fx_rate" type="number" step="0.0001" class="input mt-1" />
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Bank account (optional)</label>
                        <input v-model="form.bank_account" type="text" class="input mt-1" placeholder="e.g. HBL ZR-Operating" />
                    </div>
                    <div>
                        <label class="label">Bank reference (optional)</label>
                        <input v-model="form.bank_reference" type="text" class="input mt-1" placeholder="Txn ID / slip number" />
                    </div>
                </div>

                <div>
                    <label class="label">Notes</label>
                    <textarea v-model="form.notes" class="input mt-1" rows="2"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4">
                    <Link href="/payments" class="btn-secondary">Cancel</Link>
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        Record + allocate
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Money from '@/Components/Money.vue';

const props = defineProps({
    preselect: { type: Object, default: null },
    lookups: { type: Object, required: true },
});

const form = useForm({
    booking_id: props.preselect?.id ?? null,
    received_at: new Date().toISOString().slice(0, 10),
    channel: 'bank_transfer',
    amount: '',
    currency: props.preselect?.currency ?? 'PKR',
    fx_rate: null,
    bank_account: '',
    bank_reference: '',
    notes: '',
});

function submit() {
    form.post('/payments');
}
</script>
