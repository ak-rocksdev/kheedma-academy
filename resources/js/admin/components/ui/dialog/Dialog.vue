<script setup>
import { watch, onUnmounted } from 'vue';

const open = defineModel('open', { type: Boolean, default: false });
defineProps({
    title: { type: String, default: '' },
    /** Wide layout for content that needs room (e.g. media browsing). */
    wide: { type: Boolean, default: false },
});

function onKey(e) {
    if (e.key === 'Escape') open.value = false;
}

watch(open, (isOpen) => {
    if (isOpen) {
        document.addEventListener('keydown', onKey);
    } else {
        document.removeEventListener('keydown', onKey);
    }
});

onUnmounted(() => document.removeEventListener('keydown', onKey));
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" @click="open = false"></div>
            <div
                class="relative z-10 max-h-[calc(100dvh-2rem)] w-full overflow-y-auto rounded-xl border border-border bg-card p-6 shadow-xl"
                :class="wide ? 'max-w-3xl' : 'max-w-md'"
            >
                <h2 v-if="title" class="text-lg font-bold text-foreground">{{ title }}</h2>
                <div class="mt-4">
                    <slot />
                </div>
            </div>
        </div>
    </Teleport>
</template>
