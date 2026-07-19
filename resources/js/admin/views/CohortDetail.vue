<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { ArrowLeft, CalendarDays, Check, Copy, FileText, Pencil, Plus, Ticket, Trash2, UserMinus } from 'lucide-vue-next';
import { copyText } from '@/lib/clipboard';
import { cohorts as cohortsApi, enrollments as enrollmentsApi, sessions as sessionsApi, api } from '@/api';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog } from '@/components/ui/dialog';
import { Alert } from '@/components/ui/alert';
import { Textarea } from '@/components/ui/textarea';
import { useAuthStore } from '@/stores/auth';
import { fmtDateTime } from '@/lib/format';
import { cohortStatusLabel, cohortStatusVariant } from '@/lib/status';
import CohortFormDialog from '@/components/CohortFormDialog.vue';
import SessionFormDialog from '@/components/SessionFormDialog.vue';

const props = defineProps({ id: { type: [String, Number], required: true } });

const auth = useAuthStore();

const cohort = ref(null);
const sessionList = ref([]);
const roster = ref([]);

// Spec 2026-07-18: cohort = batch, each session = one class. Attendance and
// the info-copy toolset operate on the selected class.
const selectedSessionId = ref(null);
const selectedSession = computed(
    () => sessionList.value.find((s) => s.id === selectedSessionId.value) ?? null
);

/** First upcoming class, else the last one — the most actionable default. */
function defaultSessionId() {
    const now = new Date();
    const upcoming = sessionList.value.find((s) => s.scheduled_at && new Date(s.scheduled_at) >= now);
    return (upcoming ?? sessionList.value[sessionList.value.length - 1])?.id ?? null;
}

// Class dialog state
const sessionFormOpen = ref(false);
const sessionBeingEdited = ref(null);
const deleteTarget = ref(null);
const deleteError = ref('');

function openCreateSession() {
    sessionBeingEdited.value = null;
    sessionFormOpen.value = true;
}

function openEditSession(session) {
    sessionBeingEdited.value = session;
    sessionFormOpen.value = true;
}

async function confirmDeleteSession() {
    deleteError.value = '';
    try {
        await sessionsApi.remove(deleteTarget.value.id);
        deleteTarget.value = null;
        await load();
    } catch (e) {
        if (!e.sessionExpired) deleteError.value = e.message ?? 'Gagal menghapus kelas.';
    }
}

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

// Edit dialog — the same form the list page uses, refreshed on save.
const editOpen = ref(false);

// Ringkasan kehadiran: dropped tidak dihitung sebagai target hadir.
const activeRosterCount = computed(() => roster.value.filter((r) => r.latest_status !== 'dropped').length);
const hadirCount = computed(() =>
    selectedSession.value ? roster.value.filter((r) => isHadir(r, selectedSession.value)).length : 0
);


// Mirrors Cohort::startCountdownLabel(): final week only, display-side.
const countdownLabel = computed(() => {
    const iso = cohort.value?.start_date;
    if (!iso) return null;
    const start = new Date(iso);
    if (start <= new Date()) return null;
    const days = Math.round((new Date(start.toDateString()) - new Date(new Date().toDateString())) / 86400000);
    if (days === 0) return 'Hari ini';
    if (days === 1) return 'Besok';
    return days <= 7 ? `${days} hari lagi` : null;
});

// Paket info siap-tempel (WhatsApp dsb.) — halaman ini memberi, bukan hanya
// mengarsip: jadwal + lokasi/link + materi dalam sekali salin.
const copiedInfo = ref(false);
async function copyClassInfo() {
    const c = cohort.value;
    const s = selectedSession.value;
    const lines = [
        [`${c.program?.name ?? ''} · ${c.name}`.replace(/^ · /, ''), s?.title].filter(Boolean).join(' — '),
    ];
    if (s?.scheduled_at) lines.push(`Jadwal: ${fmtDateTime(s.scheduled_at)} WIB`);
    if (s?.type === 'offline') {
        const place = [s.location_name, s.location_address].filter(Boolean).join(', ');
        if (place) lines.push(`Lokasi: ${place}`);
        if (s.maps_url) lines.push(`Peta: ${s.maps_url}`);
    } else if (s?.meeting_url) {
        lines.push(`Link meeting: ${s.meeting_url}`);
    }
    if (c.materials_url) lines.push(`Materi: ${c.materials_url}`);

    if (await copyText(lines.join('\n'))) {
        copiedInfo.value = true;
        setTimeout(() => (copiedInfo.value = false), 1800);
    }
}


async function load() {
    loading.value = true;
    error.value = '';
    try {
        const res = await cohortsApi.detail(props.id);
        cohort.value = res.cohort;
        sessionList.value = res.sessions;
        roster.value = res.roster;
        if (!sessionList.value.some((s) => s.id === selectedSessionId.value)) {
            selectedSessionId.value = defaultSessionId();
        }
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
        candidates.value = res.data.filter((a) => a.person && !enrolledPersonIds.has(a.person.id));
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

function statusVariant(status) {
    return { accepted: 'success', dropped: 'destructive' }[status] ?? 'secondary';
}

function statusLabel(status) {
    return { accepted: 'Aktif', dropped: 'Keluar', completed: 'Selesai' }[status] ?? (status ?? 'Belum ada status');
}

onMounted(load);
// Route-view instances are reused when only :id changes (house pattern, see PersonDetail).
watch(() => props.id, () => load());
</script>

<template>
    <div>
        <RouterLink :to="{ name: 'cohorts' }" class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
            <ArrowLeft class="size-4" /> Semua Angkatan / Kelas
        </RouterLink>

        <Alert v-if="error" class="mt-4">{{ error }}</Alert>
        <div v-if="loading" class="mt-10 text-center text-muted-foreground">Memuat…</div>

        <template v-else-if="cohort">
            <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">{{ cohort.program?.name ?? 'Angkatan / Kelas' }}</p>
                    <h1 class="mt-2 text-2xl font-bold text-foreground">{{ cohort.name }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Mentor: {{ cohort.mentor?.name ?? '—' }} · {{ roster.length }} peserta
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button v-if="auth.can('cohorts.manage')" variant="outline" size="sm" @click="editOpen = true">
                        <Pencil class="mr-1.5 h-3.5 w-3.5" /> Ubah
                    </Button>
                    <Button v-if="auth.can('enrollments.manage')" variant="accent" size="sm" @click="openAdd">Tambah Peserta</Button>
                </div>
            </div>

            <!-- "Tiket kelas": satu kartu bersekat — jadwal | lokasi/link | pendaftaran.
                 Satu objek logistik dengan tile ikon senada sisi member, bukan
                 tiga kotak yatim dengan tinggi timpang. -->
            <div class="mt-4 divide-y divide-border overflow-hidden rounded-xl border border-border bg-card md:grid md:grid-cols-[1fr_1.35fr_1fr] md:divide-x md:divide-y-0">
                <div class="flex gap-3 px-5 py-4">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-teal-100 via-sand-50 to-orange-200/70 ring-1 ring-inset ring-teal-900/10">
                        <CalendarDays class="size-5 text-teal-700" />
                    </span>
                    <div class="min-w-0 text-sm">
                        <p class="text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-muted-foreground">Jadwal</p>
                        <p class="mt-0.5 font-semibold text-foreground">
                            {{ fmtDateTime(cohort.start_date) }}<span v-if="cohort.start_date" class="font-normal text-muted-foreground"> WIB</span>
                        </p>
                        <div class="mt-2 flex flex-wrap items-center gap-1.5">
                            <Badge :variant="cohortStatusVariant(cohort.status)">{{ cohortStatusLabel(cohort.status) }}</Badge>
                            <!-- orange-200 ground + ink text: warm urgency that still passes AA. -->
                            <span v-if="countdownLabel" class="rounded-full bg-orange-200 px-2.5 py-0.5 text-xs font-bold text-teal-900">{{ countdownLabel }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 px-5 py-4">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-teal-100 via-sand-50 to-orange-200/70 ring-1 ring-inset ring-teal-900/10">
                        <FileText class="size-5 text-teal-700" />
                    </span>
                    <div class="min-w-0 flex-1 text-sm">
                        <p class="text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-muted-foreground">Materi & info</p>
                        <a
                            v-if="cohort.materials_url"
                            :href="cohort.materials_url"
                            target="_blank"
                            rel="noopener"
                            class="mt-0.5 inline-flex items-center gap-1 font-semibold text-teal-700 hover:underline"
                        >
                            <FileText class="size-3.5" /> Materi kelas
                        </a>
                        <p v-else class="mt-0.5 text-muted-foreground/60 italic">Materi belum diisi.</p>
                        <div class="mt-2">
                            <button
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-full border border-border px-3 py-1 text-xs font-semibold text-teal-700 transition hover:border-teal-600/50 hover:bg-accent"
                                @click="copyClassInfo"
                            >
                                <Check v-if="copiedInfo" class="size-3.5" />
                                <Copy v-else class="size-3.5" />
                                {{ copiedInfo ? 'Tersalin!' : 'Salin info kelas terpilih' }}
                            </button>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 px-5 py-4">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-teal-100 via-sand-50 to-orange-200/70 ring-1 ring-inset ring-teal-900/10">
                        <Ticket class="size-5 text-teal-700" />
                    </span>
                    <div class="min-w-0 text-sm">
                        <p class="text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-muted-foreground">Pendaftaran</p>
                        <p class="mt-0.5 text-muted-foreground">
                            <template v-if="cohort.registration_opens_at || cohort.registration_closes_at">
                                <span class="font-semibold text-foreground">{{ cohort.registration_opens_at ? fmtDateTime(cohort.registration_opens_at) : '—' }}</span>
                                <span class="text-muted-foreground/70"> s.d. </span>
                                <span class="font-semibold text-foreground">{{ cohort.registration_closes_at ? fmtDateTime(cohort.registration_closes_at) : 'tanpa batas' }}</span>
                            </template>
                            <span v-else class="text-muted-foreground/60 italic">Jendela belum diatur.</span>
                        </p>
                        <div class="mt-2">
                            <Badge :variant="cohort.registration_open ? 'default' : 'secondary'">
                                {{ cohort.registration_open ? 'Dibuka' : 'Tutup' }}
                            </Badge>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Daftar kelas: satu blok per pertemuan; blok terpilih memegang absensi. -->
            <div class="mt-6">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Kelas</p>
                    <Button v-if="auth.can('cohorts.manage')" variant="outline" size="sm" @click="openCreateSession">
                        <Plus class="mr-1 h-3.5 w-3.5" /> Tambah kelas
                    </Button>
                </div>

                <div v-if="!sessionList.length" class="mt-3 rounded-xl border border-dashed border-border px-4 py-8 text-center text-sm text-muted-foreground">
                    Belum ada kelas. Tambahkan kelas pertama untuk mulai mencatat kehadiran.
                </div>

                <div v-else class="mt-3 grid gap-2 md:grid-cols-2">
                    <button
                        v-for="session in sessionList"
                        :key="session.id"
                        type="button"
                        class="rounded-xl border px-4 py-3 text-left transition"
                        :class="session.id === selectedSessionId
                            ? 'border-teal-600 bg-teal-600/5 ring-1 ring-teal-600'
                            : 'border-border bg-card hover:border-teal-600/50'"
                        @click="selectedSessionId = session.id"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <p class="min-w-0 truncate text-sm font-semibold text-foreground">{{ session.title }}</p>
                            <Badge variant="secondary" class="shrink-0">
                                {{ session.type === 'online' ? 'Online' : 'Offline' }}
                            </Badge>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ session.scheduled_at ? fmtDateTime(session.scheduled_at) + ' WIB' : 'Jadwal belum diatur' }}
                            · {{ session.attendances_count }} hadir
                        </p>
                        <p v-if="session.type === 'offline' && session.location_name" class="mt-0.5 truncate text-xs text-muted-foreground">
                            {{ session.location_name }}
                        </p>
                        <div v-if="auth.can('cohorts.manage')" class="mt-2 flex gap-1.5">
                            <Button variant="ghost" size="sm" class="h-7 px-2 text-xs" @click.stop="openEditSession(session)">
                                <Pencil class="mr-1 h-3 w-3" /> Ubah
                            </Button>
                            <Button variant="ghost" size="sm" class="h-7 px-2 text-xs text-destructive" @click.stop="deleteTarget = session; deleteError = ''">
                                <Trash2 class="mr-1 h-3 w-3" /> Hapus
                            </Button>
                        </div>
                    </button>
                </div>
            </div>

            <!-- Daftar hadir: kehadiran dicatat per kelas terpilih di atas. -->
            <div class="mt-6 overflow-hidden rounded-xl border border-border bg-card">
                <div v-if="selectedSession && activeRosterCount" class="flex flex-wrap items-center justify-between gap-2 border-b border-border px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Daftar hadir — {{ selectedSession.title }}</p>
                    <!-- Progres yang terlihat mendorong pencatatan sampai tuntas;
                         setelah kelas usai ia menjadi rekap. -->
                    <div class="flex items-center gap-2.5">
                        <span class="text-xs font-semibold" :class="cohort.status === 'ended' ? 'text-teal-700' : 'text-muted-foreground'">
                            {{ cohort.status === 'ended' ? `Selesai · ${hadirCount}/${activeRosterCount} hadir` : `Hadir ${hadirCount}/${activeRosterCount}` }}
                        </span>
                        <div class="h-1.5 w-28 overflow-hidden rounded-full bg-secondary">
                            <div
                                class="h-full rounded-full bg-teal-600 transition-[width] duration-300"
                                :style="{ width: `${Math.round((hadirCount / activeRosterCount) * 100)}%` }"
                            ></div>
                        </div>
                    </div>
                </div>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <th class="px-3 py-3 font-semibold sm:px-4">Peserta</th>
                            <th class="px-2 py-3 text-center font-semibold sm:px-3">Kehadiran</th>
                            <!-- Status hanya bermakna saat menyimpang; di layar sempit
                                 kolomnya dilipat (baris dropped sudah tampak pudar). -->
                            <th class="hidden px-3 py-3 font-semibold sm:table-cell">Status</th>
                            <th class="px-2 py-3 sm:px-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!roster.length">
                            <td colspan="4" class="px-4 py-10 text-center text-muted-foreground">Belum ada peserta.</td>
                        </tr>
                        <tr
                            v-for="row in roster"
                            :key="row.enrollment_id"
                            class="border-b border-border last:border-0"
                            :class="row.latest_status === 'dropped' ? 'opacity-50' : ''"
                        >
                            <td class="max-w-0 px-3 py-3 sm:px-4">
                                <RouterLink :to="{ name: 'person', params: { id: row.person.id } }" class="block truncate font-medium text-foreground hover:underline">
                                    {{ row.person.name }}
                                </RouterLink>
                                <div class="truncate text-xs text-muted-foreground">{{ row.person.phone }}</div>
                            </td>
                            <td class="px-2 py-3 text-center sm:px-3">
                                <button
                                    v-if="selectedSession"
                                    type="button"
                                    :disabled="!canToggle(row)"
                                    class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full border px-3 py-1.5 text-xs font-semibold transition disabled:cursor-not-allowed sm:px-3.5"
                                    :class="isHadir(row, selectedSession)
                                        ? 'border-teal-600 bg-teal-600 text-white'
                                        : 'border-border text-muted-foreground hover:border-teal-600/50 hover:text-foreground'"
                                    @click="toggleAttendance(row, selectedSession)"
                                >
                                    <Check v-if="isHadir(row, selectedSession)" class="size-3.5" />
                                    {{ isHadir(row, selectedSession) ? 'Hadir' : 'Tandai hadir' }}
                                </button>
                            </td>
                            <td class="hidden px-3 py-3 sm:table-cell">
                                <!-- Status default (accepted/belum ada) adalah derau; hanya
                                     kondisi menyimpang yang layak menarik mata. -->
                                <Badge v-if="row.latest_status && row.latest_status !== 'accepted'" :variant="statusVariant(row.latest_status)">
                                    {{ statusLabel(row.latest_status) }}
                                </Badge>
                                <span v-else class="text-xs text-muted-foreground/50">—</span>
                            </td>
                            <td class="px-2 py-3 text-right whitespace-nowrap sm:px-3">
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
                                    title="Hapus dari Angkatan / Kelas"
                                    aria-label="Hapus enrollment"
                                    @click="removeEnrollment(row)"
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-border px-4 py-2.5 text-xs text-muted-foreground">
                    <span v-if="auth.can('attendance.record')">Ketuk tombol kehadiran untuk mencatat hadir. Tersimpan otomatis.</span>
                    <span v-else>Anda hanya dapat melihat kehadiran.</span>
                    <span v-if="isSavingAttendance" class="font-medium text-teal-700">Menyimpan…</span>
                </div>
            </div>
        </template>

        <!-- Tambah peserta -->
        <Dialog v-model:open="addOpen" title="Tambah Peserta">
            <Alert v-if="addError" class="mb-3 px-3.5 py-2.5">{{ addError }}</Alert>
            <p v-if="!candidates.length" class="text-sm text-muted-foreground">Tidak ada pelamar diterima yang belum terdaftar di Angkatan / Kelas ini.</p>
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
            <Alert v-if="dropError" class="mt-3 px-3.5 py-2.5">{{ dropError }}</Alert>
            <Textarea v-model="dropNote" rows="3" placeholder="Alasan keluar (wajib)" class="mt-3" />
            <div class="mt-4 flex justify-end gap-2">
                <Button variant="outline" size="sm" @click="dropTarget = null">Batal</Button>
                <Button variant="destructive" size="sm" :disabled="!dropNote" @click="confirmDrop">Keluarkan</Button>
            </div>
        </Dialog>

        <CohortFormDialog v-model:open="editOpen" :cohort="cohort" @saved="load" />

        <SessionFormDialog
            v-model:open="sessionFormOpen"
            :cohort-id="cohort?.id"
            :session="sessionBeingEdited"
            @saved="load"
        />

        <Dialog :open="deleteTarget !== null" title="Hapus kelas ini?" @update:open="deleteTarget = null">
            <p class="text-sm text-muted-foreground">
                Menghapus "{{ deleteTarget?.title }}" juga menghapus {{ deleteTarget?.attendances_count ?? 0 }}
                catatan kehadiran kelas ini. Tindakan ini tidak bisa dibatalkan.
            </p>
            <Alert v-if="deleteError" class="mt-3">{{ deleteError }}</Alert>
            <div class="mt-4 flex justify-end gap-2">
                <Button variant="outline" size="sm" @click="deleteTarget = null">Batal</Button>
                <Button variant="destructive" size="sm" @click="confirmDeleteSession">Hapus</Button>
            </div>
        </Dialog>

    </div>
</template>
