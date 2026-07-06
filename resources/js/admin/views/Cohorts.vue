<script setup>
import { ref, onMounted } from 'vue';
import { cohorts as cohortsApi, users as usersApi, programs as programsApi } from '@/api';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';

const items = ref([]);
const mentors = ref([]);
const programs = ref([]);
const loading = ref(false);
const error = ref('');

const dialogOpen = ref(false);
const editing = ref(null);
const form = ref({ name: '', program_id: '', start_date: '', end_date: '', mentor_id: '' });
const formErrors = ref({});
const saving = ref(false);

const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

const STATUS = {
    upcoming: { label: 'Akan datang', variant: 'warning' },
    active: { label: 'Berjalan', variant: 'success' },
    ended: { label: 'Selesai', variant: 'secondary' },
};

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const [cRes, mRes, pRes] = await Promise.all([cohortsApi.list(), usersApi.list('?role=mentor'), programsApi.list()]);
        items.value = cRes.data;
        mentors.value = mRes.data;
        programs.value = pRes.data;
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
    form.value = { name: '', program_id: '', start_date: '', end_date: '', mentor_id: '' };
    formErrors.value = {};
    dialogOpen.value = true;
}

function openEdit(cohort) {
    editing.value = cohort;
    form.value = {
        name: cohort.name,
        program_id: cohort.program?.id ?? '',
        start_date: cohort.start_date ?? '',
        end_date: cohort.end_date ?? '',
        mentor_id: cohort.mentor?.id ?? '',
    };
    formErrors.value = {};
    dialogOpen.value = true;
}

async function save() {
    saving.value = true;
    formErrors.value = {};
    try {
        const payload = {
            name: form.value.name,
            program_id: form.value.program_id || null,
            start_date: form.value.start_date || null,
            end_date: form.value.end_date || null,
            mentor_id: form.value.mentor_id || null,
        };
        if (editing.value) {
            await cohortsApi.update(editing.value.id, payload);
        } else {
            await cohortsApi.create(payload);
        }
        dialogOpen.value = false;
        await load();
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        formErrors.value = e.errors ?? {};
        if (!Object.keys(formErrors.value).length) error.value = e.message;
    } finally {
        saving.value = false;
    }
}

async function remove(cohort) {
    error.value = '';
    try {
        await cohortsApi.remove(cohort.id);
        await load();
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        error.value = e.message ?? 'Gagal menghapus angkatan.';
    }
}

function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

<template>
    <div class="mx-auto max-w-5xl">
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Angkatan</p>
                <h1 class="mt-2 text-2xl font-bold text-foreground">Daftar Angkatan</h1>
            </div>
            <Button variant="accent" size="sm" @click="openCreate">Tambah Angkatan</Button>
        </div>

        <div v-if="error" class="mt-4 rounded-lg border border-destructive/30 bg-red-50 px-4 py-3 text-sm text-destructive">
            {{ error }}
        </div>

        <div class="mt-5 overflow-hidden rounded-xl border border-border bg-card">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <th class="px-4 py-3 font-semibold">Nama</th>
                        <th class="px-4 py-3 font-semibold">Program</th>
                        <th class="px-4 py-3 font-semibold">Periode</th>
                        <th class="px-4 py-3 font-semibold">Mentor</th>
                        <th class="px-4 py-3 font-semibold">Peserta</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading"><td colspan="7" class="px-4 py-10 text-center text-muted-foreground">Memuat…</td></tr>
                    <tr v-else-if="!items.length"><td colspan="7" class="px-4 py-10 text-center text-muted-foreground">Belum ada angkatan.</td></tr>
                    <tr v-for="cohort in items" :key="cohort.id" class="border-b border-border last:border-0">
                        <td class="px-4 py-3 font-medium text-foreground">{{ cohort.name }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ cohort.program?.name ?? '—' }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ fmtDate(cohort.start_date) }} – {{ fmtDate(cohort.end_date) }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ cohort.mentor?.name ?? '—' }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ cohort.enrollments_count }}</td>
                        <td class="px-4 py-3">
                            <Badge :variant="STATUS[cohort.status]?.variant ?? 'secondary'">
                                {{ STATUS[cohort.status]?.label ?? cohort.status }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <Button variant="ghost" size="sm" @click="openEdit(cohort)">Ubah</Button>
                            <Button variant="ghost" size="sm" @click="remove(cohort)">Hapus</Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Dialog v-model:open="dialogOpen" :title="editing ? 'Ubah Angkatan' : 'Tambah Angkatan'">
            <form class="space-y-3" @submit.prevent="save">
                <div>
                    <Input v-model="form.name" placeholder="Nama angkatan" />
                    <p v-if="formErrors.name" class="mt-1 text-xs text-destructive">{{ formErrors.name[0] }}</p>
                </div>
                <div class="flex gap-3">
                    <div class="flex-1">
                        <label class="text-xs text-muted-foreground">Mulai</label>
                        <Input v-model="form.start_date" type="date" />
                    </div>
                    <div class="flex-1">
                        <label class="text-xs text-muted-foreground">Selesai</label>
                        <Input v-model="form.end_date" type="date" />
                    </div>
                </div>
                <p v-if="formErrors.end_date" class="text-xs text-destructive">{{ formErrors.end_date[0] }}</p>
                <div>
                    <label class="text-xs text-muted-foreground">Program</label>
                    <select v-model="form.program_id" :class="selectClass">
                        <option value="">Pilih program…</option>
                        <option v-for="program in programs" :key="program.id" :value="program.id">{{ program.name }}</option>
                    </select>
                    <p v-if="formErrors.program_id" class="mt-1 text-xs text-destructive">{{ formErrors.program_id[0] }}</p>
                </div>
                <div>
                    <select v-model="form.mentor_id" :class="selectClass">
                        <option value="">Tanpa mentor</option>
                        <option v-for="mentor in mentors" :key="mentor.id" :value="mentor.id">{{ mentor.name }}</option>
                    </select>
                    <p v-if="formErrors.mentor_id" class="mt-1 text-xs text-destructive">{{ formErrors.mentor_id[0] }}</p>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <Button type="button" variant="outline" size="sm" @click="dialogOpen = false">Batal</Button>
                    <Button type="submit" size="sm" :disabled="saving">{{ saving ? 'Menyimpan…' : 'Simpan' }}</Button>
                </div>
            </form>
        </Dialog>
    </div>
</template>
