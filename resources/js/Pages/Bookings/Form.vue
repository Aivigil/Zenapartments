<template>
    <AppLayout title="New booking">
        <div class="max-w-3xl">
            <h1 class="text-2xl font-semibold text-slate-900">New booking</h1>
            <p class="mt-1 text-sm text-slate-500">Pick a client and an available unit. The schedule is generated from the plan template.</p>

            <form @submit.prevent="submit" class="mt-6 card p-6 space-y-5">
                <div>
                    <label class="label">Client</label>
                    <select v-model.number="form.client_id" class="input mt-1" required>
                        <option :value="null">— select —</option>
                        <option v-for="c in lookups.clients" :key="c.id" :value="c.id">{{ c.code }} — {{ c.full_name }}</option>
                    </select>
                    <p v-if="form.errors.client_id" class="mt-1 text-sm text-red-600">{{ form.errors.client_id }}</p>
                    <p class="mt-1 text-xs text-slate-500">
                        Not in the list? <Link href="/clients/create" class="text-brand">Create a client first</Link>.
                    </p>
                </div>

                <div>
                    <label class="label">Available unit</label>
                    <select v-model.number="form.unit_id" @change="autofillPrice" class="input mt-1" required>
                        <option :value="null">— select —</option>
                        <option v-for="u in lookups.available_units" :key="u.id" :value="u.id">
                            {{ u.code }} — {{ u.name || u.category_name }} ({{ u.project_name }})
                        </option>
                    </select>
                    <p v-if="form.errors.unit_id" class="mt-1 text-sm text-red-600">{{ form.errors.unit_id }}</p>
                    <p v-if="lookups.available_units.length === 0" class="mt-1 text-xs text-amber-700">No units are currently available.</p>
                </div>

                <div>
                    <label class="label">Plan template</label>
                    <select v-model.number="form.plan_template_id" @change="recalcDownPayment" class="input mt-1" required>
                        <option :value="null">— select —</option>
                        <option v-for="p in lookups.plan_templates" :key="p.id" :value="p.id">
                            {{ p.name }} ({{ (p.down_payment_bps/100).toFixed(0) }}% down, {{ p.installment_count }}× monthly)
                        </option>
                    </select>
                    <p v-if="form.errors.plan_template_id" class="mt-1 text-sm text-red-600">{{ form.errors.plan_template_id }}</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="label">Booking date</label>
                        <input v-model="form.booking_date" type="date" class="input mt-1" required />
                    </div>
                    <div>
                        <label class="label">Total price</label>
                        <input v-model="form.total_price" @input="recalcDownPayment" type="number" step="0.01" class="input mt-1" required />
                    </div>
                    <div>
                        <label class="label">Currency</label>
                        <select v-model="form.currency" class="input mt-1">
                            <option v-for="c in lookups.currencies" :key="c" :value="c">{{ c }}</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="label">Down payment (auto-calculated; override if needed)</label>
                    <input v-model="form.down_payment" type="number" step="0.01" class="input mt-1" />
                    <p class="mt-1 text-xs text-slate-500">
                        Auto = total × plan's down-payment %. The rest splits across installments + milestone charges.
                    </p>
                </div>

                <div>
                    <label class="label">Notes</label>
                    <textarea v-model="form.notes" class="input mt-1" rows="3"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4">
                    <Link href="/bookings" class="btn-secondary">Cancel</Link>
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        Create booking + schedule
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    lookups: { type: Object, required: true },
});

const form = useForm({
    client_id: null,
    unit_id: null,
    plan_template_id: null,
    booking_date: new Date().toISOString().slice(0, 10),
    total_price: 0,
    down_payment: null,
    currency: 'PKR',
    notes: '',
});

function autofillPrice() {
    const unit = props.lookups.available_units.find(u => u.id === form.unit_id);
    if (unit) {
        form.total_price = (unit.base_price_minor / 100).toFixed(2);
        form.currency = unit.currency || 'PKR';
        recalcDownPayment();
    }
}

function recalcDownPayment() {
    const plan = props.lookups.plan_templates.find(p => p.id === form.plan_template_id);
    if (plan && form.total_price) {
        form.down_payment = (Number(form.total_price) * (plan.down_payment_bps / 10000)).toFixed(2);
    }
}

function submit() {
    form.post('/bookings');
}
</script>
