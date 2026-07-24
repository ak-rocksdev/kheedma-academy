<script setup>
import ToastCard from './ToastCard.vue';
import { useToast } from './use-toast';

const { toasts, dismiss } = useToast();
</script>

<template>
    <Teleport to="body">
        <div class="pointer-events-none fixed right-4 top-4 z-[70] flex w-[calc(100vw-2rem)] max-w-sm flex-col items-end gap-2">
            <ToastCard v-for="t in toasts" :key="t.id" :toast="t" @dismiss="dismiss(t.id)" />
        </div>
    </Teleport>
</template>
