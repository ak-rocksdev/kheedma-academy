<script setup>
import { ref, onMounted, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { ArrowLeft, Check, ChevronDown, X } from 'lucide-vue-next';
import { api, applications as applicationsApi, people as peopleApi } from '@/api';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Dialog } from '@/components/ui/dialog';
import { PasswordInput } from '@/components/ui/password-input';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useAuthStore } from '@/stores/auth';
import { statusVariant, statusLabel } from '@/lib/status';
import EnrollToCohortDialog from '@/components/EnrollToCohortDialog.vue';

const GMV_LABELS = { '0-50': '0-50 Juta', '50-100': '50-100 Juta', '100+': 'Di atas 100 Juta' };
const GENDER_LABELS = { male: 'Laki-laki', female: 'Perempuan' };

const props = defineProps({ id: { type: [String, Number], required: true } });

const auth = useAuthStore();

const person = ref(null);
const loading = ref(true);

// Rincian per-kelas pada kartu keikutsertaan (expand/collapse per enrollment).
const expandedClasses = ref(new Set());

function toggleClasses(enrollmentId) {
    const next = new Set(expandedClasses.value);
    next.has(enrollmentId) ? next.delete(enrollmentId) : next.add(enrollmentId);
    expandedClasses.value = next;
}
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

/**
 * Keputusan dua-pilihan langsung tersimpan: Diterima atau Ditolak. "Menunggu"
 * bukan pilihan (aturan satu arah); memilih status yang sama diabaikan.
 */
function decide(app, value) {
    if (!value || value === app.status) return;
    if (value === 'accepted') {
        accept(app);
    } else if (value === 'rejected') {
        rejectNote.value = '';
        rejectTarget.value = app;
    }
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
const rejectNote = ref('');

async function confirmReject() {
    const app = rejectTarget.value;
    saveError.value = '';
    reviewSuccess.value = '';
    try {
        await applicationsApi.review(app.id, 'rejected', { review_note: rejectNote.value || null });
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

function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
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
            <div v-if="accountError" class="mt-3 rounded-lg border border-destructive/30 bg-red-50 px-4 py-3 text-sm text-destructive">
                {{ accountError }}
            </div>
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
            <div v-if="saveError" class="mt-3 rounded-lg border border-destructive/30 bg-red-50 px-4 py-3 text-sm text-destructive">
                {{ saveError }}
            </div>
            <div v-if="reviewSuccess" class="mt-3 rounded-lg border border-teal-600/30 bg-teal-50 px-4 py-3 text-sm text-teal-700">
                {{ reviewSuccess }}
            </div>
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
                                <div v-if="auth.can('applications.review')" class="flex items-center gap-2">
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

            <!-- Enrollments -->
            <h2 class="mt-8 font-display text-xs uppercase tracking-[0.3em] text-orange-600">Keikutsertaan</h2>
            <div class="mt-3 rounded-xl border border-border bg-card">
                <div v-if="!person.enrollments.length" class="px-5 py-6 text-sm text-muted-foreground">Belum pernah ditempatkan ke angkatan.</div>
                <div v-for="e in person.enrollments" :key="e.id" class="border-b border-border last:border-0">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-3 px-5 py-3 text-left text-sm transition hover:bg-accent/50"
                        @click="toggleClasses(e.id)"
                    >
                        <span class="font-medium text-foreground">{{ e.cohort ?? 'Angkatan dihapus' }}</span>
                        <span class="flex items-center gap-2 text-muted-foreground">
                            Hadir {{ e.hadir }} dari {{ e.classes.length }} kelas
                            <span v-if="e.latest_status === 'dropped'" class="text-destructive">· keluar</span>
                            <ChevronDown class="size-4 transition-transform" :class="expandedClasses.has(e.id) ? 'rotate-180' : ''" />
                        </span>
                    </button>
                    <!-- Rincian per-kelas: pernah diikuti atau tidak. -->
                    <div v-if="expandedClasses.has(e.id)" class="border-t border-border/60 bg-accent/20 px-5 py-2">
                        <p v-if="!e.classes.length" class="py-2 text-xs text-muted-foreground">Angkatan ini belum punya kelas.</p>
                        <div
                            v-for="c in e.classes"
                            :key="c.id"
                            class="flex items-center justify-between gap-3 border-b border-border/40 py-2 text-sm last:border-0"
                            :class="c.attended ? '' : 'opacity-60'"
                        >
                            <span class="flex items-center gap-2">
                                <span
                                    class="flex size-4 items-center justify-center rounded-full"
                                    :class="c.attended ? 'bg-teal-600 text-white' : 'border-2 border-border'"
                                >
                                    <Check v-if="c.attended" class="size-3" />
                                </span>
                                <span class="text-foreground">{{ c.title }}</span>
                            </span>
                            <span class="text-xs text-muted-foreground">
                                {{ fmtDate(c.scheduled_at) }}{{ c.attended ? ` · hadir, dicatat ${fmtDate(c.attended_at)}` : ' · tidak diikuti' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Enroll dialog (fallback penempatan untuk lamaran legacy tanpa angkatan) -->
            <EnrollToCohortDialog
                :application="enrollFor"
                :exclude-cohort-ids="(person?.enrollments ?? []).map((en) => en.cohort_id)"
                @close="onEnrollClosed"
                @enrolled="onEnrollClosed"
            />

            <!-- Konfirmasi tolak pendaftaran (alasan opsional; peringatan bila sudah ditempatkan/hadir) -->
            <Dialog :open="rejectTarget !== null" title="Tolak Pendaftaran" @update:open="rejectTarget = null">
                <p class="text-sm text-muted-foreground">
                    Tolak pendaftaran {{ person.name }} untuk {{ rejectTarget?.program ?? 'program ini' }}? Dia masih boleh mendaftar lagi di lain waktu.
                </p>
                <p v-if="enrollmentFor(rejectTarget)?.hadir" class="mt-2 text-sm text-orange-700">
                    Dia sudah tercatat hadir {{ enrollmentFor(rejectTarget).hadir }} kali di {{ enrollmentFor(rejectTarget).cohort }}. Penempatan dan riwayat kehadirannya tidak ikut terhapus.
                </p>
                <p v-else-if="enrollmentFor(rejectTarget)" class="mt-2 text-sm text-muted-foreground">
                    Penempatannya di {{ enrollmentFor(rejectTarget).cohort }} tidak ikut terhapus; kelola dari halaman Angkatan bila perlu.
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

            <!-- Reset password dialog (generated password is shown once) -->
            <Dialog v-model:open="resetDialogOpen" title="Reset Kata Sandi">
                <div v-if="generatedPassword" class="space-y-3">
                    <p class="text-sm text-foreground">Kata sandi direset. Catat, hanya ditampilkan sekali:</p>
                    <code class="block rounded-md border border-border bg-background px-3 py-2 text-sm">{{ generatedPassword }}</code>
                    <div class="flex justify-end">
                        <Button size="sm" @click="resetDialogOpen = false">Selesai</Button>
                    </div>
                </div>
                <form v-else class="space-y-3" @submit.prevent="submitReset">
                    <div>
                        <PasswordInput v-model="resetPassword" autocomplete="new-password" placeholder="Kata sandi baru (kosongkan untuk generate)" />
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
