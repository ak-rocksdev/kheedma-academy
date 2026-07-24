import { ref } from 'vue';

const toasts = ref([]);
let nextId = 1;

function push(type, message, { duration = 5000 } = {}) {
    toasts.value.push({ id: nextId++, type, message, duration });
}

/** Shared toast queue; <ToastViewport /> in App.vue renders it. */
export function useToast() {
    return {
        toasts,
        dismiss: (id) => (toasts.value = toasts.value.filter((t) => t.id !== id)),
        success: (message, opts) => push('success', message, opts),
        error: (message, opts) => push('error', message, opts),
        warning: (message, opts) => push('warning', message, opts),
        info: (message, opts) => push('info', message, opts),
    };
}
