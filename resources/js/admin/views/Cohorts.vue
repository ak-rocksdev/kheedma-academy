<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { Eye, Pencil, Trash2 } from 'lucide-vue-next';
import { parseDate } from '@internationalized/date';
import { cohorts as cohortsApi, users as usersApi, programs as programsApi } from '@/api';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';
import { DatePicker } from '@/components/ui/date-picker';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

const items = ref([]);
const mentors = ref([]);
const programs = ref([]);
const loading = ref(false);
const error = ref('');

const dialogOpen = ref(false);
const editing = ref(null);
const form = ref({
    name: '',
    program_id: '',
    start_date: '',
    mentor_id: '',
    registration_opens_at: '',
    registration_closes_at: '',
    required_attendance: '',
});
const formErrors = ref({});
const saving = ref(false);

// The end date is never typed: it derives from the start date + a duration in
// days (1 day = same-day class). "custom" opens a manual day-count input.
const DURATION_OPTIONS = ['1', '2', '3'];
const duration = ref('1');
const customDays = ref(4);

const durationDays = computed(() => {
    if (duration.value === 'custom') {
        return Math.max(1, parseInt(customDays.value, 10) || 1);
    }
    return Number(duration.value);
});

const computedEndDate = computed(() => {
    if (!form.value.start_date) return '';
    return parseDate(form.value.start_date).add({ days: durationDays.value - 1 }).toString();
});

/** ToggleGroup can deselect to empty; duration is mandatory, so ignore that. */
function setDuration(value) {
    if (value) duration.value = value;
}

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
    form.value = {
        name: '',
        program_id: '',
        start_date: '',
        mentor_id: '',
        registration_opens_at: '',
        registration_closes_at: '',
        required_attendance: '',
    };
    duration.value = '1';
    customDays.value = 4;
    formErrors.value = {};
    dialogOpen.value = true;
}

function openEdit(cohort) {
    editing.value = cohort;
    form.value = {
        name: cohort.name,
        program_id: cohort.program?.id ?? '',
        start_date: cohort.start_date ?? '',
        mentor_id: cohort.mentor?.id ?? '',
        registration_opens_at: cohort.registration_opens_at?.slice(0, 10) ?? '',
        registration_closes_at: cohort.registration_closes_at?.slice(0, 10) ?? '',
        required_attendance: cohort.required_attendance ?? '',
    };

    // Recover the duration from the stored date pair.
    let days = 1;
    if (cohort.start_date && cohort.end_date) {
        days = Math.max(1, Math.round((new Date(cohort.end_date) - new Date(cohort.start_date)) / 86400000) + 1);
    }
    duration.value = days <= 3 ? String(days) : 'custom';
    customDays.value = days > 3 ? days : 4;

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
            end_date: computedEndDate.value || null,
            mentor_id: form.value.mentor_id || null,
            registration_opens_at: form.value.registration_opens_at || null,
            registration_closes_at: form.value.registration_closes_at || null,
            required_attendance: form.value.required_attendance === '' ? null : Number(form.value.required_attendance),
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
        error.value = e.message ?? 'Gagal menghapus angkatan.';
    }
}

/** Periode terbaca manusiawi; keduanya kosong = belum dijadwalkan. */
function periodLabel(cohort) {
    if (!cohort.start_date && !cohort.end_date) {
        return null;
    }

    return `${fmtDate(cohort.start_date)} – ${fmtDate(cohort.end_date)}`;
}

function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

<template>
    <div>
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
                        <th class="px-4 py-3 font-semibold">Pendaftaran</th>
                        <th class="px-4 py-3 font-semibold">Mentor</th>
                        <th class="px-4 py-3 font-semibold">Peserta</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading"><td colspan="8" class="px-4 py-10 text-center text-muted-foreground">Memuat…</td></tr>
                    <tr v-else-if="!items.length"><td colspan="8" class="px-4 py-10 text-center text-muted-foreground">Belum ada angkatan.</td></tr>
                    <tr
                        v-for="cohort in items"
                        :key="cohort.id"
                        class="cursor-pointer border-b border-border transition-colors last:border-0 hover:bg-accent/50"
                        @click="goDetail(cohort)"
                    >
                        <td class="px-4 py-3 font-medium text-foreground">{{ cohort.name }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ cohort.program?.name ?? '—' }}</td>
                        <td class="px-4 py-3" :class="periodLabel(cohort) ? 'text-muted-foreground' : 'text-muted-foreground/50 italic'">
                            {{ periodLabel(cohort) ?? 'Belum dijadwalkan' }}
                        </td>
                        <td class="px-4 py-3">
                            <Badge :variant="cohort.registration_open ? 'success' : 'secondary'">
                                {{ cohort.registration_open ? 'Buka' : 'Tutup' }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">{{ cohort.mentor?.name ?? '—' }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ cohort.enrollments_count }}</td>
                        <td class="px-4 py-3">
                            <Badge :variant="STATUS[cohort.status]?.variant ?? 'secondary'">
                                {{ STATUS[cohort.status]?.label ?? cohort.status }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <Button variant="ghost" size="icon" class="h-8 w-8" title="Lihat detail" aria-label="Lihat detail Angkatan" @click.stop="goDetail(cohort)">
                                <Eye class="size-4" />
                            </Button>
                            <Button variant="ghost" size="icon" class="h-8 w-8" title="Ubah" aria-label="Ubah Angkatan" @click.stop="openEdit(cohort)">
                                <Pencil class="size-4" />
                            </Button>
                            <Button variant="ghost" size="icon" class="h-8 w-8 text-destructive hover:text-destructive" title="Hapus" aria-label="Hapus Angkatan" @click.stop="deleteTarget = cohort">
                                <Trash2 class="size-4" />
                            </Button>
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
                <div>
                    <label class="text-xs text-muted-foreground">Mulai Kelas</label>
                    <DatePicker v-model="form.start_date" class="mt-1.5" placeholder="Pilih tanggal" />
                    <p v-if="formErrors.start_date" class="mt-1 text-xs text-destructive">{{ formErrors.start_date[0] }}</p>
                </div>
                <div>
                    <label class="text-xs text-muted-foreground">Durasi kelas</label>
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        class="mt-1.5 w-full"
                        :model-value="duration"
                        @update:model-value="setDuration"
                    >
                        <ToggleGroupItem v-for="option in DURATION_OPTIONS" :key="option" :value="option" class="flex-1">
                            {{ option }} hari
                        </ToggleGroupItem>
                        <ToggleGroupItem value="custom" class="flex-1">Custom</ToggleGroupItem>
                    </ToggleGroup>
                    <div v-if="duration === 'custom'" class="mt-2 flex items-center gap-2">
                        <Input v-model="customDays" type="number" min="1" class="w-24" />
                        <span class="text-xs text-muted-foreground">hari</span>
                    </div>
                    <p v-if="computedEndDate" class="mt-1.5 text-xs text-muted-foreground">Selesai: {{ fmtDate(computedEndDate) }}</p>
                    <p v-if="formErrors.end_date" class="mt-1 text-xs text-destructive">{{ formErrors.end_date[0] }}</p>
                </div>
                <div class="flex gap-3">
                    <div class="min-w-0 flex-1">
                        <label class="text-xs text-muted-foreground">Pendaftaran dibuka</label>
                        <DatePicker v-model="form.registration_opens_at" class="mt-1.5" placeholder="Pilih tanggal" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <label class="text-xs text-muted-foreground">Pendaftaran ditutup</label>
                        <DatePicker v-model="form.registration_closes_at" class="mt-1.5" placeholder="Pilih tanggal" />
                    </div>
                </div>
                <p v-if="formErrors.registration_closes_at" class="text-xs text-destructive">{{ formErrors.registration_closes_at[0] }}</p>
                <div>
                    <label class="text-xs text-muted-foreground">Syarat kehadiran (jumlah sesi, kosongkan = semua sesi)</label>
                    <Input v-model="form.required_attendance" type="number" min="1" max="255" placeholder="Semua sesi" class="mt-1.5" />
                    <p v-if="formErrors.required_attendance" class="mt-1 text-xs text-destructive">{{ formErrors.required_attendance[0] }}</p>
                </div>
                <div>
                    <label class="text-xs text-muted-foreground">Program</label>
                    <select v-model="form.program_id" :class="[selectClass, 'mt-1.5']">
                        <option value="">Pilih program…</option>
                        <option v-for="program in programs" :key="program.id" :value="program.id">{{ program.name }}</option>
                    </select>
                    <p v-if="formErrors.program_id" class="mt-1 text-xs text-destructive">{{ formErrors.program_id[0] }}</p>
                </div>
                <div>
                    <label class="text-xs text-muted-foreground">Mentor</label>
                    <select v-model="form.mentor_id" :class="[selectClass, 'mt-1.5']">
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

        <!-- Konfirmasi hapus Angkatan -->
        <Dialog :open="deleteTarget !== null" title="Hapus Angkatan" @update:open="deleteTarget = null">
            <p class="text-sm text-muted-foreground">
                Hapus "{{ deleteTarget?.name }}" beserta sesi-sesinya? Angkatan yang sudah punya peserta tidak bisa dihapus.
            </p>
            <div class="mt-4 flex justify-end gap-2">
                <Button variant="outline" size="sm" @click="deleteTarget = null">Batal</Button>
                <Button variant="destructive" size="sm" @click="confirmRemove">Hapus Angkatan</Button>
            </div>
        </Dialog>
    </div>
</template>
