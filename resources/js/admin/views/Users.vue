<script setup>
import { ref, onMounted } from 'vue';
import { Pencil, Power } from 'lucide-vue-next';
import { users as usersApi } from '@/api';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';
import { PasswordInput } from '@/components/ui/password-input';

const items = ref([]);
const loading = ref(false);
const error = ref('');

const dialogOpen = ref(false);
const editing = ref(null); // null = create mode
const form = ref({ name: '', email: '', phone: '', role: 'mentor', password: '' });
const formErrors = ref({});
const generatedPassword = ref('');
const saving = ref(false);


async function load() {
    loading.value = true;
    error.value = '';
    try {
        const res = await usersApi.list();
        items.value = res.data;
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        error.value = e.message ?? 'Gagal memuat data.';
    } finally {
        loading.value = false;
    }
}

onMounted(load);

function openCreate() {
    editing.value = null;
    form.value = { name: '', email: '', phone: '', role: 'mentor', password: '' };
    formErrors.value = {};
    generatedPassword.value = '';
    dialogOpen.value = true;
}

function openEdit(user) {
    editing.value = user;
    form.value = { name: user.name, email: user.email, phone: user.phone ?? '', role: user.role, password: '' };
    formErrors.value = {};
    generatedPassword.value = '';
    dialogOpen.value = true;
}

async function save() {
    saving.value = true;
    formErrors.value = {};
    try {
        const payload = { ...form.value };
        if (!payload.password) delete payload.password;
        if (editing.value) {
            await usersApi.update(editing.value.id, payload);
            dialogOpen.value = false;
        } else {
            const res = await usersApi.create(payload);
            if (res.generated_password) {
                generatedPassword.value = res.generated_password;
            } else {
                dialogOpen.value = false;
            }
        }
        await load();
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        formErrors.value = e.errors ?? {};
        if (!Object.keys(formErrors.value).length) error.value = e.message;
    } finally {
        saving.value = false;
    }
}

async function toggleActive(user) {
    error.value = '';
    try {
        await usersApi.update(user.id, { is_active: !user.is_active });
        await load();
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        error.value = e.message ?? 'Gagal mengubah status.';
    }
}
</script>

<template>
    <div>
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Tim</p>
                <h1 class="mt-2 text-2xl font-bold text-foreground">Akun Tim</h1>
            </div>
            <Button variant="accent" size="sm" @click="openCreate">Tambah Akun</Button>
        </div>

        <Alert v-if="error" class="mt-4">{{ error }}</Alert>

        <!-- ≥md: tabel. Mobile: kartu — kolom peran/status/aksi terpotong di layar sempit. -->
        <div class="mt-5 overflow-hidden rounded-xl border border-border bg-card">
            <ul class="divide-y divide-border md:hidden">
                <li v-if="loading" class="px-4 py-10 text-center text-sm text-muted-foreground">Memuat…</li>
                <li v-else-if="!items.length" class="px-4 py-10 text-center text-sm text-muted-foreground">Belum ada akun.</li>
                <li
                    v-for="user in items"
                    :key="`card-${user.id}`"
                    class="cursor-pointer px-4 py-3.5 transition-colors active:bg-accent/50"
                    @click="openEdit(user)"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-foreground">{{ user.name }}</p>
                            <p class="truncate text-xs text-muted-foreground">{{ user.email }}</p>
                            <p v-if="user.phone" class="truncate text-xs text-muted-foreground">{{ user.phone }}</p>
                        </div>
                        <div class="flex shrink-0 items-center">
                            <Button variant="ghost" size="icon" class="h-9 w-9" title="Ubah" aria-label="Ubah akun" @click.stop="openEdit(user)">
                                <Pencil class="size-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-9 w-9"
                                :class="user.is_active ? 'text-destructive hover:text-destructive' : 'text-teal-700 hover:text-teal-700'"
                                :title="user.is_active ? 'Nonaktifkan' : 'Aktifkan'"
                                :aria-label="user.is_active ? 'Nonaktifkan akun' : 'Aktifkan akun'"
                                @click.stop="toggleActive(user)"
                            >
                                <Power class="size-4" />
                            </Button>
                        </div>
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                        <Badge variant="secondary">{{ user.role }}</Badge>
                        <Badge :variant="user.is_active ? 'success' : 'destructive'">{{ user.is_active ? 'Aktif' : 'Nonaktif' }}</Badge>
                    </div>
                </li>
            </ul>

            <table class="hidden w-full text-sm md:table">
                <thead>
                    <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <th class="px-4 py-3 font-semibold">Nama</th>
                        <th class="px-4 py-3 font-semibold">Kontak</th>
                        <th class="px-4 py-3 font-semibold">Peran</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading"><td colspan="5" class="px-4 py-10 text-center text-muted-foreground">Memuat…</td></tr>
                    <tr v-else-if="!items.length"><td colspan="5" class="px-4 py-10 text-center text-muted-foreground">Belum ada akun.</td></tr>
                    <tr
                        v-for="user in items"
                        :key="user.id"
                        class="cursor-pointer border-b border-border last:border-0 transition-colors hover:bg-accent/50"
                        @click="openEdit(user)"
                    >
                        <td class="px-4 py-3 font-medium text-foreground">{{ user.name }}</td>
                        <td class="px-4 py-3 text-muted-foreground">
                            <div>{{ user.email }}</div>
                            <div class="text-xs">{{ user.phone ?? '—' }}</div>
                        </td>
                        <td class="px-4 py-3"><Badge variant="secondary">{{ user.role }}</Badge></td>
                        <td class="px-4 py-3">
                            <Badge :variant="user.is_active ? 'success' : 'destructive'">
                                {{ user.is_active ? 'Aktif' : 'Nonaktif' }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Button variant="ghost" size="icon" class="h-8 w-8" title="Ubah" aria-label="Ubah akun" @click.stop="openEdit(user)">
                                <Pencil class="size-4" />
                            </Button>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="h-8 w-8"
                                :class="user.is_active ? 'text-destructive hover:text-destructive' : 'text-teal-700 hover:text-teal-700'"
                                :title="user.is_active ? 'Nonaktifkan' : 'Aktifkan'"
                                :aria-label="user.is_active ? 'Nonaktifkan akun' : 'Aktifkan akun'"
                                @click.stop="toggleActive(user)"
                            >
                                <Power class="size-4" />
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Dialog v-model:open="dialogOpen" :title="editing ? 'Ubah Akun' : 'Tambah Akun'">
            <div v-if="generatedPassword" class="space-y-3">
                <p class="text-sm text-foreground">Akun dibuat. Catat kata sandi ini, hanya ditampilkan sekali:</p>
                <code class="block rounded-md border border-border bg-background px-3 py-2 text-sm">{{ generatedPassword }}</code>
                <div class="flex justify-end">
                    <Button size="sm" @click="dialogOpen = false">Selesai</Button>
                </div>
            </div>
            <form v-else class="space-y-3" @submit.prevent="save">
                <div>
                    <Input v-model="form.name" placeholder="Nama" />
                    <p v-if="formErrors.name" class="mt-1 text-xs text-destructive">{{ formErrors.name[0] }}</p>
                </div>
                <div>
                    <Input v-model="form.email" placeholder="Email" />
                    <p v-if="formErrors.email" class="mt-1 text-xs text-destructive">{{ formErrors.email[0] }}</p>
                </div>
                <Input v-model="form.phone" placeholder="No. HP (opsional)" />
                <NativeSelect v-model="form.role">
                    <option value="mentor">Mentor</option>
                    <option value="admin">Admin</option>
                </NativeSelect>
                <div>
                    <PasswordInput v-model="form.password" autocomplete="new-password" :placeholder="editing ? 'Kata sandi baru (opsional)' : 'Kata sandi (kosongkan untuk generate)'" />
                    <p v-if="formErrors.password" class="mt-1 text-xs text-destructive">{{ formErrors.password[0] }}</p>
                    <p v-if="formErrors.role" class="mt-1 text-xs text-destructive">{{ formErrors.role[0] }}</p>
                    <p v-if="formErrors.is_active" class="mt-1 text-xs text-destructive">{{ formErrors.is_active[0] }}</p>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <Button type="button" variant="outline" size="sm" @click="dialogOpen = false">Batal</Button>
                    <Button type="submit" size="sm" :disabled="saving">{{ saving ? 'Menyimpan…' : 'Simpan' }}</Button>
                </div>
            </form>
        </Dialog>
    </div>
</template>
