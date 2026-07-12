<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { ArrowLeft, Check, ExternalLink, Eye, Pencil, X } from 'lucide-vue-next';
import { programs as programsApi, applications as applicationsApi } from '@/api';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';
import { NativeSelect } from '@/components/ui/native-select';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useAuthStore } from '@/stores/auth';
import { statusVariant, statusLabel } from '@/lib/status';
import ProgramFormDialog from '@/components/ProgramFormDialog.vue';
import CohortFormDialog from '@/components/CohortFormDialog.vue';

const props = defineProps({ id: { type: [String, Number], required: true } });

const auth = useAuthStore();
const router = useRouter();

const program = ref(null);
const cohorts = ref([]);
const applications = ref([]);
const stats = ref(null);
const loading = ref(true);
const error = ref('');

const PROGRAM_STATUS = {
    draft: { label: 'Draf', variant: 'secondary' },
    active: { label: 'Aktif', variant: 'success' },
    inactive: { label: 'Nonaktif', variant: 'destructive' },
};

const COHORT_STATUS = {
    upcoming: { label: 'Akan datang', variant: 'warning' },
    active: { label: 'Berjalan', variant: 'success' },
    ended: { label: 'Selesai', variant: 'secondary' },
};

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const res = await programsApi.detail(props.id);
        program.value = res.program;
        cohorts.value = res.cohorts;
        applications.value = res.applications;
        stats.value = res.stats;
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        program.value = null;
        error.value = e.status === 404 ? 'Program tidak ditemukan.' : (e.message ?? 'Gagal memuat data.');
    } finally {
        loading.value = false;
    }
}

onMounted(load);
// Re-fetch when navigating between two program URLs (component instance is reused).
watch(() => props.id, () => load());

// --- Edit program -----------------------------------------------------------

const editOpen = ref(false);

// --- Angkatan ----------------------------------------------------------------

const cohortDialogOpen = ref(false);
const editingCohort = ref(null);

function openCohortCreate() {
    editingCohort.value = null;
    cohortDialogOpen.value = true;
}

function openCohortEdit(cohort) {
    editingCohort.value = cohort;
    cohortDialogOpen.value = true;
}

function goCohort(cohort) {
    router.push({ name: 'cohort-detail', params: { id: cohort.id } });
}

/** Periode terbaca manusiawi; keduanya kosong = belum dijadwalkan. */
function periodLabel(cohort) {
    if (!cohort.start_date && !cohort.end_date) {
        return null;
    }

    return `${fmtDate(cohort.start_date)} – ${fmtDate(cohort.end_date)}`;
}

// --- Pendaftar: dikelompokkan per angkatan terpilih ---------------------------

// Angkatan aktif di dropdown; '' = belum memilih (empty state).
const selectedCohortId = ref('');

/** Angkatan efektif: penempatan nyata menang atas target lamaran. */
function effectiveCohortId(app) {
    return app.enrollment?.cohort_id ?? app.cohort_id ?? null;
}

// Lamaran legacy tanpa angkatan butuh opsi khusus agar tetap terjangkau.
const hasUnlinked = computed(() => applications.value.some((app) => effectiveCohortId(app) === null));

const filteredApplications = computed(() => {
    if (selectedCohortId.value === '') return [];
    if (selectedCohortId.value === 'none') {
        return applications.value.filter((app) => effectiveCohortId(app) === null);
    }
    return applications.value.filter((app) => effectiveCohortId(app) === Number(selectedCohortId.value));
});

// --- Aksi cepat Terima/Tolak ---------------------------------------------------

const reviewingId = ref(null);
const reviewError = ref('');
const reviewSuccess = ref('');
const rejectTarget = ref(null); // application yang akan ditolak (dialog konfirmasi)
const rejectNote = ref('');

/**
 * Keputusan lewat toggle dua-pilihan pada baris: Diterima atau Ditolak.
 * "Menunggu" bukan pilihan (aturan satu arah); memilih status yang sama
 * atau klik-deselect diabaikan.
 */
function decide(app, value) {
    if (!value || value === app.status) return;
    if (value === 'accepted') {
        accept(app);
    } else if (value === 'rejected') {
        openReject(app);
    }
}

async function accept(app) {
    reviewingId.value = app.id;
    reviewError.value = '';
    reviewSuccess.value = '';
    try {
        const res = await applicationsApi.review(app.id, 'accepted');
        const name = app.person?.name ?? 'Pendaftar';
        reviewSuccess.value = res.application.enrollment
            ? `${name} diterima dan ditempatkan di ${res.application.enrollment.cohort_name}.`
            : `${name} diterima. Tempatkan ke angkatan lewat halaman profilnya.`;
        await load();
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        reviewError.value = e.message ?? 'Gagal menyimpan keputusan.';
    } finally {
        reviewingId.value = null;
    }
}

function openReject(app) {
    rejectNote.value = '';
    rejectTarget.value = app;
}

async function confirmReject() {
    const app = rejectTarget.value;
    reviewError.value = '';
    reviewSuccess.value = '';
    try {
        await applicationsApi.review(app.id, 'rejected', { review_note: rejectNote.value || null });
        rejectTarget.value = null;
        reviewSuccess.value = `Pendaftaran ${app.person?.name ?? 'pendaftar'} ditolak.`;
        await load();
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        rejectTarget.value = null;
        reviewError.value = e.message ?? 'Gagal menyimpan keputusan.';
    }
}

function goPerson(app) {
    if (!app.person) return; // orang sudah dihapus; tidak ada halaman tujuan
    router.push({ name: 'person', params: { id: app.person.id } });
}

function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

<template>
    <div>
        <RouterLink :to="{ name: 'programs' }" class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
            <ArrowLeft class="size-4" /> Kembali ke katalog
        </RouterLink>

        <div v-if="loading" class="mt-10 text-center text-muted-foreground">Memuat…</div>

        <template v-else-if="program">
            <!-- Header -->
            <div class="mt-4 overflow-hidden rounded-xl border border-border bg-card">
                <div class="flex flex-col gap-5 p-6 sm:flex-row">
                    <img
                        v-if="program.thumbnail_url"
                        :src="program.thumbnail_url"
                        alt=""
                        class="aspect-video w-full rounded-lg object-cover sm:w-56"
                    />
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Program</p>
                                <h1 class="mt-1 text-2xl font-bold text-foreground">{{ program.name }}</h1>
                                <p v-if="program.tagline" class="mt-1 text-sm text-muted-foreground">{{ program.tagline }}</p>
                            </div>
                            <Button v-if="auth.can('programs.manage')" variant="outline" size="sm" @click="editOpen = true">
                                <Pencil class="size-3.5" /> Ubah Program
                            </Button>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-1.5">
                            <Badge :variant="PROGRAM_STATUS[program.status]?.variant ?? 'secondary'">
                                {{ PROGRAM_STATUS[program.status]?.label ?? program.status }}
                            </Badge>
                            <Badge variant="secondary">
                                {{ program.type === 'affiliate_community' ? 'Affiliate L' + program.level : 'Umum' }}
                            </Badge>
                            <Badge :variant="program.is_open ? 'success' : 'secondary'">
                                {{ program.is_open ? 'Pendaftaran buka' : 'Pendaftaran tutup' }}
                            </Badge>
                        </div>
                        <a
                            :href="`/program/${program.slug}`"
                            target="_blank"
                            rel="noopener"
                            class="mt-3 inline-flex items-center gap-1.5 text-sm text-teal-700 hover:underline"
                        >
                            Lihat halaman publik <code class="text-xs">/program/{{ program.slug }}</code>
                            <ExternalLink class="size-3.5" />
                        </a>
                    </div>
                </div>
            </div>

            <!-- Funnel stats -->
            <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border border-border bg-card p-5">
                    <div class="text-3xl font-bold tabular-nums" :class="stats.pending ? 'text-orange-600' : 'text-foreground'">{{ stats.pending }}</div>
                    <div class="mt-1 text-xs uppercase tracking-wide text-muted-foreground">Menunggu Review</div>
                </div>
                <div class="rounded-xl border border-border bg-card p-5">
                    <div class="text-3xl font-bold tabular-nums text-foreground">{{ stats.accepted }}</div>
                    <div class="mt-1 text-xs uppercase tracking-wide text-muted-foreground">Diterima</div>
                </div>
                <div class="rounded-xl border border-border bg-card p-5">
                    <div class="text-3xl font-bold tabular-nums text-foreground">{{ stats.rejected }}</div>
                    <div class="mt-1 text-xs uppercase tracking-wide text-muted-foreground">Ditolak</div>
                </div>
                <div class="rounded-xl border border-border bg-card p-5">
                    <div class="text-3xl font-bold tabular-nums text-foreground">{{ stats.active_participants }}</div>
                    <div class="mt-1 text-xs uppercase tracking-wide text-muted-foreground">Peserta Aktif</div>
                </div>
            </div>

            <!-- Angkatan -->
            <div class="mt-8 flex items-end justify-between gap-4">
                <h2 class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Angkatan</h2>
                <Button v-if="auth.can('cohorts.manage')" variant="accent" size="sm" @click="openCohortCreate">Tambah Angkatan</Button>
            </div>
            <div class="mt-3 overflow-hidden rounded-xl border border-border bg-card">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <th class="px-4 py-3 font-semibold">Nama</th>
                            <th class="px-4 py-3 font-semibold">Periode</th>
                            <th class="px-4 py-3 font-semibold">Pendaftaran</th>
                            <th class="px-4 py-3 font-semibold">Mentor</th>
                            <th class="px-4 py-3 font-semibold">Peserta</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!cohorts.length"><td colspan="7" class="px-4 py-8 text-center text-muted-foreground">Belum ada angkatan. Buka kelas pertama dengan tombol Tambah Angkatan.</td></tr>
                        <tr
                            v-for="cohort in cohorts"
                            :key="cohort.id"
                            class="cursor-pointer border-b border-border transition-colors last:border-0 hover:bg-accent/50"
                            @click="goCohort(cohort)"
                        >
                            <td class="px-4 py-3 font-medium text-foreground">{{ cohort.name }}</td>
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
                                <Badge :variant="COHORT_STATUS[cohort.status]?.variant ?? 'secondary'">
                                    {{ COHORT_STATUS[cohort.status]?.label ?? cohort.status }}
                                </Badge>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <Button variant="ghost" size="icon" class="h-8 w-8" title="Lihat detail" aria-label="Lihat detail Angkatan" @click.stop="goCohort(cohort)">
                                    <Eye class="size-4" />
                                </Button>
                                <Button
                                    v-if="auth.can('cohorts.manage')"
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8"
                                    title="Ubah"
                                    aria-label="Ubah Angkatan"
                                    @click.stop="openCohortEdit(cohort)"
                                >
                                    <Pencil class="size-4" />
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pendaftar: daftar per angkatan, dipilih lewat dropdown -->
            <h2 class="mt-8 font-display text-xs uppercase tracking-[0.3em] text-orange-600">Pendaftar</h2>
            <div v-if="reviewError" class="mt-3 rounded-lg border border-destructive/30 bg-red-50 px-4 py-3 text-sm text-destructive">
                {{ reviewError }}
            </div>
            <div v-if="reviewSuccess" class="mt-3 rounded-lg border border-teal-600/30 bg-teal-50 px-4 py-3 text-sm text-teal-700">
                {{ reviewSuccess }}
            </div>
            <div v-if="selectedCohortId === ''" class="mt-3 rounded-xl border border-border bg-card px-5 py-10 text-center">
                <template v-if="cohorts.length || hasUnlinked">
                    <p class="text-sm text-muted-foreground">Pilih Angkatan untuk melihat pendaftarnya.</p>
                    <NativeSelect v-model="selectedCohortId" class="mx-auto mt-3 w-64 max-w-full" aria-label="Pilih Angkatan">
                        <option value="">Pilih Angkatan…</option>
                        <option v-for="cohort in cohorts" :key="cohort.id" :value="String(cohort.id)">{{ cohort.name }}</option>
                        <option v-if="hasUnlinked" value="none">Tanpa angkatan</option>
                    </NativeSelect>
                </template>
                <p v-else class="text-sm text-muted-foreground">Belum ada angkatan maupun pendaftar.</p>
            </div>
            <template v-else>
                <NativeSelect v-model="selectedCohortId" class="mt-3 w-64 max-w-full" aria-label="Pilih Angkatan">
                    <option value="">Pilih Angkatan…</option>
                    <option v-for="cohort in cohorts" :key="cohort.id" :value="String(cohort.id)">{{ cohort.name }}</option>
                    <option v-if="hasUnlinked" value="none">Tanpa angkatan</option>
                </NativeSelect>
                <div class="mt-3 overflow-hidden rounded-xl border border-border bg-card">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                                <th class="px-4 py-3 font-semibold">Nama</th>
                                <th class="px-4 py-3 font-semibold">Tanggal</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!filteredApplications.length"><td colspan="3" class="px-4 py-8 text-center text-muted-foreground">Belum ada pendaftar di angkatan ini.</td></tr>
                            <tr
                                v-for="app in filteredApplications"
                                :key="app.id"
                                class="border-b border-border transition-colors last:border-0"
                                :class="app.person ? 'cursor-pointer hover:bg-accent/50' : ''"
                                @click="goPerson(app)"
                            >
                                <td class="px-4 py-3">
                                    <template v-if="app.person">
                                        <div class="font-medium text-foreground">{{ app.person.name }}</div>
                                        <div class="text-xs text-muted-foreground">{{ app.person.phone }}<span v-if="app.person.email"> · {{ app.person.email }}</span></div>
                                    </template>
                                    <span v-else class="italic text-muted-foreground">Orang dihapus</span>
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ fmtDate(app.created_at) }}</td>
                                <td class="px-4 py-3">
                                    <!-- Keputusan dua pilihan langsung di baris; menunggu = belum ada yang aktif. -->
                                    <div v-if="auth.can('applications.review')" class="flex items-center gap-2" @click.stop>
                                        <ToggleGroup
                                            type="single"
                                            variant="outline"
                                            :model-value="app.status === 'pending' ? '' : app.status"
                                            :disabled="reviewingId === app.id"
                                            @update:model-value="(v) => decide(app, v)"
                                        >
                                            <ToggleGroupItem value="accepted" class="gap-1 text-teal-700 data-[state=on]:bg-teal-50 data-[state=on]:text-teal-700">
                                                <Check class="size-3.5" /> Diterima
                                            </ToggleGroupItem>
                                            <ToggleGroupItem value="rejected" class="gap-1 text-destructive data-[state=on]:bg-red-50 data-[state=on]:text-destructive">
                                                <X class="size-3.5" /> Ditolak
                                            </ToggleGroupItem>
                                        </ToggleGroup>
                                        <span v-if="app.status === 'pending'" class="text-xs text-muted-foreground">menunggu</span>
                                    </div>
                                    <Badge v-else :variant="statusVariant(app.status)">{{ statusLabel(app.status) }}</Badge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>

            <!-- Dialogs -->
            <ProgramFormDialog v-model:open="editOpen" :program="program" @saved="load" @thumbnail-changed="load" />

            <CohortFormDialog
                v-model:open="cohortDialogOpen"
                :cohort="editingCohort"
                :locked-program="{ id: program.id, name: program.name }"
                @saved="load"
            />

            <!-- Konfirmasi tolak pendaftaran (alasan opsional, tercatat di riwayat) -->
            <Dialog :open="rejectTarget !== null" title="Tolak Pendaftaran" @update:open="rejectTarget = null">
                <p class="text-sm text-muted-foreground">
                    Tolak pendaftaran {{ rejectTarget?.person?.name ?? 'pendaftar ini' }}? Dia masih boleh mendaftar lagi di lain waktu.
                </p>
                <p v-if="rejectTarget?.enrollment" class="mt-2 text-sm text-muted-foreground">
                    Penempatannya di {{ rejectTarget.enrollment.cohort_name }} tidak ikut terhapus; kelola dari halaman Angkatan bila perlu.
                </p>
                <div class="mt-3">
                    <label class="text-xs text-muted-foreground">Alasan (opsional)</label>
                    <textarea
                        v-model="rejectNote"
                        rows="3"
                        placeholder="Contoh: belum sesuai kriteria angkatan ini."
                        class="mt-1.5 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    ></textarea>
                </div>
                <div class="mt-4 flex justify-end gap-2">
                    <Button variant="outline" size="sm" @click="rejectTarget = null">Batal</Button>
                    <Button variant="destructive" size="sm" @click="confirmReject">Tolak Pendaftaran</Button>
                </div>
            </Dialog>
        </template>

        <div v-else class="mt-16 text-center text-muted-foreground">{{ error || 'Data tidak ditemukan.' }}</div>
    </div>
</template>
