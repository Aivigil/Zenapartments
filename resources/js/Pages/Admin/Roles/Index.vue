<template>
    <AppLayout title="Roles &amp; permissions">
        <div>
            <div class="text-sm text-slate-500"><Link href="/admin" class="hover:text-brand">Admin</Link> / Roles</div>
            <h1 class="text-2xl font-semibold text-slate-900">Roles &amp; permission matrix</h1>
            <p class="mt-1 text-sm text-slate-500">
                Read-only. Permissions are seeded via <code>RolesAndPermissionsSeeder</code> — to change them, edit the seeder and redeploy.
            </p>
        </div>

        <!-- Role summary cards -->
        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div v-for="r in roles" :key="r.name" class="card p-4">
                <div class="text-sm font-semibold text-slate-900">{{ r.label }}</div>
                <div class="mt-1 text-xs text-slate-500 font-mono">{{ r.name }}</div>
                <div class="mt-3 flex items-baseline justify-between text-sm">
                    <span class="text-slate-500">Permissions</span>
                    <span class="font-semibold">{{ r.permission_count }}</span>
                </div>
                <div class="flex items-baseline justify-between text-sm">
                    <span class="text-slate-500">Users</span>
                    <span class="font-semibold">{{ r.user_count }}</span>
                </div>
            </div>
        </div>

        <!-- Full matrix -->
        <div class="mt-8 card overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-slate-500 sticky left-0 bg-slate-50">Permission</th>
                        <th v-for="r in roles" :key="r.name" class="px-3 py-3 text-center text-xs font-medium uppercase tracking-wide text-slate-500 whitespace-nowrap">
                            {{ r.label }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template v-for="(perms, group) in permission_groups" :key="group">
                        <tr class="bg-slate-50/60">
                            <th colspan="100%" class="px-4 py-2 text-left text-xs font-semibold uppercase tracking-wide text-slate-700">{{ group }}</th>
                        </tr>
                        <tr v-for="p in perms" :key="p">
                            <td class="px-4 py-2 text-sm font-mono text-slate-700 sticky left-0 bg-white whitespace-nowrap">{{ p }}</td>
                            <td v-for="r in roles" :key="r.name" class="px-3 py-2 text-center">
                                <span v-if="r.permissions.includes(p)" class="text-emerald-700">✓</span>
                                <span v-else class="text-slate-300">·</span>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({
    roles: { type: Array, required: true },
    permission_groups: { type: Object, required: true },
    all_permissions: { type: Array, required: true },
});
</script>
