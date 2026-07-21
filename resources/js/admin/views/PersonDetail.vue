<script setup>
import { ref, onMounted, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { ArrowLeft } from 'lucide-vue-next';
import { api, applications as applicationsApi, people as peopleApi } from '@/api';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Dialog } from '@/components/ui/dialog';
import PinInput from '@/components/PinInput.vue';
import { useAuthStore } from '@/stores/auth';
import { statusVariant, statusLabel } from '@/lib/status';
import { fmtDate } from '@/lib/format';
import ApplicationDecisionToggle from '@/components/ApplicationDecisionToggle.vue';
import EnrollToCohortDialog from '@/components/EnrollToCohortDialog.vue';
import EnrollPersonDialog from '@/components/EnrollPersonDialog.vue';
import RejectApplicationDialog from '@/components/RejectApplicationDialog.vue';

const GMV_LABELS = { '0-50': '0-50 Juta', '50-100': '50-100 Juta', '100+': 'Di atas 100 Juta' };
const GENDER_LABELS = { male: 'Laki-laki', female: 'Perempuan' };

const props = defineProps({ id: { type: [String, Number], required: true } });

const auth = useAuthStore();

const person = ref(null);
const loading = ref(true);

const error = ref('');
const reviewingId = ref(null);
const saveError = ref('');
const reviewSuccess = ref('');

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const res = await api(`/admin/people/${props.id}`);
        person.value = res.person;
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        person.value = null;
        error.value = e.status === 404 ? 'Pelamar tidak ditemukan.' : (e.message ?? 'Gagal memuat data.');
    } finally {
        loading.value = false;
    }
}

onMounted(load);
// Re-fetch when navigating between two person URLs (component instance is reused).
watch(() => props.id, () => load());

/** Enrollment milik lamaran ini (untuk peringatan absensi saat menolak). */
function enrollmentFor(app) {
    if (!app?.cohort_id) return null;
    return (person.value?.enrollments ?? []).find((e) => e.cohort_id === app.cohort_id) ?? null;
}

function decide(app, value) {
    value === 'accepted' ? accept(app) : (rejectTarget.value = app);
}

async function accept(app) {
    reviewingId.value = app.id;
    saveError.value = '';
    reviewSuccess.value = '';
    try {
        const res = await applicationsApi.review(app.id, 'accepted');
        reviewSuccess.value = res.application.enrollment
            ? `Pendaftaran diterima; ditempatkan di ${res.application.enrollment.cohort_name}.`
            : 'Pendaftaran diterima.';
        if (app.cohort_id) {
            await load();
        } else {
            offerEnroll(app); // legacy tanpa angkatan: tawarkan penempatan manual
        }
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        saveError.value = e.message ?? 'Gagal menyimpan.';
    } finally {
        reviewingId.value = null;
    }
}

const rejectTarget = ref(null); // application yang akan ditolak (dialog konfirmasi)

/** Peringatan absensi/penempatan untuk dialog tolak. */
function rejectWarning(app) {
    const enrollment = enrollmentFor(app);
    if (!enrollment) return '';
    if (enrollment.hadir > 0) {
        return `Dia sudah tercatat hadir di ${enrollment.cohort}. Penempatan dan catatan kehadirannya tidak ikut terhapus.`;
    }
    return `Penempatannya di ${enrollment.cohort} tidak ikut terhapus; kelola dari halaman Angkatan bila perlu.`;
}

async function confirmReject(note) {
    const app = rejectTarget.value;
    saveError.value = '';
    reviewSuccess.value = '';
    try {
        await applicationsApi.review(app.id, 'rejected', { review_note: note });
        rejectTarget.value = null;
        reviewSuccess.value = 'Pendaftaran ditolak.';
        await load();
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        rejectTarget.value = null;
        saveError.value = e.message ?? 'Gagal menyimpan.';
    }
}

// --- Masukkan ke Angkatan (fallback untuk lamaran legacy tanpa angkatan) ----

const enrollFor = ref(null); // application yang baru diterima

function offerEnroll(app) {
    enrollFor.value = app;
}

async function onEnrollClosed() {
    enrollFor.value = null;
    await load();
}

// --- Daftarkan langsung ke angkatan (tanpa lamaran) -------------------------

const enrollPersonFor = ref(null); // person row; non-null opens the dialog

async function onDirectEnrolled() {
    await load();
}

// --- Akun (participant account) actions -------------------------------------

const accountError = ref('');
const accountBusy = ref(false);
const resetDialogOpen = ref(false);
const resetPassword = ref('');
const resetErrors = ref({});
const generatedPassword = ref('');

async function toggleAccountActive() {
    accountBusy.value = true;
    accountError.value = '';
    try {
        const res = await peopleApi.updateAccount(person.value.id, { is_active: !person.value.account.is_active });
        person.value.account = res.account;
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        accountError.value = e.errors?.account?.[0] ?? e.message ?? 'Gagal mengubah status akun.';
    } finally {
        accountBusy.value = false;
    }
}

function openResetDialog() {
    resetPassword.value = '';
    resetErrors.value = {};
    generatedPassword.value = '';
    resetDialogOpen.value = true;
}

async function submitReset() {
    accountBusy.value = true;
    resetErrors.value = {};
    try {
        const payload = { reset_password: true };
        if (resetPassword.value) payload.password = resetPassword.value;
        const res = await peopleApi.updateAccount(person.value.id, payload);
        person.value.account = res.account;
        if (res.generated_password) {
            generatedPassword.value = res.generated_password;
        } else {
            resetDialogOpen.value = false;
        }
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        resetErrors.value = e.errors ?? {};
        if (!Object.keys(resetErrors.value).length) accountError.value = e.message;
    } finally {
        accountBusy.value = false;
    }
}
</script>

<template>
    <div>
        <RouterLink :to="{ name: 'people' }" class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
            <ArrowLeft class="size-4" /> Kembali ke daftar
        </RouterLink>

        <div v-if="loading" class="mt-10 text-center text-muted-foreground">Memuat…</div>

        <template v-else-if="person">
            <!-- Profile -->
            <div class="mt-4 rounded-xl border border-border bg-card p-6">
                <h1 class="text-2xl font-bold text-foreground">{{ person.name }}</h1>
                <div class="mt-4 grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
                    <div><span class="text-muted-foreground">Nomor HP</span><div class="font-medium">{{ person.phone }}</div></div>
                    <div><span class="text-muted-foreground">Email</span><div class="font-medium">{{ person.email }}</div></div>
                    <div><span class="text-muted-foreground">Domisili</span><div class="font-medium">{{ [person.city, person.province].filter(Boolean).join(', ') || '—' }}</div></div>
                    <div><span class="text-muted-foreground">Bergabung</span><div class="font-medium">{{ fmtDate(person.created_at) }}</div></div>
                    <div><span class="text-muted-foreground">TikTok</span><div class="font-medium">{{ person.tiktok_username || '—' }}</div></div>
                    <div><span class="text-muted-foreground">Instagram</span><div class="font-medium">{{ person.instagram_username || '—' }}</div></div>
                    <div><span class="text-muted-foreground">Usia</span><div class="font-medium">{{ person.age !== null ? `${person.age} tahun` : '—' }}</div></div>
                    <div><span class="text-muted-foreground">Jenis kelamin</span><div class="font-medium">{{ GENDER_LABELS[person.gender] ?? '—' }}</div></div>
                    <div><span class="text-muted-foreground">Followers TikTok</span><div class="font-medium">{{ person.tiktok_followers ?? '—' }}</div></div>
                    <div>
                        <span class="text-muted-foreground">Affiliate TikTok</span>
                        <div class="font-medium">
                            <template v-if="person.has_started_affiliate === true">
                                Sudah · Level {{ person.affiliate_level }} · {{ GMV_LABELS[person.affiliate_gmv_range] }}
                            </template>
                            <template v-else-if="person.has_started_affiliate === false">Belum mulai</template>
                            <template v-else>—</template>
                        </div>
                    </div>
                    <div>
                        <span class="text-muted-foreground">Follow sosmed</span>
                        <div class="font-medium">
                            <template v-if="person.followed_socials === true">Sudah</template>
                            <template v-else-if="person.followed_socials === false">Belum</template>
                            <template v-else>—</template>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Account -->
            <h2 class="mt-8 font-display text-xs uppercase tracking-[0.3em] text-orange-600">Akun</h2>
            <Alert v-if="accountError" class="mt-3">{{ accountError }}</Alert>
            <div class="mt-3 rounded-xl border border-border bg-card p-5">
                <div v-if="!person.account" class="text-sm text-muted-foreground">
                    Belum memiliki akun login. Akun dibuat saat orang ini bergabung komunitas atau mendaftar program.
                </div>
                <div v-else class="flex flex-wrap items-center justify-between gap-3 text-sm">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-foreground">{{ person.account.email }}</span>
                            <Badge :variant="person.account.is_active ? 'success' : 'destructive'">
                                {{ person.account.is_active ? 'Aktif' : 'Nonaktif' }}
                            </Badge>
                        </div>
                        <div class="mt-1 text-xs text-muted-foreground">Dibuat {{ fmtDate(person.account.created_at) }}</div>
                    </div>
                    <div v-if="auth.can('users.manage')" class="flex gap-2">
                        <Button variant="outline" size="sm" :disabled="accountBusy" @click="openResetDialog">Reset kata sandi</Button>
                        <Button variant="outline" size="sm" :disabled="accountBusy" @click="toggleAccountActive">
                            {{ person.account.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Applications (cross-attempt history) -->
            <h2 class="mt-8 font-display text-xs uppercase tracking-[0.3em] text-orange-600">Riwayat Pendaftaran</h2>
            <Alert v-if="saveError" class="mt-3">{{ saveError }}</Alert>
            <Alert v-if="reviewSuccess" variant="success" class="mt-3">{{ reviewSuccess }}</Alert>
            <div class="mt-3 overflow-hidden rounded-xl border border-border bg-card">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                            <th class="px-4 py-3 font-semibold">Program</th>
                            <th class="px-4 py-3 font-semibold">Angkatan</th>
                            <th class="px-4 py-3 font-semibold">Tanggal</th>
                            <th class="px-4 py-3 font-semibold">Keputusan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!person.applications.length">
                            <td colspan="4" class="px-4 py-8 text-center text-muted-foreground">Belum pernah mendaftar program.</td>
                        </tr>
                        <tr v-for="app in person.applications" :key="app.id" class="border-b border-border align-middle last:border-0">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-foreground">{{ app.program ?? 'Program tidak diketahui' }}</span>
                                    <Badge v-if="person.applications.length > 1" variant="secondary">ke-{{ app.attempt }}</Badge>
                                </div>
                                <p v-if="app.motivation" class="mt-1 max-w-md text-xs italic text-muted-foreground">"{{ app.motivation }}"</p>
                                <p v-if="app.status === 'rejected' && app.review_note" class="mt-1 max-w-md text-xs text-muted-foreground">
                                    Catatan penolakan: {{ app.review_note }}
                                </p>
                            </td>
                            <td class="px-4 py-3 text-muted-foreground">{{ app.cohort ?? '—' }}</td>
                            <td class="px-4 py-3 whitespace-nowrap text-muted-foreground">{{ fmtDate(app.created_at) }}</td>
                            <td class="px-4 py-3">
                                <ApplicationDecisionToggle
                                    v-if="auth.can('applications.review')"
                                    :status="app.status"
                                    :disabled="reviewingId === app.id"
                                    @decide="(v) => decide(app, v)"
                                />
                                <Badge v-else :variant="statusVariant(app.status)">{{ statusLabel(app.status) }}</Badge>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Enrollments -->
            <div class="mt-8 flex items-center justify-between gap-3">
                <h2 class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Keikutsertaan</h2>
                <Button v-if="auth.can('enrollments.manage')" variant="outline" size="sm" @click="enrollPersonFor = person">
                    Daftarkan ke Angkatan
                </Button>
            </div>
            <div class="mt-3 rounded-xl border border-border bg-card">
                <div v-if="!person.enrollments.length" class="px-5 py-6 text-sm text-muted-foreground">Belum pernah ditempatkan ke angkatan.</div>
                <div
                    v-for="e in person.enrollments"
                    :key="e.id"
                    class="flex items-center justify-between gap-3 border-b border-border px-5 py-3 text-sm last:border-0"
                >
                    <span class="font-medium text-foreground">{{ e.cohort ?? 'Angkatan dihapus' }}</span>
                    <span class="flex items-center gap-2">
                        <span v-if="e.latest_status === 'dropped'" class="text-xs text-destructive">Keluar</span>
                        <Badge :variant="e.hadir > 0 ? 'success' : 'secondary'">{{ e.hadir > 0 ? 'Hadir' : 'Belum hadir' }}</Badge>
                    </span>
                </div>
            </div>

            <!-- Enroll dialog (fallback penempatan untuk lamaran legacy tanpa angkatan) -->
            <EnrollToCohortDialog
                :application="enrollFor"
                :exclude-cohort-ids="(person?.enrollments ?? []).map((en) => en.cohort_id)"
                @close="onEnrollClosed"
                @enrolled="onEnrollClosed"
            />

            <EnrollPersonDialog
                :person="enrollPersonFor"
                :exclude-cohort-ids="(person?.enrollments ?? []).map((en) => en.cohort_id)"
                @close="enrollPersonFor = null"
                @enrolled="onDirectEnrolled"
            />

            <!-- Konfirmasi tolak pendaftaran (alasan opsional; peringatan bila sudah ditempatkan/hadir) -->
            <RejectApplicationDialog
                :target="rejectTarget"
                :person-name="person.name"
                :warning="rejectTarget ? rejectWarning(rejectTarget) : ''"
                :warning-muted="!(rejectTarget && enrollmentFor(rejectTarget)?.hadir > 0)"
                @close="rejectTarget = null"
                @confirm="confirmReject"
            />

            <!-- Reset PIN dialog (generated PIN is shown once). "PIN" is UI
                 vocabulary only — the payload still travels as `password`. -->
            <Dialog v-model:open="resetDialogOpen" title="Reset PIN">
                <div v-if="generatedPassword" class="space-y-3">
                    <p class="text-sm text-foreground">PIN direset. Catat dan sampaikan ke peserta, hanya ditampilkan sekali:</p>
                    <code class="block rounded-md border border-border bg-background px-3 py-2 text-center text-lg font-semibold tracking-[0.4em]">{{ generatedPassword }}</code>
                    <div class="flex justify-end">
                        <Button size="sm" @click="resetDialogOpen = false">Selesai</Button>
                    </div>
                </div>
                <form v-else class="space-y-3" @submit.prevent="submitReset">
                    <div>
                        <label class="text-xs text-muted-foreground">PIN baru (6 digit)</label>
                        <PinInput v-model="resetPassword" class="mt-1.5" />
                        <p class="mt-1.5 text-xs text-muted-foreground">Kosongkan untuk membuat PIN acak.</p>
                        <p v-if="resetErrors.password" class="mt-1 text-xs text-destructive">{{ resetErrors.password[0] }}</p>
                        <p v-if="resetErrors.account" class="mt-1 text-xs text-destructive">{{ resetErrors.account[0] }}</p>
                    </div>
                    <div class="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" size="sm" @click="resetDialogOpen = false">Batal</Button>
                        <Button type="submit" size="sm" :disabled="accountBusy">{{ accountBusy ? 'Menyimpan…' : 'Reset' }}</Button>
                    </div>
                </form>
            </Dialog>

        </template>

        <div v-else class="mt-16 text-center text-muted-foreground">{{ error || 'Data tidak ditemukan.' }}</div>
    </div>
</template>
