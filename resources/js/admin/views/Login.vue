<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { Eye, EyeOff } from 'lucide-vue-next';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const auth = useAuthStore();

const email = ref('');
const password = ref('');
const showPassword = ref(false);
const error = ref('');
const loading = ref(false);

const inputClass =
    'mt-1.5 w-full rounded-lg border border-teal-900/15 bg-white px-3.5 py-2.5 text-sm outline-none focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20';

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
    <div class="flex min-h-screen items-center justify-center px-6">
        <div class="w-full max-w-sm">
            <div class="flex flex-col items-center text-center">
                <img :src="'/images/kheedma-academy-stacked.png'" width="1180" height="918" alt="Kheedma Academy" class="h-24 w-auto" />
                <span class="mt-4 text-[0.6rem] font-semibold uppercase tracking-[0.4em] text-orange-600">Panel Admin</span>
            </div>

            <form class="mt-10 space-y-5" @submit.prevent="submit">
                <div v-if="error" class="rounded-lg border border-red-300 bg-red-50 px-3.5 py-2.5 text-sm text-red-700">
                    {{ error }}
                </div>

                <div>
                    <label class="block text-sm font-medium text-teal-800">Email</label>
                    <input v-model="email" type="email" autocomplete="username" required :class="inputClass" placeholder="admin@kheedma.id" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-teal-800">Kata sandi</label>
                    <div class="relative">
                        <input v-model="password" :type="showPassword ? 'text' : 'password'" autocomplete="current-password" required :class="[inputClass, 'pr-11']" placeholder="••••••••" />
                        <button
                            type="button"
                            :aria-label="showPassword ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
                            class="absolute inset-y-0 right-0 mt-1.5 flex w-11 items-center justify-center text-teal-900/35 transition-colors hover:text-teal-700 focus-visible:outline-none"
                            @click="showPassword = !showPassword"
                        >
                            <component :is="showPassword ? EyeOff : Eye" class="size-4" />
                        </button>
                    </div>
                </div>
                <button
                    type="submit"
                    :disabled="loading"
                    class="w-full rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-60"
                >
                    {{ loading ? 'Memproses…' : 'Masuk' }}
                </button>
            </form>
        </div>
    </div>
</template>
