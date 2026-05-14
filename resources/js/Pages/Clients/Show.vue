<template>
    <AppLayout :title="client.full_name">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-sm text-slate-500 font-mono">{{ client.code }}</div>
                <h1 class="text-2xl font-semibold text-slate-900">{{ client.full_name }}</h1>
                <div class="mt-1 text-sm text-slate-600 flex items-center gap-2">
                    <KycBadge :status="client.kyc_status" />
                    <span v-if="client.kyc_verified_at" class="text-xs text-slate-500">verified {{ client.kyc_verified_at }}</span>
                </div>
            </div>
            <div v-if="can.edit" class="flex gap-2">
                <Link :href="`/clients/${client.id}/edit`" class="btn-secondary">Edit</Link>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-4">
            <!-- Contact -->
            <div class="card p-5">
                <h2 class="text-sm font-semibold text-slate-900">Contact</h2>
                <dl class="mt-3 text-sm space-y-2">
                    <div><dt class="text-slate-500 text-xs">Primary phone</dt><dd>{{ client.primary_phone }}</dd></div>
                    <div v-if="client.alt_phone"><dt class="text-slate-500 text-xs">Alt phone</dt><dd>{{ client.alt_phone }}</dd></div>
                    <div v-if="client.email"><dt class="text-slate-500 text-xs">Email</dt><dd>{{ client.email }}</dd></div>
                </dl>
            </div>

            <!-- Identity -->
            <div class="card p-5">
                <h2 class="text-sm font-semibold text-slate-900">Identity</h2>
                <dl class="mt-3 text-sm space-y-2">
                    <div v-if="client.father_or_husband_name"><dt class="text-slate-500 text-xs">Father / Husband</dt><dd>{{ client.father_or_husband_name }}</dd></div>
                    <div v-if="client.date_of_birth"><dt class="text-slate-500 text-xs">Date of birth</dt><dd>{{ client.date_of_birth }}</dd></div>
                    <div v-if="client.nationality"><dt class="text-slate-500 text-xs">Nationality</dt><dd>{{ client.nationality }}</dd></div>
                    <div v-if="client.country_of_residence"><dt class="text-slate-500 text-xs">Country of residence</dt><dd>{{ client.country_of_residence }}</dd></div>
                    <div v-if="client.cnic_masked">
                        <dt class="text-slate-500 text-xs">CNIC</dt>
                        <dd class="font-mono">{{ client.cnic_masked }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Address -->
            <div class="card p-5">
                <h2 class="text-sm font-semibold text-slate-900">Address</h2>
                <dl class="mt-3 text-sm space-y-2">
                    <div v-if="client.address_line1"><dt class="text-slate-500 text-xs">Line 1</dt><dd>{{ client.address_line1 }}</dd></div>
                    <div v-if="client.address_line2"><dt class="text-slate-500 text-xs">Line 2</dt><dd>{{ client.address_line2 }}</dd></div>
                    <div v-if="client.city || client.country">
                        <dt class="text-slate-500 text-xs">City / Country</dt>
                        <dd>{{ [client.city, client.country].filter(Boolean).join(', ') }}</dd>
                    </div>
                    <div v-if="!client.address_line1 && !client.address_line2" class="text-slate-400">No address on file.</div>
                </dl>
            </div>
        </div>

        <!-- Nominees -->
        <div class="mt-6 card">
            <div class="flex items-center justify-between p-5 border-b border-slate-200">
                <h2 class="text-base font-semibold text-slate-900">Nominees</h2>
                <button v-if="can.edit" @click="openNomineeForm()" class="btn-secondary text-sm">+ Add nominee</button>
            </div>
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Name</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Relationship</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Phone</th>
                        <th v-if="can.edit" class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="n in client.nominees" :key="n.id">
                        <td class="px-4 py-2 text-sm">{{ n.full_name }}</td>
                        <td class="px-4 py-2 text-sm text-slate-600">{{ n.relationship }}</td>
                        <td class="px-4 py-2 text-sm text-slate-600">{{ n.phone || '—' }}</td>
                        <td v-if="can.edit" class="px-4 py-2 text-right">
                            <button @click="removeNominee(n)" class="text-sm text-red-600 hover:text-red-800">Remove</button>
                        </td>
                    </tr>
                    <tr v-if="client.nominees.length === 0">
                        <td :colspan="can.edit ? 4 : 3" class="px-4 py-6 text-center text-sm text-slate-500">No nominees on file.</td>
                    </tr>
                </tbody>
            </table>

            <!-- Inline nominee form -->
            <form v-if="showNomineeForm && can.edit" @submit.prevent="submitNominee" class="border-t border-slate-200 p-5 bg-slate-50 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <input v-model="nomineeForm.full_name" type="text" placeholder="Full name" class="input" required />
                <input v-model="nomineeForm.relationship" type="text" placeholder="Relationship" class="input" required />
                <input v-model="nomineeForm.cnic" type="text" placeholder="CNIC (optional)" class="input" />
                <input v-model="nomineeForm.phone" type="text" placeholder="Phone (optional)" class="input" />
                <div class="flex gap-2">
                    <button type="submit" class="btn-primary text-sm" :disabled="nomineeForm.processing">Add</button>
                    <button type="button" @click="showNomineeForm = false" class="btn-secondary text-sm">Cancel</button>
                </div>
            </form>
        </div>

        <!-- Bookings -->
        <div class="mt-6 card">
            <div class="p-5 border-b border-slate-200">
                <h2 class="text-base font-semibold text-slate-900">Bookings</h2>
            </div>
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Code</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Unit</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Booked</th>
                        <th class="px-4 py-2 text-right text-xs font-medium uppercase tracking-wide text-slate-500">Total</th>
                        <th class="px-4 py-2 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="b in client.bookings" :key="b.id">
                        <td class="px-4 py-2 text-sm font-mono text-slate-600">{{ b.code }}</td>
                        <td class="px-4 py-2 text-sm">{{ b.unit_code }} {{ b.unit_name ? '— ' + b.unit_name : '' }}</td>
                        <td class="px-4 py-2 text-sm text-slate-600">{{ b.booking_date }}</td>
                        <td class="px-4 py-2 text-sm text-right"><Money :minor="b.total_price_minor" :currency="b.currency" /></td>
                        <td class="px-4 py-2 text-sm text-slate-600">{{ b.status }}</td>
                    </tr>
                    <tr v-if="client.bookings.length === 0">
                        <td colspan="5" class="px-4 py-6 text-center text-sm text-slate-500">No bookings yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="client.notes" class="mt-6 card p-5">
            <h2 class="text-sm font-semibold text-slate-900">Notes</h2>
            <p class="mt-2 text-sm text-slate-700 whitespace-pre-line">{{ client.notes }}</p>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import KycBadge from '@/Components/KycBadge.vue';
import Money from '@/Components/Money.vue';

const props = defineProps({
    client: { type: Object, required: true },
    can: { type: Object, required: true },
});

const showNomineeForm = ref(false);

const nomineeForm = useForm({
    full_name: '',
    relationship: '',
    cnic: '',
    phone: '',
});

function openNomineeForm() {
    nomineeForm.reset();
    showNomineeForm.value = true;
}

function submitNominee() {
    nomineeForm.post(`/clients/${props.client.id}/nominees`, {
        preserveScroll: true,
        onSuccess: () => { showNomineeForm.value = false; },
    });
}

function removeNominee(n) {
    if (!confirm(`Remove nominee ${n.full_name}?`)) return;
    router.delete(`/clients/${props.client.id}/nominees/${n.id}`, { preserveScroll: true });
}
</script>
