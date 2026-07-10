<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { ArrowLeft } from 'lucide-vue-next';
import { cohorts as cohortsApi, enrollments as enrollmentsApi, sessions as sessionsApi, api } from '@/api';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Dialog } from '@/components/ui/dialog';
import { useAuthStore } from '@/stores/auth';

const props = defineProps({ id: { type: [String, Number], required: true } });

const auth = useAuthStore();

const cohort = ref(null);
const requirement = ref(0);
const sessionList = ref([]);
const roster = ref([]);
const loading = ref(true);
const error = ref('');

// Absensi state
const activeSessionId = ref(null);
const hadirSet = ref(new Set());
const savingAttendance = ref(false);

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

const selectClass =
    'h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

const activeSession = computed(() => sessionList.value.find((s) => s.id === activeSessionId.value) ?? null);

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const res = await cohortsApi.detail(props.id);
        cohort.value = res.cohort;
        requirement.value = res.requirement;
        sessionList.value = res.sessions;
        roster.value = res.roster;
        if (!activeSessionId.value && res.sessions.length) activeSessionId.value = res.sessions[0].id;
        syncHadirSet();
    } catch (e) {
        if (e.sessionExpired) return;
        error.value = e.message ?? 'Gagal memuat data.';
    } finally {
        loading.value = false;
    }
}

function syncHadirSet() {
    const set = new Set();
    roster.value.forEach((r) => {
        if (r.attended_session_ids.includes(activeSessionId.value)) set.add(r.enrollment_id);
    });
    hadirSet.value = set;
}

function selectSession(id) {
    activeSessionId.value = Number(id);
    syncHadirSet();
}

function toggleHadir(enrollmentId) {
    const set = new Set(hadirSet.value);
    set.has(enrollmentId) ? set.delete(enrollmentId) : set.add(enrollmentId);
    hadirSet.value = set;
}

async function saveAttendance() {
    if (!activeSession.value) return;
    savingAttendance.value = true;
    try {
        await sessionsApi.setAttendance(activeSession.value.id, [...hadirSet.value]);
        await load();
    } catch (e) {
        if (!e.sessionExpired) error.value = e.message ?? 'Gagal menyimpan absensi.';
    } finally {
        savingAttendance.value = false;
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

async function confirmRemoveSession() {
    const session = deleteSessionTarget.value;
    error.value = '';
    try {
        await sessionsApi.remove(session.id);
        if (activeSessionId.value === session.id) activeSessionId.value = null;
        deleteSessionTarget.value = null;
        await load();
    } catch (e) {
        if (!e.sessionExpired) error.value = e.message ?? 'Gagal menghapus sesi.';
    }
}

function statusVariant(status) {
    return { accepted: 'warning', completed: 'success', dropped: 'destructive' }[status] ?? 'secondary';
}

function statusLabel(status) {
    return { accepted: 'Aktif', completed: 'Lulus', dropped: 'Keluar' }[status] ?? (status ?? 'Belum ada status');
}

function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
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
                        Syarat lulus: hadir {{ requirement }} dari {{ sessionList.length }} sesi · Mentor: {{ cohort.mentor?.name ?? '—' }}
                    </p>
                </div>
                <Button v-if="auth.can('enrollments.manage')" variant="accent" size="sm" @click="openAdd">Tambah Peserta</Button>
            </div>

            <!-- Peserta -->
            <div class="mt-6 overflow-hidden rounded-xl border border-border bg-card">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <th class="px-4 py-3 font-semibold">Peserta</th>
                            <th class="px-4 py-3 font-semibold">Kehadiran</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!roster.length"><td colspan="4" class="px-4 py-10 text-center text-muted-foreground">Belum ada peserta.</td></tr>
                        <tr v-for="row in roster" :key="row.enrollment_id" class="border-b border-border last:border-0">
                            <td class="px-4 py-3">
                                <RouterLink :to="{ name: 'person', params: { id: row.person.id } }" class="font-medium text-foreground hover:underline">
                                    {{ row.person.name }}
                                </RouterLink>
                                <div class="text-xs text-muted-foreground">{{ row.person.phone }}</div>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ row.hadir }}/{{ requirement }}</td>
                            <td class="px-4 py-3">
                                <Badge :variant="statusVariant(row.latest_status)">{{ statusLabel(row.latest_status) }}</Badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button v-if="auth.can('enrollments.manage') && row.latest_status !== 'dropped'" variant="ghost" size="sm" @click="openDrop(row)">Keluarkan</Button>
                                <Button v-if="auth.can('enrollments.manage') && row.hadir === 0 && (row.latest_status === 'accepted' || !row.latest_status)" variant="ghost" size="sm" @click="removeEnrollment(row)">Hapus</Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Sesi + Absensi -->
            <div class="mt-8 grid gap-6 lg:grid-cols-2">
                <div>
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Sesi</h2>
                        <Button v-if="auth.can('cohorts.manage')" variant="outline" size="sm" @click="openSessionForm()">Tambah Sesi</Button>
                    </div>
                    <div class="mt-3 overflow-hidden rounded-xl border border-border bg-card">
                        <div v-if="!sessionList.length" class="px-4 py-8 text-center text-sm text-muted-foreground">Belum ada sesi. Tambahkan jadwal pertemuan Angkatan ini.</div>
                        <div v-for="s in sessionList" :key="s.id" class="flex items-center justify-between border-b border-border px-4 py-3 text-sm last:border-0">
                            <div>
                                <p class="font-medium text-foreground">{{ s.title }}</p>
                                <p class="text-xs text-muted-foreground">{{ fmtDate(s.scheduled_at) }} · {{ s.attendances_count }} hadir</p>
                            </div>
                            <div v-if="auth.can('cohorts.manage')" class="shrink-0">
                                <Button variant="ghost" size="sm" @click="openSessionForm(s)">Ubah</Button>
                                <Button variant="ghost" size="sm" @click="deleteSessionTarget = s">Hapus</Button>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Absensi</h2>
                        <select v-if="sessionList.length" :value="activeSessionId ?? ''" :class="selectClass" @change="selectSession($event.target.value)">
                            <option v-for="s in sessionList" :key="s.id" :value="s.id">{{ s.title }}</option>
                        </select>
                    </div>
                    <div class="mt-3 overflow-hidden rounded-xl border border-border bg-card">
                        <div v-if="!activeSession || !roster.length" class="px-4 py-8 text-center text-sm text-muted-foreground">
                            {{ !sessionList.length ? 'Buat sesi dulu untuk mencatat absensi.' : 'Belum ada peserta.' }}
                        </div>
                        <template v-else>
                            <label
                                v-for="row in roster"
                                :key="row.enrollment_id"
                                class="flex items-center gap-3 border-b border-border px-4 py-2.5 text-sm last:border-0"
                                :class="row.latest_status === 'dropped' ? 'opacity-50' : 'cursor-pointer hover:bg-accent/50'"
                            >
                                <input
                                    type="checkbox"
                                    class="size-4 accent-teal-700"
                                    :checked="hadirSet.has(row.enrollment_id)"
                                    :disabled="row.latest_status === 'dropped' || !auth.can('attendance.record')"
                                    @change="toggleHadir(row.enrollment_id)"
                                />
                                <span class="text-foreground">{{ row.person.name }}</span>
                                <Badge v-if="row.latest_status === 'completed'" variant="success" class="ml-auto">Lulus</Badge>
                            </label>
                            <div v-if="auth.can('attendance.record')" class="flex items-center justify-between px-4 py-3">
                                <span class="text-xs tabular-nums text-muted-foreground">{{ hadirSet.size }} dari {{ roster.length }} hadir</span>
                                <Button size="sm" :disabled="savingAttendance" @click="saveAttendance">
                                    {{ savingAttendance ? 'Menyimpan…' : 'Simpan Absensi' }}
                                </Button>
                            </div>
                        </template>
                    </div>
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
                <div class="flex justify-end gap-2 pt-2">
                    <Button type="button" variant="outline" size="sm" @click="sessionOpen = false">Batal</Button>
                    <Button type="submit" size="sm">Simpan</Button>
                </div>
            </form>
        </Dialog>
    </div>
</template>
