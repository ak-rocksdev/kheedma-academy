<script setup>
import { ref, computed, watch } from 'vue';
import { ExternalLink, ClipboardList } from 'lucide-vue-next';
import { submissions as submissionsApi } from '@/api';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';
import { Alert } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { fmtDateTime } from '@/lib/format';

const props = defineProps({
    /** {assignment, enrollmentId, personName} — null closes the dialog. */
    target: { type: Object, default: null },
});

const emit = defineEmits(['close', 'graded']);

// The newest row is the one being graded; older rows are context only.
const latest = computed(() => history.value[0] ?? null);
const older = computed(() => history.value.slice(1));

const loading = ref(false);
const error = ref('');
const history = ref([]);

// Grade form always aims at the NEWEST row shown (by id, race-safe).
const score = ref('');
const feedback = ref('');
const saving = ref(false);
const formErrors = ref({});

watch(() => props.target, async (target) => {
    if (!target) return;
    loading.value = true;
    error.value = '';
    history.value = [];
    score.value = '';
    feedback.value = '';
    formErrors.value = {};
    try {
        const res = await submissionsApi.history(target.assignment.id, target.enrollmentId);
        if (props.target !== target) return; // stale response: a newer target took over
        history.value = res.submissions;
        const latest = res.submissions[0];
        if (latest !== undefined && latest.score !== null) {
            score.value = latest.score;
            feedback.value = latest.feedback ?? '';
        }
    } catch (e) {
        if (props.target === target && !e.sessionExpired) error.value = e.message ?? 'Gagal memuat riwayat.';
    } finally {
        if (props.target === target) loading.value = false;
    }
});

async function saveGrade() {
    const latest = history.value[0];
    if (!latest) return;
    saving.value = true;
    formErrors.value = {};
    try {
        await submissionsApi.grade(latest.id, {
            score: score.value === '' ? null : Number(score.value),
            feedback: feedback.value || null,
        });
        emit('graded');
        emit('close');
    } catch (e) {
        if (e.sessionExpired) return;
        formErrors.value = e.errors ?? {};
        if (!Object.keys(formErrors.value).length) error.value = e.message ?? 'Gagal menyimpan nilai.';
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog :open="target !== null" :title="`Nilai Tugas · ${target?.personName ?? ''}`" @update:open="emit('close')">
        <p class="-mt-1 text-xs text-muted-foreground">{{ target?.assignment?.title }}</p>

        <Alert v-if="error" class="mt-3 px-3.5 py-2.5">{{ error }}</Alert>
        <div v-if="loading" class="py-10 text-center text-sm text-muted-foreground">Memuat…</div>

        <template v-else>
            <div v-if="!history.length" class="flex flex-col items-center py-10 text-center">
                <span class="flex size-12 items-center justify-center rounded-2xl bg-gradient-to-br from-teal-100 via-sand-50 to-orange-200/70 ring-1 ring-inset ring-teal-900/10">
                    <ClipboardList class="size-6 text-teal-700" />
                </span>
                <p class="mt-3 text-sm font-semibold text-foreground">Belum ada jawaban</p>
                <p class="mt-1 text-sm text-muted-foreground">Peserta ini belum mengirim link jawabannya.</p>
            </div>

            <template v-else>
                <!-- Focus zone: the submission being graded, with the grade
                     form attached to it - one panel, one decision. -->
                <div class="mt-3 rounded-2xl border border-teal-600/25 bg-sand-50/60 p-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-muted-foreground">
                            Kiriman {{ history.length }} · terbaru
                        </p>
                        <Badge v-if="latest.score !== null" variant="success">Sudah dinilai · {{ latest.score }}</Badge>
                        <Badge v-else variant="secondary" class="bg-orange-100 text-orange-800">Menunggu dinilai</Badge>
                    </div>

                    <a :href="latest.url" target="_blank" rel="noopener"
                       class="mt-2.5 flex items-center justify-between gap-3 rounded-xl border border-border bg-card px-3.5 py-2.5 text-sm font-medium text-teal-700 transition hover:border-teal-600/50 hover:bg-accent">
                        <span class="truncate">{{ latest.url }}</span>
                        <span class="flex shrink-0 items-center gap-1 text-xs font-semibold"><ExternalLink class="size-3.5" /> Buka link</span>
                    </a>
                    <p v-if="latest.note" class="mt-2 border-l-2 border-teal-600/40 pl-3 text-sm italic text-muted-foreground">"{{ latest.note }}"</p>
                    <p class="mt-2 text-xs text-muted-foreground">Dikirim {{ fmtDateTime(latest.created_at) }}<template v-if="latest.graded_by"> · dinilai {{ latest.graded_by }}</template></p>

                    <form class="mt-4 border-t border-teal-900/10 pt-4" @submit.prevent="saveGrade">
                        <div class="flex flex-wrap items-end gap-4">
                            <div>
                                <label class="text-xs text-muted-foreground">Nilai (0-100)</label>
                                <Input v-model="score" type="number" min="0" max="100" class="mt-1.5 h-12 w-24 text-center !text-xl font-bold" />
                            </div>
                            <p class="pb-1 text-xs leading-relaxed text-muted-foreground">
                                Nilai ini yang dihitung ke rata-rata peserta<br>sampai ada kiriman baru yang dinilai.
                            </p>
                        </div>
                        <p v-if="formErrors.score" class="mt-1.5 text-xs text-destructive">{{ formErrors.score[0] }}</p>

                        <label class="mt-3 block text-xs text-muted-foreground">Feedback untuk peserta (opsional)</label>
                        <Textarea v-model="feedback" rows="3" class="mt-1.5 bg-card" placeholder="Tulis masukanmu di sini." />
                        <p v-if="formErrors.feedback" class="mt-1 text-xs text-destructive">{{ formErrors.feedback[0] }}</p>

                        <div class="mt-4 flex justify-end gap-2">
                            <Button type="button" variant="outline" size="sm" @click="emit('close')">Batal</Button>
                            <Button type="submit" size="sm" :disabled="saving">
                                {{ saving ? 'Menyimpan…' : (latest.score !== null ? 'Perbarui nilai' : 'Simpan nilai') }}
                            </Button>
                        </div>
                    </form>
                </div>

                <!-- Older versions: context, deliberately quiet. -->
                <div v-if="older.length" class="mt-4">
                    <p class="text-[0.65rem] font-semibold uppercase tracking-[0.14em] text-muted-foreground">Riwayat sebelumnya</p>
                    <ul class="mt-2 max-h-40 space-y-1.5 overflow-y-auto">
                        <li v-for="(s, i) in older" :key="s.id" class="flex items-center justify-between gap-3 rounded-lg border border-border/70 px-3 py-2 text-xs">
                            <a :href="s.url" target="_blank" rel="noopener" class="inline-flex min-w-0 items-center gap-1.5 font-medium text-teal-700 hover:underline">
                                <ExternalLink class="size-3 shrink-0" /><span class="truncate">{{ s.url }}</span>
                            </a>
                            <span class="flex shrink-0 items-center gap-2 text-muted-foreground">
                                {{ fmtDateTime(s.created_at) }}
                                <Badge v-if="s.score !== null" variant="secondary">{{ s.score }}</Badge>
                                <span v-else class="text-muted-foreground">tanpa nilai</span>
                            </span>
                        </li>
                    </ul>
                </div>
            </template>
        </template>
    </Dialog>
</template>
