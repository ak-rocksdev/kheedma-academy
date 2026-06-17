<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const auth = useAuthStore();

const email = ref('');
const password = ref('');

const inputClass =
    'mt-1.5 w-full rounded-lg border border-teal-900/15 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20';

// Placeholder login — Layer 2 swaps this for a real Sanctum request.
function submit() {
    auth.loginAs(email.value || 'Admin');
    router.push({ name: 'dashboard' });
}
</script>

<template>
    <div class="flex min-h-screen items-center justify-center px-6">
        <div class="w-full max-w-sm">
            <div class="flex flex-col items-center text-center">
                <img :src="'/images/kheedma-academy-stacked.png'" width="1180" height="918" alt="Kheedma Academy" class="h-24 w-auto" />
                <span class="mt-4 text-[0.6rem] font-semibold uppercase tracking-[0.4em] text-orange-600">Panel Admin</span>
            </div>

            <form class="mt-10 space-y-5" @submit.prevent="submit">
                <div>
                    <label class="block text-sm font-medium text-teal-800">Email</label>
                    <input
                        v-model="email"
                        type="email"
                        autocomplete="username"
                        :class="inputClass"
                        placeholder="admin@kheedma.id"
                    />
                </div>
                <div>
                    <label class="block text-sm font-medium text-teal-800">Kata sandi</label>
                    <input
                        v-model="password"
                        type="password"
                        autocomplete="current-password"
                        :class="inputClass"
                        placeholder="••••••••"
                    />
                </div>
                <button
                    type="submit"
                    class="w-full rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-teal-800"
                >
                    Masuk
                </button>
            </form>

            <p class="mt-6 text-center text-xs text-teal-800/50">
                Autentikasi sebenarnya (Sanctum) menyusul di Layer 2.
            </p>
        </div>
    </div>
</template>
