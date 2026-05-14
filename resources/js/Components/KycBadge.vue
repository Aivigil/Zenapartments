<template>
    <span :class="['badge', classes]">{{ label }}</span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, required: true },
});

const palette = {
    pending:  'bg-amber-50 text-amber-700 ring-amber-600/20',
    verified: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    rejected: 'bg-red-50 text-red-700 ring-red-600/20',
};

const labels = { pending: 'KYC pending', verified: 'KYC verified', rejected: 'KYC rejected' };

const classes = computed(() => palette[props.status] || 'bg-slate-100 text-slate-700 ring-slate-600/20');
const label = computed(() => labels[props.status] || props.status);
</script>
