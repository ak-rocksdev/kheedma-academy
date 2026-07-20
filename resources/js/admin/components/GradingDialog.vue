<script setup>
import { ref, watch } from 'vue';
import { ExternalLink } from 'lucide-vue-next';
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
        <Alert v-if="error" class="mb-3 px-3.5 py-2.5">{{ error }}</Alert>
        <div v-if="loading" class="py-8 text-center text-sm text-muted-foreground">Memuat…</div>

        <template v-else>
            <p v-if="!history.length" class="py-6 text-center text-sm text-muted-foreground">
                Peserta ini belum mengirim jawaban.
            </p>

            <template v-else>
                <!-- History, newest first; older versions compact. -->
                <ul class="max-h-56 space-y-2 overflow-y-auto">
                    <li v-for="(s, i) in history" :key="s.id" class="rounded-lg border border-border px-3 py-2 text-sm" :class="i === 0 ? '' : 'opacity-70'">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <a :href="s.url" target="_blank" rel="noopener" class="inline-flex min-w-0 items-center gap-1 font-medium text-teal-700 hover:underline">
                                <ExternalLink class="size-3.5 shrink-0" /><span class="truncate">{{ s.url }}</span>
                            </a>
                            <Badge v-if="s.score !== null" variant="secondary">{{ s.score }}</Badge>
                            <Badge v-else-if="i === 0" variant="secondary" class="bg-orange-100 text-orange-700">menunggu dinilai</Badge>
                        </div>
                        <p v-if="s.note" class="mt-1 text-xs text-muted-foreground">"{{ s.note }}"</p>
                        <p class="mt-1 text-xs text-muted-foreground/70">
                            Kiriman {{ history.length - i }} · {{ fmtDateTime(s.created_at) }}
                            <template v-if="s.graded_by"> · dinilai {{ s.graded_by }}</template>
                        </p>
                    </li>
                </ul>

                <!-- Grade form targets history[0] (the newest row) by id. -->
                <form class="mt-4 space-y-3 border-t border-border pt-4" @submit.prevent="saveGrade">
                    <div class="flex gap-3">
                        <div class="w-28">
                            <label class="text-xs text-muted-foreground">Nilai (0-100)</label>
                            <Input v-model="score" type="number" min="0" max="100" class="mt-1.5" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <label class="text-xs text-muted-foreground">Feedback untuk peserta (opsional)</label>
                            <Textarea v-model="feedback" rows="2" class="mt-1.5" placeholder="Tulis masukanmu di sini." />
                        </div>
                    </div>
                    <p v-if="formErrors.score" class="text-xs text-destructive">{{ formErrors.score[0] }}</p>
                    <p v-if="formErrors.feedback" class="text-xs text-destructive">{{ formErrors.feedback[0] }}</p>
                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" size="sm" @click="emit('close')">Batal</Button>
                        <Button type="submit" size="sm" :disabled="saving">{{ saving ? 'Menyimpan…' : 'Simpan nilai' }}</Button>
                    </div>
                </form>
            </template>
        </template>
    </Dialog>
</template>
