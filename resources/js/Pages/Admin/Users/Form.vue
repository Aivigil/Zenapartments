<template>
    <AppLayout :title="isEdit ? `Edit ${user.name}` : 'New user'">
        <div class="max-w-2xl">
            <div class="text-sm text-slate-500"><Link href="/admin/users" class="hover:text-brand">Users</Link> / {{ isEdit ? 'Edit' : 'New' }}</div>
            <h1 class="text-2xl font-semibold text-slate-900">{{ isEdit ? `Edit ${user.name}` : 'New user' }}</h1>

            <form @submit.prevent="submit" class="mt-6 card p-6 space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="label">Name</label>
                        <input v-model="form.name" type="text" class="input mt-1" required />
                        <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
                    </div>
                    <div>
                        <label class="label">Status</label>
                        <select v-model="form.status" class="input mt-1">
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="locked">Locked</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Email</label>
                        <input v-model="form.email" type="email" class="input mt-1" required />
                        <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label class="label">Phone</label>
                        <input v-model="form.phone" type="text" class="input mt-1" placeholder="+92 300 1234567" />
                    </div>
                </div>

                <div>
                    <label class="label">
                        Password
                        <span v-if="isEdit" class="text-xs font-normal text-slate-500">(leave blank to keep existing)</span>
                    </label>
                    <input v-model="form.password" type="password" class="input mt-1" :required="!isEdit" minlength="8" />
                    <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
                    <p class="mt-1 text-xs text-slate-500">Minimum 8 characters. The user can rotate it themselves on first login (not yet built — for now, share securely via Keeper).</p>
                </div>

                <div>
                    <label class="label">Roles (one or more)</label>
                    <div class="mt-2 space-y-2">
                        <label v-for="r in lookups.roles" :key="r.value" class="flex items-center gap-2 text-sm">
                            <input type="checkbox" :value="r.value" v-model="form.roles" class="rounded border-slate-300 text-brand focus:ring-brand" />
                            <span class="font-medium">{{ r.label }}</span>
                            <span class="text-xs text-slate-500 font-mono">{{ r.value }}</span>
                        </label>
                    </div>
                    <p v-if="form.errors.roles" class="mt-1 text-sm text-red-600">{{ form.errors.roles }}</p>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4">
                    <Link href="/admin/users" class="btn-secondary">Cancel</Link>
                    <button type="submit" class="btn-primary" :disabled="form.processing">
                        {{ isEdit ? 'Save changes' : 'Create user' }}
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
    user: { type: Object, default: null },
    lookups: { type: Object, required: true },
});

const isEdit = computed(() => !!props.user?.id);

const form = useForm({
    name: props.user?.name ?? '',
    email: props.user?.email ?? '',
    phone: props.user?.phone ?? '',
    password: '',
    status: props.user?.status ?? 'active',
    roles: props.user?.roles ?? [],
});

function submit() {
    if (isEdit.value) {
        form.put(`/admin/users/${props.user.id}`);
    } else {
        form.post('/admin/users');
    }
}
</script>
