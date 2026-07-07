<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';
import { Button } from '@/components/ui/button';
import { PasswordInput } from '@/components/ui/password-input';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const auth = useAuthStore();

const password = ref('');
const error = ref('');
const loading = ref(false);

/** The login gate answers 422 with this wording when the account was deactivated. */
const deactivated = computed(() => error.value.includes('dinonaktifkan'));

async function submit() {
    if (!password.value || loading.value) {
        return;
    }
    error.value = '';
    loading.value = true;
    try {
        await auth.reauthenticate(password.value);
        password.value = '';
    } catch (e) {
        error.value = e.errors?.email?.[0] || e.errors?.password?.[0] || e.message || 'Gagal masuk. Coba lagi.';
    } finally {
        loading.value = false;
    }
}

/** Escape hatch: drop the dead session entirely and start over at the login page. */
async function switchAccount() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <Teleport to="body">
        <Transition
            appear
            enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
        >
            <div class="fixed inset-0 z-[60] flex items-center justify-center bg-[#05312b]/70 p-4 backdrop-blur-[2px]">
                <Transition
                    appear
                    enter-active-class="transition duration-200 ease-out motion-reduce:transition-none"
                    enter-from-class="scale-95 opacity-0"
                    enter-to-class="scale-100 opacity-100"
                >
                    <div
                        role="alertdialog"
                        aria-modal="true"
                        aria-labelledby="session-expired-title"
                        class="w-full max-w-sm rounded-xl border border-border bg-card p-7 shadow-2xl"
                    >
                        <!-- Brand mark -->
                        <img
                            :src="'/images/kheedma-academy-horizontal.png'"
                            width="1408"
                            height="492"
                            alt="Kheedma Academy"
                            class="mx-auto h-20 w-auto"
                        />

                        <div class="mt-6 text-center">
                            <p class="font-display text-[0.6rem] uppercase tracking-[0.35em] text-orange-600">Sesi</p>
                            <h2 id="session-expired-title" class="mt-2 text-xl font-bold text-foreground">Sesi Anda berakhir</h2>
                            <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
                                Demi keamanan, sesi diakhiri setelah tidak aktif.
                                Pekerjaan di halaman ini tetap tersimpan.
                            </p>
                            <p class="mt-4 text-sm font-medium text-foreground">
                                {{ auth.user?.name }}
                                <span class="font-normal text-muted-foreground">· {{ auth.user?.email }}</span>
                            </p>
                        </div>

                        <div v-if="error" class="mt-4 rounded-lg border border-destructive/30 bg-red-50 px-3.5 py-2.5 text-sm text-destructive">
                            {{ error }}
                        </div>

                        <form v-if="!deactivated" class="mt-4 space-y-3" @submit.prevent="submit">
                            <PasswordInput v-model="password" autofocus required placeholder="Kata sandi" />
                            <Button type="submit" variant="accent" class="w-full" :disabled="loading || !password">
                                {{ loading ? 'Memproses…' : 'Lanjutkan bekerja' }}
                            </Button>
                        </form>

                        <div class="mt-3 text-center">
                            <Button variant="ghost" size="sm" @click="switchAccount">Masuk dengan akun lain</Button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
