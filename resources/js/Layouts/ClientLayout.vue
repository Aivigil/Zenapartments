<template>
    <div class="min-h-screen bg-slate-50 flex flex-col">
        <header class="bg-white border-b border-slate-200">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 py-4 flex items-center justify-between">
                <div>
                    <div class="text-base sm:text-lg font-semibold text-slate-900 tracking-tight">{{ company.name }}</div>
                    <div class="text-xs text-slate-500">Client Portal</div>
                </div>
                <a v-if="company.whatsapp" :href="`https://wa.me/${company.whatsapp.replace(/[^0-9]/g, '')}`" target="_blank"
                    class="hidden sm:inline-flex items-center gap-1 text-xs text-emerald-700 bg-emerald-50 ring-1 ring-emerald-200 px-2.5 py-1 rounded-md hover:bg-emerald-100">
                    <span aria-hidden>📱</span> WhatsApp us
                </a>
            </div>
        </header>

        <main class="flex-1">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 py-6">
                <slot />
            </div>
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 py-4 text-xs text-slate-500 flex flex-wrap items-center justify-between gap-2">
                <div>&copy; {{ year }} {{ company.name }}. All rights reserved.</div>
                <div class="flex flex-wrap gap-3">
                    <a v-if="company.phone" :href="`tel:${company.phone}`" class="hover:text-brand">{{ company.phone }}</a>
                    <a v-if="company.email" :href="`mailto:${company.email}`" class="hover:text-brand">{{ company.email }}</a>
                </div>
            </div>
        </footer>
    </div>
</template>

<script setup>
import { computed } from 'vue';
defineProps({
    company: { type: Object, required: true },
});
const year = computed(() => new Date().getFullYear());
</script>
