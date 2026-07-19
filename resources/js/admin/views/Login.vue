<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import PinInput from '@/components/PinInput.vue';

const router = useRouter();
const auth = useAuthStore();

const email = ref('');
const password = ref('');
const error = ref('');
const loading = ref(false);

// Pagi-pagi langsung bisa mengetik: kursor menunggu di kolom email.
const emailInput = ref(null);
onMounted(() => emailInput.value?.$el?.focus());

async function submit() {
    error.value = '';
    loading.value = true;
    try {
        await auth.login({ email: email.value, password: password.value });
        router.push({ name: 'dashboard' });
    } catch (e) {
        error.value = e.errors?.email?.[0] || e.message || 'Gagal masuk. Coba lagi.';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <!-- Chrome-nya alat kerja, bukan situs publik: teal pekat + supergraphic,
         sekali lirik tidak mungkin tertukar dengan login member. -->
    <div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-teal-900 px-6 py-12">
        <div class="supergraphic pointer-events-none absolute inset-0 opacity-[0.07]" aria-hidden="true"></div>
        <div class="glow pointer-events-none absolute left-1/2 top-1/3 h-[28rem] w-[28rem] -translate-x-1/2 -translate-y-1/2 rounded-full bg-teal-600/25 blur-3xl" aria-hidden="true"></div>

        <div class="relative w-full max-w-sm">
            <a href="/" class="rise group flex justify-center" aria-label="Kembali ke beranda Kheedma Academy">
                <img
                    :src="'/images/kheedma-academy-stacked-ondark.png'"
                    width="1180" height="918" alt="Kheedma Academy"
                    class="h-24 w-auto transition-transform duration-300 group-hover:-translate-y-1"
                />
            </a>

            <div class="rise mt-8 rounded-2xl bg-white p-8 shadow-2xl shadow-teal-950/40" style="--rise-delay: 90ms">
                <h1 class="font-display text-2xl font-bold text-teal-900">Panel Admin</h1>
                <p class="mt-1 text-sm text-muted-foreground">Masuk untuk mengelola akademi.</p>

                <form class="mt-6 space-y-4" @submit.prevent="submit">
                    <Transition
                        enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
                        enter-from-class="-translate-y-1 opacity-0"
                        enter-to-class="translate-y-0 opacity-100"
                    >
                        <Alert v-if="error" class="px-3.5 py-2.5">{{ error }}</Alert>
                    </Transition>

                    <div>
                        <label class="text-xs text-muted-foreground">Email</label>
                        <Input ref="emailInput" v-model="email" type="email" autocomplete="username" required placeholder="admin@kheedma.id" class="mt-1.5" />
                    </div>
                    <div>
                        <label class="text-xs text-muted-foreground">PIN (6 digit)</label>
                        <PinInput v-model="password" class="mt-1.5" />
                    </div>
                    <Button type="submit" :disabled="loading" class="w-full transition-transform active:scale-[0.99]">
                        {{ loading ? 'Memproses…' : 'Masuk' }}
                    </Button>
                </form>
            </div>

            <p class="rise mt-6 text-center text-sm text-teal-100/70" style="--rise-delay: 180ms">
                Bukan bagian dari tim?
                <a href="/masuk" class="font-medium text-white underline-offset-4 transition hover:text-orange-300 hover:underline">Masuk sebagai member</a>
            </p>
        </div>

        <p class="rise absolute bottom-6 text-[0.65rem] uppercase tracking-[0.3em] text-teal-100/40" style="--rise-delay: 260ms">
            Khidmat · Amanah · Itqan · Barakah
        </p>
    </div>
</template>

<style scoped>
/* Orkestrasi masuk: logo, kartu, lalu tautan naik berurutan; glow bernapas pelan. */
@media (prefers-reduced-motion: no-preference) {
    .rise {
        opacity: 0;
        transform: translateY(12px);
        animation: rise 0.5s ease-out forwards;
        animation-delay: var(--rise-delay, 0ms);
    }

    @keyframes rise {
        to {
            opacity: 1;
            transform: none;
        }
    }

    .glow {
        animation: breathe 9s ease-in-out infinite alternate;
    }

    @keyframes breathe {
        from {
            opacity: 0.7;
            transform: translate(-50%, -50%) scale(1);
        }

        to {
            opacity: 1;
            transform: translate(-50%, -52%) scale(1.12);
        }
    }
}
</style>
