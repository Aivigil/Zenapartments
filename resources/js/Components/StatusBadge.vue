<template>
    <span :class="['badge', classes]">{{ label }}</span>
</template>

<script setup>
import { computed } from 'vue';
const props = defineProps({
    status: { type: String, required: true },
    label: { type: String, default: null },
});

const palette = {
    available: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    blocked:   'bg-amber-50 text-amber-700 ring-amber-600/20',
    sold:      'bg-sky-50 text-sky-700 ring-sky-600/20',
    possession_transferred: 'bg-violet-50 text-violet-700 ring-violet-600/20',
    cancelled: 'bg-red-50 text-red-700 ring-red-600/20',
    active:    'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
    paused:    'bg-amber-50 text-amber-700 ring-amber-600/20',
    completed: 'bg-slate-100 text-slate-700 ring-slate-600/20',
};
const classes = computed(() => palette[props.status] || 'bg-slate-100 text-slate-700 ring-slate-600/20');
</script>
