<template>
    <AppLayout title="Admin">
        <div>
            <h1 class="text-2xl font-semibold text-slate-900">Admin</h1>
            <p class="mt-1 text-sm text-slate-500">User accounts, roles, audit log, and system settings.</p>
        </div>

        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            <Link href="/admin/users" class="card p-5 hover:shadow-md transition block">
                <div class="text-xs uppercase tracking-wide text-slate-500">Users</div>
                <div class="mt-1 text-2xl font-semibold text-slate-900">{{ stats.users_active }}</div>
                <div class="text-xs text-slate-500 mt-1">Active staff · {{ stats.users_suspended }} suspended</div>
                <div class="mt-3 text-sm text-brand">Manage users →</div>
            </Link>

            <Link href="/admin/roles" class="card p-5 hover:shadow-md transition block">
                <div class="text-xs uppercase tracking-wide text-slate-500">Roles &amp; permissions</div>
                <div class="mt-1 text-2xl font-semibold text-slate-900">{{ stats.roles }}</div>
                <div class="text-xs text-slate-500 mt-1">{{ stats.permissions }} permissions in catalogue</div>
                <div class="mt-3 text-sm text-brand">View matrix →</div>
            </Link>

            <Link href="/admin/audit" class="card p-5 hover:shadow-md transition block">
                <div class="text-xs uppercase tracking-wide text-slate-500">Audit events</div>
                <div class="mt-1 text-2xl font-semibold text-slate-900">{{ stats.audit_events_today }}</div>
                <div class="text-xs text-slate-500 mt-1">today · {{ stats.audit_events_30d }} in last 30 days</div>
                <div class="mt-3 text-sm text-brand">View log →</div>
            </Link>
        </div>
    </AppLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps({ stats: { type: Object, required: true } });
</script>
