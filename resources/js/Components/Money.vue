<template>
    <span>{{ formatted }}</span>
</template>

<script setup>
import { computed } from 'vue';
const props = defineProps({
    minor: { type: [Number, String], required: true },
    currency: { type: String, default: 'PKR' },
});
const formatted = computed(() => {
    const major = Number(props.minor || 0) / 100;
    const prefix = props.currency === 'PKR' ? 'Rs. ' : `${props.currency} `;
    return prefix + major.toLocaleString('en-PK', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
});
</script>
