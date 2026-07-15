<script setup>
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { Eye, Pencil, Trash2 } from 'lucide-vue-next';
import { cohorts as cohortsApi } from '@/api';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';
import CohortFormDialog from '@/components/CohortFormDialog.vue';
import { cohortStatusVariant, cohortStatusLabel } from '@/lib/status';
import { cohortPeriodLabel } from '@/lib/format';

const items = ref([]);
const loading = ref(false);
const error = ref('');

const dialogOpen = ref(false);
const editing = ref(null);

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const res = await cohortsApi.list();
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

function openEdit(cohort) {
    editing.value = cohort;
    dialogOpen.value = true;
}

const router = useRouter();
const deleteTarget = ref(null);

/** Baris = pintu utama ke halaman kelola Angkatan. */
function goDetail(cohort) {
    router.push({ name: 'cohort-detail', params: { id: cohort.id } });
}

async function confirmRemove() {
    const cohort = deleteTarget.value;
    error.value = '';
    try {
        await cohortsApi.remove(cohort.id);
        deleteTarget.value = null;
        await load();
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        deleteTarget.value = null;
        error.value = e.message ?? 'Gagal menghapus angkatan / kelas.';
    }
}

</script>

<template>
    <div>
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Angkatan / Kelas</p>
                <h1 class="mt-2 text-2xl font-bold text-foreground">Daftar Angkatan / Kelas</h1>
            </div>
            <Button variant="accent" size="sm" @click="openCreate">Tambah Angkatan / Kelas</Button>
        </div>

        <Alert v-if="error" class="mt-4">{{ error }}</Alert>

        <div class="mt-5 overflow-hidden rounded-xl border border-border bg-card">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <th class="px-4 py-3 font-semibold">Nama</th>
                        <th class="px-4 py-3 font-semibold">Program</th>
                        <th class="px-4 py-3 font-semibold">Periode</th>
                        <th class="px-4 py-3 font-semibold">Pendaftaran</th>
                        <th class="px-4 py-3 font-semibold">Mentor</th>
                        <th class="px-4 py-3 font-semibold">Peserta</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading"><td colspan="8" class="px-4 py-10 text-center text-muted-foreground">Memuat…</td></tr>
                    <tr v-else-if="!items.length"><td colspan="8" class="px-4 py-10 text-center text-muted-foreground">Belum ada angkatan / kelas.</td></tr>
                    <tr
                        v-for="cohort in items"
                        :key="cohort.id"
                        class="cursor-pointer border-b border-border transition-colors last:border-0 hover:bg-accent/50"
                        @click="goDetail(cohort)"
                    >
                        <td class="px-4 py-3 font-medium text-foreground">{{ cohort.name }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ cohort.program?.name ?? '—' }}</td>
                        <td class="px-4 py-3" :class="cohortPeriodLabel(cohort) ? 'text-muted-foreground' : 'text-muted-foreground/50 italic'">
                            {{ cohortPeriodLabel(cohort) ?? 'Belum dijadwalkan' }}
                        </td>
                        <td class="px-4 py-3">
                            <Badge :variant="cohort.registration_open ? 'success' : 'secondary'">
                                {{ cohort.registration_open ? 'Buka' : 'Tutup' }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">{{ cohort.mentor?.name ?? '—' }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ cohort.enrollments_count }}</td>
                        <td class="px-4 py-3">
                            <Badge :variant="cohortStatusVariant(cohort.status)">
                                {{ cohortStatusLabel(cohort.status) }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <Button variant="ghost" size="icon" class="h-8 w-8" title="Lihat detail" aria-label="Lihat detail Angkatan / Kelas" @click.stop="goDetail(cohort)">
                                <Eye class="size-4" />
                            </Button>
                            <Button variant="ghost" size="icon" class="h-8 w-8" title="Ubah" aria-label="Ubah Angkatan / Kelas" @click.stop="openEdit(cohort)">
                                <Pencil class="size-4" />
                            </Button>
                            <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive hover:text-destructive" title="Hapus" aria-label="Hapus Angkatan / Kelas" @click.stop="deleteTarget = cohort">
                                <Trash2 class="size-4" />
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <CohortFormDialog v-model:open="dialogOpen" :cohort="editing" @saved="load" />

        <!-- Konfirmasi hapus Angkatan -->
        <Dialog :open="deleteTarget !== null" title="Hapus Angkatan / Kelas" @update:open="deleteTarget = null">
            <p class="text-sm text-muted-foreground">
                Hapus "{{ deleteTarget?.name }}" beserta sesi-sesinya? Angkatan / Kelas yang sudah punya peserta tidak bisa dihapus.
            </p>
            <div class="mt-4 flex justify-end gap-2">
                <Button variant="outline" size="sm" @click="deleteTarget = null">Batal</Button>
                <Button variant="destructive" size="sm" @click="confirmRemove">Hapus Angkatan / Kelas</Button>
            </div>
        </Dialog>
    </div>
</template>
