<script setup>
// Promote a participant login to staff (mentor/admin): search, pick, confirm.
// A pure role switch — the PIN, Person link, and enrollments stay untouched.
import { ref, watch } from 'vue';
import { users as usersApi } from '@/api';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { ConfirmDialog } from '@/components/ui/confirm-dialog';
import { Dialog } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';

const open = defineModel('open', { type: Boolean, default: false });
const emit = defineEmits(['promoted']);

const query = ref('');
const results = ref([]);
const selected = ref(null);
const role = ref('mentor');
const error = ref('');
const confirmOpen = ref(false);
const busy = ref(false);

const roleLabel = { mentor: 'Mentor', admin: 'Admin' };

let debounce;
watch(query, () => {
    clearTimeout(debounce);
    debounce = setTimeout(search, 300);
});

watch(open, (isOpen) => {
    if (!isOpen) return;
    query.value = '';
    results.value = [];
    selected.value = null;
    role.value = 'mentor';
    error.value = '';
    search();
});

async function search() {
    try {
        const res = await usersApi.promotable(query.value);
        results.value = res.data;
    } catch (e) {
        if (!e.sessionExpired) error.value = e.message ?? 'Gagal memuat data.';
    }
}

async function promote() {
    busy.value = true;
    error.value = '';
    try {
        const res = await usersApi.promote(selected.value.id, { role: role.value });
        confirmOpen.value = false;
        open.value = false;
        emit('promoted', { user: res.user, roleLabel: roleLabel[role.value] });
    } catch (e) {
        if (e.sessionExpired) return;
        confirmOpen.value = false;
        error.value = e.message ?? 'Gagal mengangkat peserta.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open" title="Angkat dari Peserta">
        <div class="space-y-4">
            <Alert v-if="error">{{ error }}</Alert>

            <div>
                <label class="mb-1 block text-sm font-medium text-foreground" for="promote-search">Cari peserta</label>
                <Input id="promote-search" v-model="query" placeholder="Nama, email, atau nomor HP" autocomplete="off" />
            </div>

            <ul v-if="results.length" class="max-h-56 divide-y divide-border overflow-y-auto rounded-lg border border-border">
                <li v-for="user in results" :key="user.id">
                    <button
                        type="button"
                        class="flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left transition hover:bg-accent"
                        :class="selected?.id === user.id ? 'bg-accent' : ''"
                        @click="selected = user"
                    >
                        <span class="text-sm font-medium text-foreground">{{ user.name }}</span>
                        <span class="text-xs text-muted-foreground">{{ user.email }}</span>
                    </button>
                </li>
            </ul>
            <p v-else class="rounded-lg border border-dashed border-border px-3 py-4 text-center text-sm text-muted-foreground">
                Tidak ada akun peserta yang cocok.
            </p>

            <div v-if="selected" class="space-y-3 rounded-lg bg-accent/50 p-3">
                <p class="text-sm text-foreground">
                    Angkat <strong>{{ selected.name }}</strong> menjadi:
                </p>
                <NativeSelect v-model="role">
                    <option value="mentor">Mentor</option>
                    <option value="admin">Admin</option>
                </NativeSelect>
                <Button class="w-full" @click="confirmOpen = true">Lanjutkan</Button>
            </div>
        </div>
    </Dialog>

    <ConfirmDialog
        v-model:open="confirmOpen"
        title="Angkat jadi staf?"
        :confirm-label="`Ya, angkat jadi ${roleLabel[role]}`"
        :busy="busy"
        @confirm="promote"
    >
        <template v-if="selected">
            {{ selected.name }} akan menjadi {{ roleLabel[role] }} dan tidak lagi masuk sebagai peserta.
            PIN login tidak berubah.
        </template>
    </ConfirmDialog>
</template>
