<template>
    <AppLayout title="Users">
        <div class="flex items-center justify-between">
            <div>
                <div class="text-sm text-slate-500"><Link href="/admin" class="hover:text-brand">Admin</Link> / Users</div>
                <h1 class="text-2xl font-semibold text-slate-900">Users</h1>
            </div>
            <Link href="/admin/users/create" class="btn-primary">+ New user</Link>
        </div>

        <div class="mt-6 card p-4 grid grid-cols-1 md:grid-cols-4 gap-3">
            <input v-model="filters.q" @keyup.enter="apply" class="input md:col-span-2" placeholder="Name, email, phone…" />
            <select v-model="filters.role" @change="apply" class="input">
                <option :value="''">Any role</option>
                <option v-for="r in lookups.roles" :key="r.value" :value="r.value">{{ r.label }}</option>
            </select>
            <select v-model="filters.status" @change="apply" class="input">
                <option :value="''">Any status</option>
                <option v-for="s in lookups.statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
        </div>

        <div class="mt-4 card overflow-hidden">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Phone</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Roles</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500">Last login</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="u in users.data" :key="u.id" class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm font-medium">{{ u.name }}</td>
                        <td class="px-4 py-3 text-sm text-slate-700">{{ u.email }}</td>
                        <td class="px-4 py-3 text-sm text-slate-600">{{ u.phone || '—' }}</td>
                        <td class="px-4 py-3 text-sm">
                            <span v-for="r in u.roles" :key="r" class="inline-flex items-center mr-1 mb-1 badge bg-slate-100 text-slate-700 ring-slate-600/20">
                                {{ r.replace('_', ' ') }}
                            </span>
                        </td>
                        <td class="px-4 py-3"><span :class="['badge', statusClass(u.status)]">{{ u.status }}</span></td>
                        <td class="px-4 py-3 text-sm text-slate-500">{{ u.last_login_at || '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            <Link :href="`/admin/users/${u.id}/edit`" class="text-sm text-brand hover:text-brand-dark">Edit</Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>

<script setup>
import { reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    users: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    lookups: { type: Object, required: true },
});

const filters = reactive({
    q: props.filters.q ?? '',
    status: props.filters.status ?? '',
    role: props.filters.role ?? '',
});

function statusClass(s) {
    return {
        active:    'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        suspended: 'bg-amber-50 text-amber-700 ring-amber-600/20',
        locked:    'bg-red-50 text-red-700 ring-red-600/20',
    }[s] || 'bg-slate-100 text-slate-700 ring-slate-600/20';
}

function apply() {
    router.get('/admin/users', filters, { preserveState: true, preserveScroll: true });
}
</script>
