<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { ArrowLeft, Check, Pencil, Trash2, UserMinus } from 'lucide-vue-next';
import { cohorts as cohortsApi, enrollments as enrollmentsApi, sessions as sessionsApi, api } from '@/api';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog } from '@/components/ui/dialog';
import { useAuthStore } from '@/stores/auth';

const props = defineProps({ id: { type: [String, Number], required: true } });

const auth = useAuthStore();

const cohort = ref(null);
const sessionList = ref([]);
const roster = ref([]);
const loading = ref(true);
const error = ref('');

// Absensi matriks: klik sel = toggle hadir, tersimpan otomatis per sesi.
const busyCells = ref(new Set());
const isSavingAttendance = computed(() => busyCells.value.size > 0);

// Tambah peserta
const addOpen = ref(false);
const candidates = ref([]);
const addError = ref('');

// Drop dialog
const dropTarget = ref(null);
const dropNote = ref('');
const dropError = ref('');

// Sesi form + konfirmasi hapus (menghapus sesi ikut menghapus absensinya)
const sessionForm = ref({ id: null, title: '', scheduled_at: '' });
const sessionOpen = ref(false);
const sessionError = ref('');
const deleteSessionTarget = ref(null);

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const res = await cohortsApi.detail(props.id);
        cohort.value = res.cohort;
        sessionList.value = res.sessions;
        roster.value = res.roster;
    } catch (e) {
        if (e.sessionExpired) return;
        error.value = e.message ?? 'Gagal memuat data.';
    } finally {
        loading.value = false;
    }
}

function isHadir(row, session) {
    return row.attended_session_ids.includes(session.id);
}

function canToggle(row) {
    return auth.can('attendance.record') && row.latest_status !== 'dropped';
}

/**
 * Toggle hadir untuk satu sel (peserta x sesi). Optimistic: UI berubah dulu,
 * lalu set hadir penuh sesi itu dikirim; gagal = dikembalikan. Respons server
 * adalah fakta final ("pernah diikuti") — tidak ada status turunan apa pun.
 */
async function toggleAttendance(row, session) {
    const key = `${row.enrollment_id}:${session.id}`;
    if (!canToggle(row) || busyCells.value.has(key)) return;

    const wasHadir = isHadir(row, session);
    const apply = (hadir) => {
        if (hadir) {
            row.attended_session_ids.push(session.id);
        } else {
            row.attended_session_ids.splice(row.attended_session_ids.indexOf(session.id), 1);
        }
        row.hadir += hadir ? 1 : -1;
        session.attendances_count += hadir ? 1 : -1;
    };

    apply(!wasHadir);
    busyCells.value = new Set([...busyCells.value, key]);
    error.value = '';

    try {
        const hadirIds = roster.value
            .filter((r) => r.attended_session_ids.includes(session.id))
            .map((r) => r.enrollment_id);
        await sessionsApi.setAttendance(session.id, hadirIds);
    } catch (e) {
        apply(wasHadir); // revert
        if (!e.sessionExpired) error.value = e.message ?? 'Gagal menyimpan absensi.';
    } finally {
        const next = new Set(busyCells.value);
        next.delete(key);
        busyCells.value = next;
    }
}

async function openAdd() {
    addError.value = '';
    addOpen.value = true;
    try {
        // Accepted applications of this cohort's program, not yet enrolled here.
        const res = await api(`/admin/applications?status=accepted&program=${cohort.value.program.id}&per_page=200`);
        const enrolledPersonIds = new Set(roster.value.map((r) => r.person.id));
        candidates.value = res.data.filter((a) => !enrolledPersonIds.has(a.person.id));
    } catch (e) {
        if (!e.sessionExpired) addError.value = e.message ?? 'Gagal memuat pelamar.';
    }
}

async function enroll(application) {
    addError.value = '';
    try {
        await enrollmentsApi.create({ cohort_id: cohort.value.id, application_id: application.id });
        addOpen.value = false;
        await load();
    } catch (e) {
        if (!e.sessionExpired) addError.value = e.errors ? Object.values(e.errors)[0][0] : e.message;
    }
}

function openDrop(row) {
    dropTarget.value = row;
    dropNote.value = '';
    dropError.value = '';
}

async function confirmDrop() {
    dropError.value = '';
    try {
        await enrollmentsApi.drop(dropTarget.value.enrollment_id, dropNote.value);
        dropTarget.value = null;
        await load();
    } catch (e) {
        if (!e.sessionExpired) dropError.value = e.errors?.note?.[0] ?? e.message;
    }
}

async function removeEnrollment(row) {
    error.value = '';
    try {
        await enrollmentsApi.remove(row.enrollment_id);
        await load();
    } catch (e) {
        if (!e.sessionExpired) error.value = e.errors?.enrollment?.[0] ?? e.message;
    }
}

function openSessionForm(session = null) {
    sessionError.value = '';
    sessionForm.value = session
        ? { id: session.id, title: session.title, scheduled_at: session.scheduled_at?.slice(0, 10) ?? '' }
        : { id: null, title: '', scheduled_at: '' };
    sessionOpen.value = true;
}

async function saveSession() {
    sessionError.value = '';
    const payload = { title: sessionForm.value.title, scheduled_at: sessionForm.value.scheduled_at || null };
    try {
        if (sessionForm.value.id) {
            await sessionsApi.update(sessionForm.value.id, payload);
        } else {
            await sessionsApi.create(cohort.value.id, { ...payload, position: sessionList.value.length + 1 });
        }
        sessionOpen.value = false;
        await load();
    } catch (e) {
        if (!e.sessionExpired) sessionError.value = e.errors?.title?.[0] ?? e.message;
    }
}

/** Dari dialog ubah sesi: tutup form, buka konfirmasi hapus untuk sesi itu. */
function requestDeleteFromForm() {
    const session = sessionList.value.find((s) => s.id === sessionForm.value.id);
    sessionOpen.value = false;
    deleteSessionTarget.value = session ?? null;
}

async function confirmRemoveSession() {
    const session = deleteSessionTarget.value;
    error.value = '';
    try {
        await sessionsApi.remove(session.id);
        deleteSessionTarget.value = null;
        await load();
    } catch (e) {
        if (!e.sessionExpired) error.value = e.message ?? 'Gagal menghapus sesi.';
    }
}

function statusVariant(status) {
    return { accepted: 'success', dropped: 'destructive' }[status] ?? 'secondary';
}

function statusLabel(status) {
    return { accepted: 'Aktif', dropped: 'Keluar' }[status] ?? (status ?? 'Belum ada status');
}

function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

function shortDate(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString('id-ID', { day: '2-digit', month: 'short' });
}

onMounted(load);
// Route-view instances are reused when only :id changes (house pattern, see PersonDetail).
watch(() => props.id, () => load());
</script>

<template>
    <div>
        <RouterLink :to="{ name: 'cohorts' }" class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
            <ArrowLeft class="size-4" /> Semua Angkatan
        </RouterLink>

        <div v-if="error" class="mt-4 rounded-lg border border-destructive/30 bg-red-50 px-4 py-3 text-sm text-destructive">{{ error }}</div>
        <div v-if="loading" class="mt-10 text-center text-muted-foreground">Memuat…</div>

        <template v-else-if="cohort">
            <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">{{ cohort.program?.name ?? 'Angkatan' }}</p>
                    <h1 class="mt-2 text-2xl font-bold text-foreground">{{ cohort.name }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ sessionList.length }} kelas · Mentor: {{ cohort.mentor?.name ?? '—' }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button v-if="auth.can('cohorts.manage')" variant="outline" size="sm" @click="openSessionForm()">Tambah Sesi</Button>
                    <Button v-if="auth.can('enrollments.manage')" variant="accent" size="sm" @click="openAdd">Tambah Peserta</Button>
                </div>
            </div>

            <!-- Matriks peserta x sesi: satu baris mengelola peserta DAN absensinya. -->
            <div class="mt-6 overflow-hidden rounded-xl border border-border bg-card">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <th class="px-4 py-3 font-semibold">Peserta</th>
                                <th v-for="(s, i) in sessionList" :key="s.id" class="px-2 py-2 text-center font-semibold">
                                    <button
                                        v-if="auth.can('cohorts.manage')"
                                        type="button"
                                        class="group mx-auto rounded-md px-2 py-1 transition hover:bg-accent"
                                        :title="`${s.title} · klik untuk mengubah`"
                                        @click="openSessionForm(s)"
                                    >
                                        <span class="flex items-center justify-center gap-1">
                                            S{{ i + 1 }}
                                            <Pencil class="size-3 opacity-0 transition group-hover:opacity-60" />
                                        </span>
                                        <span class="block text-[0.6rem] font-normal normal-case text-muted-foreground/80">{{ shortDate(s.scheduled_at) || '—' }}</span>
                                    </button>
                                    <span v-else :title="s.title">
                                        S{{ i + 1 }}
                                        <span class="block text-[0.6rem] font-normal normal-case text-muted-foreground/80">{{ shortDate(s.scheduled_at) || '—' }}</span>
                                    </span>
                                </th>
                                <th class="px-3 py-3 text-center font-semibold">Kehadiran</th>
                                <th class="px-3 py-3 font-semibold">Status</th>
                                <th class="px-3 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!roster.length">
                                <td :colspan="sessionList.length + 4" class="px-4 py-10 text-center text-muted-foreground">Belum ada peserta.</td>
                            </tr>
                            <tr
                                v-for="row in roster"
                                :key="row.enrollment_id"
                                class="border-b border-border last:border-0"
                                :class="row.latest_status === 'dropped' ? 'opacity-50' : ''"
                            >
                                <td class="px-4 py-3">
                                    <RouterLink :to="{ name: 'person', params: { id: row.person.id } }" class="font-medium text-foreground hover:underline">
                                        {{ row.person.name }}
                                    </RouterLink>
                                    <div class="text-xs text-muted-foreground">{{ row.person.phone }}</div>
                                </td>
                                <td
                                    v-for="s in sessionList"
                                    :key="s.id"
                                    class="px-2 py-2 text-center"
                                    :class="canToggle(row) ? 'cursor-pointer hover:bg-accent/60' : ''"
                                    :title="canToggle(row) ? `${row.person.name} · ${s.title}` : ''"
                                    @click="toggleAttendance(row, s)"
                                >
                                    <span
                                        class="mx-auto flex size-5 items-center justify-center rounded-full transition"
                                        :class="isHadir(row, s) ? 'bg-teal-600 text-white' : 'border-2 border-border'"
                                    >
                                        <Check v-if="isHadir(row, s)" class="size-3.5" />
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-center tabular-nums text-muted-foreground">{{ row.hadir }}/{{ sessionList.length }}</td>
                                <td class="px-3 py-3">
                                    <Badge :variant="statusVariant(row.latest_status)">{{ statusLabel(row.latest_status) }}</Badge>
                                </td>
                                <td class="px-3 py-3 text-right whitespace-nowrap">
                                    <Button
                                        v-if="auth.can('enrollments.manage') && row.latest_status !== 'dropped'"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8"
                                        title="Keluarkan (catat alasan)"
                                        aria-label="Keluarkan peserta"
                                        @click="openDrop(row)"
                                    >
                                        <UserMinus class="size-4" />
                                    </Button>
                                    <Button
                                        v-if="auth.can('enrollments.manage') && row.hadir === 0 && (row.latest_status === 'accepted' || !row.latest_status)"
                                        variant="ghost"
                                        size="icon"
                                        class="h-8 w-8 text-destructive hover:text-destructive"
                                        title="Hapus dari Angkatan"
                                        aria-label="Hapus enrollment"
                                        @click="removeEnrollment(row)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-border px-4 py-2.5 text-xs text-muted-foreground">
                    <span v-if="!sessionList.length">Belum ada sesi. Tambahkan sesi untuk mulai mencatat absensi.</span>
                    <span v-else-if="auth.can('attendance.record')">Klik sel sesi untuk menandai hadir. Perubahan tersimpan otomatis.</span>
                    <span v-else>Anda hanya dapat melihat absensi.</span>
                    <span v-if="isSavingAttendance" class="font-medium text-teal-700">Menyimpan…</span>
                </div>
            </div>
        </template>

        <!-- Tambah peserta -->
        <Dialog v-model:open="addOpen" title="Tambah Peserta">
            <div v-if="addError" class="mb-3 rounded-lg border border-destructive/30 bg-red-50 px-3.5 py-2.5 text-sm text-destructive">{{ addError }}</div>
            <p v-if="!candidates.length" class="text-sm text-muted-foreground">Tidak ada pelamar diterima yang belum terdaftar di Angkatan ini.</p>
            <div v-else class="max-h-72 space-y-2 overflow-y-auto">
                <div v-for="app in candidates" :key="app.id" class="flex items-center justify-between gap-3 rounded-lg border border-border px-3 py-2 text-sm">
                    <div>
                        <p class="font-medium text-foreground">{{ app.person.name }}</p>
                        <p class="text-xs text-muted-foreground">{{ app.person.phone }}</p>
                    </div>
                    <Button size="sm" variant="outline" @click="enroll(app)">Masukkan</Button>
                </div>
            </div>
        </Dialog>

        <!-- Drop -->
        <Dialog :open="dropTarget !== null" title="Keluarkan Peserta" @update:open="dropTarget = null">
            <p class="text-sm text-muted-foreground">
                Catat alasan {{ dropTarget?.person.name }} keluar. Riwayatnya tetap tersimpan untuk analisis.
            </p>
            <div v-if="dropError" class="mt-3 rounded-lg border border-destructive/30 bg-red-50 px-3.5 py-2.5 text-sm text-destructive">{{ dropError }}</div>
            <textarea
                v-model="dropNote"
                rows="3"
                placeholder="Alasan keluar (wajib)"
                class="mt-3 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            ></textarea>
            <div class="mt-4 flex justify-end gap-2">
                <Button variant="outline" size="sm" @click="dropTarget = null">Batal</Button>
                <Button variant="destructive" size="sm" :disabled="!dropNote" @click="confirmDrop">Keluarkan</Button>
            </div>
        </Dialog>

        <!-- Konfirmasi hapus sesi (destruktif: absensinya ikut terhapus) -->
        <Dialog :open="deleteSessionTarget !== null" title="Hapus Sesi" @update:open="deleteSessionTarget = null">
            <p class="text-sm text-muted-foreground">
                Menghapus "{{ deleteSessionTarget?.title }}" ikut menghapus catatan absensinya dan menghitung ulang kelulusan peserta. Lanjutkan?
            </p>
            <div class="mt-4 flex justify-end gap-2">
                <Button variant="outline" size="sm" @click="deleteSessionTarget = null">Batal</Button>
                <Button variant="destructive" size="sm" @click="confirmRemoveSession">Hapus Sesi</Button>
            </div>
        </Dialog>

        <!-- Sesi form -->
        <Dialog v-model:open="sessionOpen" :title="sessionForm.id ? 'Ubah Sesi' : 'Tambah Sesi'">
            <form class="space-y-3" @submit.prevent="saveSession">
                <div>
                    <Input v-model="sessionForm.title" placeholder="Judul sesi (mis. Sesi 1: Dasar Affiliate)" />
                    <p v-if="sessionError" class="mt-1 text-xs text-destructive">{{ sessionError }}</p>
                </div>
                <div>
                    <label class="text-xs text-muted-foreground">Tanggal (opsional)</label>
                    <Input v-model="sessionForm.scheduled_at" type="date" class="mt-1" />
                </div>
                <div class="flex items-center justify-between gap-2 pt-2">
                    <Button v-if="sessionForm.id" type="button" variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="requestDeleteFromForm">
                        Hapus Sesi
                    </Button>
                    <div class="ml-auto flex gap-2">
                        <Button type="button" variant="outline" size="sm" @click="sessionOpen = false">Batal</Button>
                        <Button type="submit" size="sm">Simpan</Button>
                    </div>
                </div>
            </form>
        </Dialog>
    </div>
</template>
