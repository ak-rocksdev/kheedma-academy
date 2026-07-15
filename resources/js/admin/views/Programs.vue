<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { Eye, Pencil, Trash2 } from 'lucide-vue-next';
import { programs as programsApi } from '@/api';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';
import ProgramFormDialog from '@/components/ProgramFormDialog.vue';
import { programStatusVariant, programStatusLabel } from '@/lib/status';

const items = ref([]);
const loading = ref(false);
const error = ref('');

const dialogOpen = ref(false);
const editing = ref(null);

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const res = await programsApi.list();
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
    dialogOpen.value = true;
}

function openEdit(program) {
    editing.value = program;
    dialogOpen.value = true;
}

const router = useRouter();

/** Baris = pintu utama ke halaman kelola program. */
function goDetail(program) {
    router.push({ name: 'program-detail', params: { id: program.id } });
}

/** Program baru langsung dibuka detailnya: tambah angkatan tinggal satu klik. */
function onSaved(program) {
    if (editing.value === null) {
        router.push({ name: 'program-detail', params: { id: program.id } });
        return;
    }
    load();
}

const deleteTarget = ref(null);

async function confirmRemove() {
    const program = deleteTarget.value;
    error.value = '';
    try {
        await programsApi.remove(program.id);
        deleteTarget.value = null;
        await load();
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        deleteTarget.value = null;
        error.value = e.message ?? 'Gagal menghapus program.';
    }
}
</script>

<template>
    <div>
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Program</p>
                <h1 class="mt-2 text-2xl font-bold text-foreground">Katalog Program</h1>
            </div>
            <Button variant="accent" size="sm" @click="openCreate">Tambah Program</Button>
        </div>

        <Alert v-if="error" class="mt-4">{{ error }}</Alert>

        <div class="mt-5 overflow-hidden rounded-xl border border-border bg-card">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <th class="px-4 py-3 font-semibold">Nama</th>
                        <th class="px-4 py-3 font-semibold">Slug</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold">Pendaftaran</th>
                        <th class="px-4 py-3 font-semibold">Angkatan</th>
                        <th class="px-4 py-3 font-semibold">Pendaftar</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading"><td colspan="7" class="px-4 py-10 text-center text-muted-foreground">Memuat…</td></tr>
                    <tr v-else-if="!items.length"><td colspan="7" class="px-4 py-10 text-center text-muted-foreground">Belum ada program.</td></tr>
                    <tr
                        v-for="program in items"
                        :key="program.id"
                        class="cursor-pointer border-b border-border last:border-0 transition-colors hover:bg-accent/50"
                        @click="goDetail(program)"
                    >
                        <td class="px-4 py-3 font-medium text-foreground">
                            <div class="flex items-center gap-2">
                                <img v-if="program.thumbnail_url" :src="program.thumbnail_url" alt="" class="h-8 w-14 rounded object-cover" />
                                {{ program.name }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground"><code class="text-xs">/program/{{ program.slug }}</code></td>
                        <td class="px-4 py-3">
                            <Badge :variant="programStatusVariant(program.status)">
                                {{ programStatusLabel(program.status) }}
                            </Badge>
                            <Badge variant="secondary" class="ml-1">
                                {{ program.type === 'affiliate_community' ? 'Affiliate L' + program.level : 'Umum' }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3">
                            <Badge :variant="program.is_open ? 'success' : 'secondary'">
                                {{ program.is_open ? 'Buka' : 'Tutup' }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">{{ program.cohorts_count }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ program.applications_count }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <Button variant="ghost" size="icon" class="h-8 w-8" title="Lihat detail" aria-label="Lihat detail program" @click.stop="goDetail(program)">
                                <Eye class="size-4" />
                            </Button>
                            <Button variant="ghost" size="icon" class="h-8 w-8" title="Ubah" aria-label="Ubah program" @click.stop="openEdit(program)">
                                <Pencil class="size-4" />
                            </Button>
                            <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive hover:text-destructive" title="Hapus" aria-label="Hapus program" @click.stop="deleteTarget = program">
                                <Trash2 class="size-4" />
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <ProgramFormDialog v-model:open="dialogOpen" :program="editing" @saved="onSaved" @thumbnail-changed="load" />

        <!-- Konfirmasi hapus program -->
        <Dialog :open="deleteTarget !== null" title="Hapus Program" @update:open="deleteTarget = null">
            <p class="text-sm text-muted-foreground">
                Hapus "{{ deleteTarget?.name }}" dari katalog? Program dengan angkatan atau pendaftar tidak bisa dihapus.
            </p>
            <div class="mt-4 flex justify-end gap-2">
                <Button variant="outline" size="sm" @click="deleteTarget = null">Batal</Button>
                <Button variant="destructive" size="sm" @click="confirmRemove">Hapus Program</Button>
            </div>
        </Dialog>
    </div>
</template>
