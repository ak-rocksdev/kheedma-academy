<script setup>
import { Primitive } from 'reka-ui';
import { buttonVariants } from '.';
import { cn } from '@/lib/utils';

const props = defineProps({
    variant: { type: String, default: 'default' },
    size: { type: String, default: 'default' },
    class: { type: null, default: '' },
    as: { type: String, default: 'button' },
    asChild: { type: Boolean, default: false },
});
</script>

<template>
    <Primitive
        :as="as"
        :as-child="asChild"
        :class="cn(buttonVariants({ variant, size }), props.class)"
    >
        <slot />
    </Primitive>
</template>
