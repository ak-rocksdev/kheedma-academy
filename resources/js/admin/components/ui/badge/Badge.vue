<script setup>
import { badgeVariants } from '.';
import { cn } from '@/lib/utils';

const props = defineProps({
    variant: { type: String, default: 'default' },
    class: { type: null, default: '' },
});
</script>

<template>
    <span :class="cn(badgeVariants({ variant }), props.class)">
        <slot />
    </span>
</template>
