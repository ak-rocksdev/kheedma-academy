<script setup>
import { RouterLink } from 'vue-router';

/** Dashboard-style number tile; becomes a link when `to` is set. */
defineProps({
    value: { type: [Number, String], default: 0 },
    label: { type: String, required: true },
    to: { type: Object, default: null },
    valueClass: { type: String, default: 'text-foreground' },
});
</script>

<template>
    <component
        :is="to ? RouterLink : 'div'"
        :to="to"
        class="block rounded-xl border border-border bg-card p-5"
        :class="to ? 'transition hover:border-primary/40 hover:shadow-sm' : ''"
    >
        <p class="text-3xl font-bold tabular-nums" :class="valueClass">{{ value }}</p>
        <p class="mt-1 text-xs uppercase tracking-wide text-muted-foreground">{{ label }}</p>
    </component>
</template>
