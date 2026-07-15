<script setup>
import { cn } from '@/lib/utils';

const VARIANTS = {
    destructive: 'border-destructive/30 bg-red-50 text-destructive',
    success: 'border-teal-600/30 bg-teal-50 text-teal-700',
};

const props = defineProps({
    variant: { type: String, default: 'destructive' },
    class: { type: null, default: '' },
});
</script>

<template>
    <div :class="cn('rounded-lg border px-4 py-3 text-sm', VARIANTS[props.variant], props.class)">
        <slot />
    </div>
</template>
