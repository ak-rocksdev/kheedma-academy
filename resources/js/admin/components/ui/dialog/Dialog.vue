<script>
// Module-level stack of open dialogs: when dialogs stack (e.g. a
// ConfirmDialog over a form dialog), only the top-most reacts to Escape.
const openStack = [];
</script>

<script setup>
import { watch, onUnmounted } from 'vue';
import { X } from 'lucide-vue-next';

const open = defineModel('open', { type: Boolean, default: false });
defineProps({
    title: { type: String, default: '' },
    /** Wide layout for content that needs room (e.g. media browsing). */
    wide: { type: Boolean, default: false },
});

const stackId = Symbol();

function onKey(e) {
    if (e.key === 'Escape' && openStack[openStack.length - 1] === stackId) {
        open.value = false;
    }
}

function leaveStack() {
    const i = openStack.indexOf(stackId);
    if (i !== -1) {
        openStack.splice(i, 1);
    }
    document.removeEventListener('keydown', onKey);
}

watch(open, (isOpen) => {
    if (isOpen) {
        openStack.push(stackId);
        document.addEventListener('keydown', onKey);
    } else {
        leaveStack();
    }
});

onUnmounted(leaveStack);
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/50" @click="open = false"></div>
            <div
                class="relative z-10 max-h-[calc(100dvh-2rem)] w-full overflow-y-auto rounded-xl border border-border bg-card p-6 shadow-xl"
                :class="wide ? 'max-w-3xl' : 'max-w-md'"
            >
                <button
                    type="button"
                    aria-label="Tutup"
                    class="absolute right-4 top-4 flex size-8 items-center justify-center rounded-full text-muted-foreground transition hover:bg-accent hover:text-foreground"
                    @click="open = false"
                >
                    <X class="size-4" />
                </button>
                <h2 v-if="title" class="pr-10 text-lg font-bold text-foreground">{{ title }}</h2>
                <div class="mt-4">
                    <slot />
                </div>
            </div>
        </div>
    </Teleport>
</template>
