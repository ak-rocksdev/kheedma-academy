<script setup>
import { ref, onMounted, watch } from 'vue';
import { RouterLink } from 'vue-router';
import { ArrowLeft } from 'lucide-vue-next';
import { api } from '@/api';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { APPLICATION_STATUSES, PREFILTER_VERDICTS, statusVariant, statusLabel } from '@/lib/status';

const props = defineProps({ id: { type: [String, Number], required: true } });

const person = ref(null);
const loading = ref(true);
const error = ref('');
const savingId = ref(null);
const saveError = ref('');

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

async function save(app) {
    savingId.value = app.id;
    saveError.value = '';
    try {
        await api(`/admin/applications/${app.id}`, {
            method: 'PATCH',
            body: { status: app.status, prefilter_verdict: app.prefilter_verdict || null },
        });
        // The in-place v-model values already reflect the saved state, so no full
        // reload is needed (avoids the spinner blink / scroll jump).
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        saveError.value = e.message ?? 'Gagal menyimpan.';
        await load(); // reset selects to the server's truth
    } finally {
        savingId.value = null;
    }
}

function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

const selectClass =
    'h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
</script>

<template>
    <div class="mx-auto max-w-4xl">
        <RouterLink :to="{ name: 'applicants' }" class="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground">
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
                </div>
            </div>

            <!-- Applications (cross-attempt history) -->
            <h2 class="mt-8 font-display text-xs uppercase tracking-[0.3em] text-orange-600">Riwayat Pendaftaran</h2>
            <div v-if="saveError" class="mt-3 rounded-lg border border-destructive/30 bg-red-50 px-4 py-3 text-sm text-destructive">
                {{ saveError }}
            </div>
            <div class="mt-3 space-y-3">
                <div v-for="app in person.applications" :key="app.id" class="rounded-xl border border-border bg-card p-5">
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0 text-sm">
                            <span class="font-medium text-foreground">{{ app.program ?? 'Program tidak diketahui' }}</span>
                            <span class="text-muted-foreground"> · Daftar {{ fmtDate(app.created_at) }}</span>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <Badge v-if="person.applications.length > 1" variant="secondary">Pendaftaran ke-{{ app.attempt }}</Badge>
                            <Badge :variant="statusVariant(app.status)">{{ statusLabel(app.status) }}</Badge>
                        </div>
                    </div>
                    <div class="mt-4 flex flex-wrap items-end gap-3">
                        <label class="text-sm">
                            <span class="text-muted-foreground">Status</span>
                            <select v-model="app.status" :class="selectClass" class="mt-1 block">
                                <option v-for="s in APPLICATION_STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
                            </select>
                        </label>
                        <label class="text-sm">
                            <span class="text-muted-foreground">Verdict tugas</span>
                            <select v-model="app.prefilter_verdict" :class="selectClass" class="mt-1 block">
                                <option v-for="v in PREFILTER_VERDICTS" :key="v.value" :value="v.value">{{ v.label }}</option>
                            </select>
                        </label>
                        <Button size="sm" :disabled="savingId === app.id" @click="save(app)">
                            {{ savingId === app.id ? 'Menyimpan…' : 'Simpan' }}
                        </Button>
                    </div>
                    <p v-if="app.prefilter_link" class="mt-3 text-sm">
                        <span class="text-muted-foreground">Link tugas:</span>
                        <a :href="app.prefilter_link" target="_blank" rel="noopener" class="text-primary underline">{{ app.prefilter_link }}</a>
                    </p>
                </div>
            </div>

            <!-- Enrollments -->
            <h2 class="mt-8 font-display text-xs uppercase tracking-[0.3em] text-orange-600">Keikutsertaan</h2>
            <div class="mt-3 rounded-xl border border-border bg-card">
                <div v-if="!person.enrollments.length" class="px-5 py-6 text-sm text-muted-foreground">Belum pernah diterima di cohort.</div>
                <div
                    v-for="e in person.enrollments"
                    :key="e.id"
                    class="flex items-center justify-between border-b border-border px-5 py-3 text-sm last:border-0"
                >
                    <span class="font-medium text-foreground">{{ e.cohort ?? 'Angkatan dihapus' }}</span>
                    <span class="text-muted-foreground">{{ e.latest_status ? `${e.latest_status} · ${fmtDate(e.latest_status_at)}` : 'Belum ada status' }}</span>
                </div>
            </div>
        </template>

        <div v-else class="mt-16 text-center text-muted-foreground">{{ error || 'Data tidak ditemukan.' }}</div>
    </div>
</template>
