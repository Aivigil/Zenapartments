<template>
    <div class="min-h-full flex">
        <!-- Sidebar -->
        <aside class="hidden lg:flex lg:w-64 lg:flex-col lg:fixed lg:inset-y-0 bg-brand text-white">
            <div class="flex h-16 shrink-0 items-center px-6 border-b border-brand-700">
                <div class="text-lg font-semibold tracking-tight">Zen Retreats</div>
            </div>
            <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto">
                <template v-for="section in navSections" :key="section.label">
                    <div class="px-3 pt-3 pb-1 text-[10px] font-semibold uppercase tracking-widest text-brand-100/70">{{ section.label }}</div>
                    <Link v-for="item in section.items" :key="item.name" :href="item.href"
                        :class="[
                            'group flex items-center px-3 py-2 text-sm font-medium rounded-md',
                            item.current ? 'bg-brand-700 text-white' : 'text-brand-100 hover:bg-brand-700 hover:text-white',
                        ]">
                        <component :is="item.icon" class="mr-3 h-5 w-5 shrink-0" aria-hidden="true" />
                        {{ item.name }}
                    </Link>
                </template>
            </nav>
            <div class="border-t border-brand-700 p-4 text-xs text-brand-100">
                {{ $page.props.app.env }} · v0.2.0
            </div>
        </aside>

        <!-- Main -->
        <div class="lg:pl-64 flex-1 flex flex-col">
            <!-- Top bar -->
            <header class="bg-white border-b border-slate-200 sticky top-0 z-10">
                <div class="flex h-16 items-center justify-between px-6 gap-4">
                    <div class="text-sm text-slate-500 truncate flex-1">
                        <slot name="breadcrumb">{{ title }}</slot>
                    </div>
                    <GlobalSearch />
                    <div class="flex items-center gap-x-3">
                        <span class="hidden sm:inline text-sm text-slate-700">{{ user?.name }}</span>
                        <span class="hidden md:inline badge bg-slate-100 text-slate-700 ring-slate-200">{{ primaryRole }}</span>
                        <Link href="/logout" method="post" as="button" class="text-sm text-slate-500 hover:text-slate-800">
                            Sign out
                        </Link>
                    </div>
                </div>
            </header>

            <!-- Flash -->
            <div v-if="flash?.success" class="mx-6 mt-4 rounded-md bg-emerald-50 ring-1 ring-emerald-200 p-3 text-sm text-emerald-800">
                {{ flash.success }}
            </div>
            <div v-if="flash?.error" class="mx-6 mt-4 rounded-md bg-red-50 ring-1 ring-red-200 p-3 text-sm text-red-800">
                {{ flash.error }}
            </div>

            <main class="flex-1 p-6">
                <slot />
            </main>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { HomeIcon, BuildingOffice2Icon, UsersIcon, DocumentTextIcon,
    BanknotesIcon, ArrowsRightLeftIcon, BellAlertIcon, ChartBarIcon, Cog6ToothIcon,
    Squares2X2Icon, ClockIcon, KeyIcon } from '@heroicons/vue/24/outline';
import GlobalSearch from '@/Components/GlobalSearch.vue';

defineProps({ title: { type: String, default: '' } });

const page = usePage();
const user = computed(() => page.props.auth?.user);
const flash = computed(() => page.props.flash);
const primaryRole = computed(() => user.value?.roles?.[0] ?? 'user');

const navSections = computed(() => {
    const current = page.url;
    return [
        {
            label: 'Workspace',
            items: [
                { name: 'Dashboard',  href: '/dashboard',                 icon: HomeIcon,             current: current === '/' || current.startsWith('/dashboard') },
                { name: 'Inventory',  href: '/inventory/projects',        icon: BuildingOffice2Icon,  current: current.startsWith('/inventory') },
                { name: 'Clients',    href: '/clients',                   icon: UsersIcon,            current: current.startsWith('/clients') },
                { name: 'Bookings',   href: '/bookings',                  icon: DocumentTextIcon,     current: current.startsWith('/bookings') },
                { name: 'Payments',   href: '/payments',                  icon: BanknotesIcon,        current: current.startsWith('/payments') },
            ],
        },
        {
            label: 'Operations',
            items: [
                { name: 'Reconcile',   href: '/reconciliation',         icon: ArrowsRightLeftIcon,  current: current.startsWith('/reconciliation') },
                { name: 'Reminders',   href: '/notifications',          icon: BellAlertIcon,        current: current.startsWith('/notifications') },
                { name: 'Possession',  href: '/reports/possession',     icon: KeyIcon,              current: current.startsWith('/reports/possession') },
            ],
        },
        {
            label: 'Analytics',
            items: [
                { name: 'Reports',     href: '/reports',                icon: ChartBarIcon,         current: current.startsWith('/reports') && !current.startsWith('/reports/possession') && !current.startsWith('/reports/forecast') },
                { name: 'Forecast',    href: '/reports/forecast',       icon: ClockIcon,            current: current.startsWith('/reports/forecast') },
            ],
        },
        {
            label: 'System',
            items: [
                { name: 'Admin',       href: '/admin',                  icon: Cog6ToothIcon,        current: current.startsWith('/admin') },
            ],
        },
    ];
});
</script>
