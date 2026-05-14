<template>
    <AppLayout :title="isEdit ? 'Edit client' : 'New client'">
        <div class="max-w-4xl">
            <h1 class="text-2xl font-semibold text-slate-900">
                {{ isEdit ? `Edit ${client.full_name}` : 'New client' }}
            </h1>

            <form @submit.prevent="submit" class="mt-6 space-y-6">
                <!-- Identity -->
                <div class="card p-6">
                    <h2 class="text-sm font-semibold text-slate-900 mb-4">Identity</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="label">Full name</label>
                            <input v-model="form.full_name" type="text" class="input mt-1" required />
                            <p v-if="form.errors.full_name" class="mt-1 text-sm text-red-600">{{ form.errors.full_name }}</p>
                        </div>
                        <div>
                            <label class="label">Father / Husband name</label>
                            <input v-model="form.father_or_husband_name" type="text" class="input mt-1" />
                        </div>
                        <div>
                            <label class="label">Date of birth</label>
                            <input v-model="form.date_of_birth" type="date" class="input mt-1" />
                        </div>
                        <div>
                            <label class="label">Nationality</label>
                            <input v-model="form.nationality" type="text" class="input mt-1" placeholder="Pakistani" />
                        </div>
                        <div>
                            <label class="label">
                                CNIC
                                <span v-if="isEdit" class="text-xs font-normal text-slate-500">(leave blank to keep existing)</span>
                            </label>
                            <input v-model="form.cnic" type="text" class="input mt-1 font-mono" placeholder="12345-1234567-1" />
                            <p v-if="form.errors.cnic" class="mt-1 text-sm text-red-600">{{ form.errors.cnic }}</p>
                        </div>
                        <div>
                            <label class="label">
                                Passport
                                <span v-if="isEdit" class="text-xs font-normal text-slate-500">(leave blank to keep existing)</span>
                            </label>
                            <input v-model="form.passport" type="text" class="input mt-1 font-mono" />
                        </div>
                    </div>
                </div>

                <!-- Contact -->
                <div class="card p-6">
                    <h2 class="text-sm font-semibold text-slate-900 mb-4">Contact</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="label">Primary phone</label>
                            <input v-model="form.primary_phone" type="text" class="input mt-1" placeholder="+92 300 1234567" required />
                            <p v-if="form.errors.primary_phone" class="mt-1 text-sm text-red-600">{{ form.errors.primary_phone }}</p>
                        </div>
                        <div>
                            <label class="label">Alternate phone</label>
                            <input v-model="form.alt_phone" type="text" class="input mt-1" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="label">Email</label>
                            <input v-model="form.email" type="email" class="input mt-1" />
                        </div>
                    </div>
                </div>

                <!-- Address -->
                <div class="card p-6">
                    <h2 class="text-sm font-semibold text-slate-900 mb-4">Address</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="label">Address line 1</label>
                            <input v-model="form.address_line1" type="text" class="input mt-1" />
                        </div>
                        <div class="sm:col-span-2">
                            <label class="label">Address line 2</label>
                            <input v-model="form.address_line2" type="text" class="input mt-1" />
                        </div>
                        <div>
                            <label class="label">City</label>
                            <input v-model="form.city" type="text" class="input mt-1" />
                        </div>
                        <div>
                            <label class="label">Country (address)</label>
                            <select v-model="form.country" class="input mt-1">
                                <option :value="''">— select —</option>
                                <option v-for="c in lookups.countries" :key="c.value" :value="c.value">{{ c.label }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="label">Country of residence</label>
                            <select v-model="form.country_of_residence" class="input mt-1">
                                <option :value="''">— select —</option>
                                <option v-for="c in lookups.countries" :key="c.value" :value="c.value">{{ c.label }}</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- KYC + Notes -->
                <div class="card p-6">
                    <h2 class="text-sm font-semibold text-slate-900 mb-4">KYC & internal notes</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="label">KYC status</label>
                            <select v-model="form.kyc_status" class="input mt-1" required>
                                <option v-for="s in lookups.kyc_statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="label">Internal notes</label>
                        <textarea v-model="form.notes" class="input mt-1" rows="3"></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <Link href="/clients" class="btn-secondary">Cancel</Link>
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        {{ isEdit ? 'Save changes' : 'Create client' }}
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
    client: { type: Object, default: null },
    lookups: { type: Object, required: true },
});

const isEdit = computed(() => !!props.client?.id);

const form = useForm({
    full_name: props.client?.full_name ?? '',
    father_or_husband_name: props.client?.father_or_husband_name ?? '',
    date_of_birth: props.client?.date_of_birth ?? '',
    nationality: props.client?.nationality ?? 'Pakistani',
    country_of_residence: props.client?.country_of_residence ?? 'PK',
    cnic: '',
    passport: '',
    primary_phone: props.client?.primary_phone ?? '',
    alt_phone: props.client?.alt_phone ?? '',
    email: props.client?.email ?? '',
    address_line1: props.client?.address_line1 ?? '',
    address_line2: props.client?.address_line2 ?? '',
    city: props.client?.city ?? '',
    country: props.client?.country ?? 'PK',
    kyc_status: props.client?.kyc_status ?? 'pending',
    notes: props.client?.notes ?? '',
});

function submit() {
    if (isEdit.value) {
        form.put(`/clients/${props.client.id}`);
    } else {
        form.post('/clients');
    }
}
</script>
