<script setup>
// One toast card, the Vue port of the member-area house toast
// (resources/views/components/toast.blade.php). Slide/timer CSS lives in
// resources/css/app.css (.kh-toast*), shared by both bundles. The timer
// bar's animationend starts the exit; the exit animation's end removes the
// card, with a timeout fallback for prefers-reduced-motion where the exit
// animation never runs.
import { ref } from 'vue';
import { AlertTriangle, Check, Info, X } from 'lucide-vue-next';

const props = defineProps({
    /** {id, type: 'success'|'error'|'warning'|'info', message, duration} */
    toast: { type: Object, required: true },
});
const emit = defineEmits(['dismiss']);

const leaving = ref(false);
let leaveFallback;

function startLeave() {
    if (leaving.value) return;
    leaving.value = true;
    leaveFallback = setTimeout(() => emit('dismiss'), 600);
}

function onAnimationEnd(e) {
    if (e.animationName === 'kh-toast-timer') startLeave();
    if (e.animationName === 'kh-toast-out') {
        clearTimeout(leaveFallback);
        emit('dismiss');
    }
}

const disc = {
    success: 'bg-teal-100 text-teal-700',
    error: 'bg-red-100 text-red-600',
    warning: 'bg-orange-100 text-orange-600',
    info: 'bg-sand-100 text-teal-700',
}[props.toast.type];

const bar = {
    success: 'bg-teal-600',
    error: 'bg-red-500',
    warning: 'bg-orange-500',
    info: 'bg-teal-600/50',
}[props.toast.type];

const icon = { success: Check, error: X, warning: AlertTriangle, info: Info }[props.toast.type];
</script>

<template>
    <div
        :role="toast.type === 'error' ? 'alert' : 'status'"
        class="kh-toast pointer-events-auto w-full max-w-sm overflow-hidden rounded-2xl bg-white shadow-lg ring-1 ring-teal-900/10"
        :data-leaving="leaving || undefined"
        :style="{ '--kh-toast-duration': `${toast.duration}ms` }"
        @animationend="onAnimationEnd"
    >
        <div class="flex items-center gap-3 px-4 py-3">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full" :class="disc">
                <component :is="icon" class="size-5" />
            </span>
            <p class="min-w-0 flex-1 text-sm font-medium leading-snug text-teal-900">{{ toast.message }}</p>
            <button
                type="button"
                aria-label="Tutup"
                class="flex size-7 shrink-0 items-center justify-center rounded-full text-teal-800/40 transition hover:bg-sand-100 hover:text-teal-900"
                @click="startLeave"
            >
                <X class="size-3.5" />
            </button>
        </div>
        <div class="h-1 w-full bg-teal-900/5">
            <div class="kh-toast-timer h-full" :class="bar"></div>
        </div>
    </div>
</template>
