<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { ArrowLeft, ExternalLink, Eye, Pencil } from 'lucide-vue-next';
import { programs as programsApi, applications as applicationsApi } from '@/api';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { useAuthStore } from '@/stores/auth';
import { statusVariant, statusLabel, programStatusVariant, programStatusLabel, cohortStatusVariant, cohortStatusLabel } from '@/lib/status';
import { fmtDate, cohortPeriodLabel } from '@/lib/format';
import ApplicationDecisionToggle from '@/components/ApplicationDecisionToggle.vue';
import ProgramFormDialog from '@/components/ProgramFormDialog.vue';
import CohortFormDialog from '@/components/CohortFormDialog.vue';
import RejectApplicationDialog from '@/components/RejectApplicationDialog.vue';
import StatTile from '@/components/StatTile.vue';

const props = defineProps({ id: { type: [String, Number], required: true } });

const auth = useAuthStore();
const router = useRouter();

const program = ref(null);
const cohorts = ref([]);
const applications = ref([]);
const stats = ref(null);
const loading = ref(true);
const error = ref('');

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

function decide(app, value) {
    value === 'accepted' ? accept(app) : openReject(app);
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
            : `${name} diterima. Tempatkan ke angkatan / kelas lewat halaman profilnya.`;
        await load();
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        reviewError.value = e.message ?? 'Gagal menyimpan keputusan.';
    } finally {
        reviewingId.value = null;
    }
}

function openReject(app) {
    rejectTarget.value = app;
}

async function confirmReject(note) {
    const app = rejectTarget.value;
    reviewError.value = '';
    reviewSuccess.value = '';
    try {
        await applicationsApi.review(app.id, 'rejected', { review_note: note });
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
                            <Badge :variant="programStatusVariant(program.status)">
                                {{ programStatusLabel(program.status) }}
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
                <StatTile :value="stats.pending" label="Menunggu Review" :value-class="stats.pending ? 'text-orange-600' : 'text-foreground'" />
                <StatTile :value="stats.accepted" label="Diterima" />
                <StatTile :value="stats.rejected" label="Ditolak" />
                <StatTile :value="stats.active_participants" label="Peserta Aktif" />
            </div>

            <!-- Angkatan -->
            <div class="mt-8 flex items-end justify-between gap-4">
                <h2 class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Angkatan / Kelas</h2>
                <Button v-if="auth.can('cohorts.manage')" variant="accent" size="sm" @click="openCohortCreate">Tambah Angkatan / Kelas</Button>
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
                        <tr v-if="!cohorts.length">
                            <td colspan="7" class="px-4 py-10 text-center">
                                <p class="text-sm text-muted-foreground">
                                    Belum ada angkatan / kelas. Program belum bisa menerima pendaftar sampai angkatan / kelas pertama dibuka.
                                </p>
                                <Button
                                    v-if="auth.can('cohorts.manage')"
                                    variant="accent"
                                    size="sm"
                                    class="mt-4"
                                    @click="openCohortCreate"
                                >
                                    Tambah Angkatan / Kelas Pertama
                                </Button>
                            </td>
                        </tr>
                        <tr
                            v-for="cohort in cohorts"
                            :key="cohort.id"
                            class="cursor-pointer border-b border-border transition-colors last:border-0 hover:bg-accent/50"
                            @click="goCohort(cohort)"
                        >
                            <td class="px-4 py-3 font-medium text-foreground">{{ cohort.name }}</td>
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
                                <Button variant="ghost" size="icon" class="h-8 w-8" title="Lihat detail" aria-label="Lihat detail Angkatan / Kelas" @click.stop="goCohort(cohort)">
                                    <Eye class="size-4" />
                                </Button>
                                <Button
                                    v-if="auth.can('cohorts.manage')"
                                    variant="ghost"
                                    size="icon"
                                    class="h-8 w-8"
                                    title="Ubah"
                                    aria-label="Ubah Angkatan / Kelas"
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
            <Alert v-if="reviewError" class="mt-3">{{ reviewError }}</Alert>
            <Alert v-if="reviewSuccess" variant="success" class="mt-3">{{ reviewSuccess }}</Alert>
            <div v-if="selectedCohortId === ''" class="mt-3 rounded-xl border border-border bg-card px-5 py-10 text-center">
                <template v-if="cohorts.length || hasUnlinked">
                    <p class="text-sm text-muted-foreground">Pilih Angkatan / Kelas untuk melihat pendaftarnya.</p>
                    <NativeSelect v-model="selectedCohortId" class="mx-auto mt-3 w-64 max-w-full" aria-label="Pilih Angkatan / Kelas">
                        <option value="">Pilih Angkatan / Kelas…</option>
                        <option v-for="cohort in cohorts" :key="cohort.id" :value="String(cohort.id)">{{ cohort.name }}</option>
                        <option v-if="hasUnlinked" value="none">Tanpa angkatan / kelas</option>
                    </NativeSelect>
                </template>
                <p v-else class="text-sm text-muted-foreground">Belum ada pendaftar. Buka angkatan / kelas dulu agar kelas ini bisa didaftari.</p>
            </div>
            <template v-else>
                <NativeSelect v-model="selectedCohortId" class="mt-3 w-64 max-w-full" aria-label="Pilih Angkatan / Kelas">
                    <option value="">Pilih Angkatan / Kelas…</option>
                    <option v-for="cohort in cohorts" :key="cohort.id" :value="String(cohort.id)">{{ cohort.name }}</option>
                    <option v-if="hasUnlinked" value="none">Tanpa angkatan / kelas</option>
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
                            <tr v-if="!filteredApplications.length"><td colspan="3" class="px-4 py-8 text-center text-muted-foreground">Belum ada pendaftar di angkatan / kelas ini.</td></tr>
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
                                    <div v-if="auth.can('applications.review')" @click.stop>
                                        <ApplicationDecisionToggle
                                            :status="app.status"
                                            :disabled="reviewingId === app.id"
                                            @decide="(v) => decide(app, v)"
                                        />
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
            <RejectApplicationDialog
                :target="rejectTarget"
                :person-name="rejectTarget?.person?.name ?? ''"
                :warning="rejectTarget?.enrollment ? `Penempatannya di ${rejectTarget.enrollment.cohort_name} tidak ikut terhapus; kelola dari halaman Angkatan / Kelas bila perlu.` : ''"
                @close="rejectTarget = null"
                @confirm="confirmReject"
            />
        </template>

        <div v-else class="mt-16 text-center text-muted-foreground">{{ error || 'Data tidak ditemukan.' }}</div>
    </div>
</template>
