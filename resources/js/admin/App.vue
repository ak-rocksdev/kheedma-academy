<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { RouterView } from 'vue-router';
import { CheckCircle2 } from 'lucide-vue-next';
import SessionExpiredDialog from './components/SessionExpiredDialog.vue';
import { useAuthStore } from './stores/auth';

const auth = useAuthStore();

// Proactive expiry detection: when the tab regains focus after being idle,
// probe the session so the renewal dialog appears BEFORE the next click.
function onFocus() {
    auth.probeSession();
}

onMounted(() => {
    window.addEventListener('focus', onFocus);
    document.addEventListener('visibilitychange', onFocus);
});

onUnmounted(() => {
    window.removeEventListener('focus', onFocus);
    document.removeEventListener('visibilitychange', onFocus);
});

// Transient confirmation after a successful renewal.
const showRenewedToast = ref(false);
let toastTimer;

watch(
    () => auth.renewedAt,
    (value) => {
        if (!value) return;
        showRenewedToast.value = true;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => (showRenewedToast.value = false), 4000);
    },
);

onUnmounted(() => clearTimeout(toastTimer));
</script>

<template>
    <div class="min-h-screen bg-sand-50 text-teal-900">
        <RouterView />

        <!-- Global re-login dialog: keeps page state alive when the session dies. -->
        <SessionExpiredDialog v-if="auth.sessionExpired && auth.user" />

        <!-- Konfirmasi singkat setelah sesi diperpanjang. -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
                enter-from-class="translate-y-2 opacity-0"
                enter-to-class="translate-y-0 opacity-100"
                leave-active-class="transition duration-300 ease-in motion-reduce:transition-none"
                leave-to-class="opacity-0"
            >
                <div
                    v-if="showRenewedToast"
                    class="fixed bottom-6 left-1/2 z-[65] flex -translate-x-1/2 items-center gap-2 rounded-full bg-teal-800 px-5 py-2.5 text-sm font-medium text-white shadow-lg"
                    role="status"
                >
                    <CheckCircle2 class="size-4 text-teal-300" />
                    Sesi diperpanjang. Selamat bekerja kembali.
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
