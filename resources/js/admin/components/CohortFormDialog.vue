<script setup>
import { ref, computed, watch } from 'vue';
import { parseDate } from '@internationalized/date';
import { cohorts as cohortsApi, users as usersApi, programs as programsApi } from '@/api';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';
import { DatePicker } from '@/components/ui/date-picker';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

const props = defineProps({
    /** Cohort row (API shape) to edit; null opens the dialog in create mode. */
    cohort: { type: Object, default: null },
    /** {id, name}: hides the program select and pins the payload to this program. */
    lockedProgram: { type: Object, default: null },
});

const open = defineModel('open', { type: Boolean, default: false });

const emit = defineEmits(['saved']);

const isEditing = computed(() => props.cohort !== null);

const form = ref({});
const formErrors = ref({});
const saving = ref(false);

// Dialog-only option lists, fetched once on first open and cached.
const mentors = ref(null);
const programs = ref(null);
const optionsError = ref('');

// The end date is never typed: it derives from the start date + a duration in
// days (1 day = same-day class). "custom" opens a manual day-count input.
const DURATION_OPTIONS = ['1', '2', '3'];
const duration = ref('1');
const customDays = ref(4);

const durationDays = computed(() => {
    if (duration.value === 'custom') {
        return Math.max(1, parseInt(customDays.value, 10) || 1);
    }
    return Number(duration.value);
});

const computedEndDate = computed(() => {
    if (!form.value.start_date) return '';
    return parseDate(form.value.start_date).add({ days: durationDays.value - 1 }).toString();
});

/** ToggleGroup can deselect to empty; duration is mandatory, so ignore that. */
function setDuration(value) {
    if (value) duration.value = value;
}

const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

async function loadOptions() {
    optionsError.value = '';
    try {
        if (mentors.value === null) {
            mentors.value = (await usersApi.list('?role=mentor')).data;
        }
        if (programs.value === null && !props.lockedProgram) {
            programs.value = (await programsApi.list()).data;
        }
    } catch (e) {
        if (!e.sessionExpired) optionsError.value = e.message ?? 'Gagal memuat pilihan.';
    }
}

// Every open re-seeds the form from the prop so a reopened dialog never shows
// stale values from the previous session.
watch(open, (isOpen) => {
    if (!isOpen) return;
    loadOptions();
    form.value = {
        name: props.cohort?.name ?? '',
        program_id: props.lockedProgram?.id ?? props.cohort?.program?.id ?? '',
        start_date: props.cohort?.start_date ?? '',
        mentor_id: props.cohort?.mentor?.id ?? '',
        registration_opens_at: props.cohort?.registration_opens_at?.slice(0, 10) ?? '',
        registration_closes_at: props.cohort?.registration_closes_at?.slice(0, 10) ?? '',
    };

    // Recover the duration from the stored date pair.
    let days = 1;
    if (props.cohort?.start_date && props.cohort?.end_date) {
        days = Math.max(1, Math.round((new Date(props.cohort.end_date) - new Date(props.cohort.start_date)) / 86400000) + 1);
    }
    duration.value = days <= 3 ? String(days) : 'custom';
    customDays.value = days > 3 ? days : 4;

    formErrors.value = {};
});

async function save() {
    saving.value = true;
    formErrors.value = {};
    try {
        const payload = {
            name: form.value.name,
            program_id: props.lockedProgram?.id ?? form.value.program_id ?? null,
            start_date: form.value.start_date || null,
            end_date: computedEndDate.value || null,
            mentor_id: form.value.mentor_id || null,
            registration_opens_at: form.value.registration_opens_at || null,
            registration_closes_at: form.value.registration_closes_at || null,
        };
        if (payload.program_id === '') payload.program_id = null;
        const res = isEditing.value
            ? await cohortsApi.update(props.cohort.id, payload)
            : await cohortsApi.create(payload);
        open.value = false;
        emit('saved', res.cohort);
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        formErrors.value = e.errors ?? {};
        if (!Object.keys(formErrors.value).length) formErrors.value = { name: [e.message ?? 'Gagal menyimpan.'] };
    } finally {
        saving.value = false;
    }
}

function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

<template>
    <Dialog v-model:open="open" :title="isEditing ? 'Ubah Angkatan' : 'Tambah Angkatan'">
        <form class="space-y-3" @submit.prevent="save">
            <div v-if="optionsError" class="rounded-lg border border-destructive/30 bg-red-50 px-3.5 py-2.5 text-sm text-destructive">
                {{ optionsError }}
            </div>
            <div>
                <Input v-model="form.name" placeholder="Nama angkatan" />
                <p v-if="formErrors.name" class="mt-1 text-xs text-destructive">{{ formErrors.name[0] }}</p>
            </div>
            <div>
                <label class="text-xs text-muted-foreground">Mulai Kelas</label>
                <DatePicker v-model="form.start_date" class="mt-1.5" placeholder="Pilih tanggal" />
                <p v-if="formErrors.start_date" class="mt-1 text-xs text-destructive">{{ formErrors.start_date[0] }}</p>
            </div>
            <div>
                <label class="text-xs text-muted-foreground">Durasi kelas</label>
                <ToggleGroup
                    type="single"
                    variant="outline"
                    class="mt-1.5 w-full"
                    :model-value="duration"
                    @update:model-value="setDuration"
                >
                    <ToggleGroupItem v-for="option in DURATION_OPTIONS" :key="option" :value="option" class="flex-1">
                        {{ option }} hari
                    </ToggleGroupItem>
                    <ToggleGroupItem value="custom" class="flex-1">Custom</ToggleGroupItem>
                </ToggleGroup>
                <div v-if="duration === 'custom'" class="mt-2 flex items-center gap-2">
                    <Input v-model="customDays" type="number" min="1" class="w-24" />
                    <span class="text-xs text-muted-foreground">hari</span>
                </div>
                <p v-if="computedEndDate" class="mt-1.5 text-xs text-muted-foreground">Selesai: {{ fmtDate(computedEndDate) }}</p>
                <p v-if="formErrors.end_date" class="mt-1 text-xs text-destructive">{{ formErrors.end_date[0] }}</p>
            </div>
            <div class="flex gap-3">
                <div class="min-w-0 flex-1">
                    <label class="text-xs text-muted-foreground">Pendaftaran dibuka</label>
                    <DatePicker v-model="form.registration_opens_at" class="mt-1.5" placeholder="Pilih tanggal" />
                </div>
                <div class="min-w-0 flex-1">
                    <label class="text-xs text-muted-foreground">Pendaftaran ditutup</label>
                    <DatePicker v-model="form.registration_closes_at" class="mt-1.5" placeholder="Pilih tanggal" />
                </div>
            </div>
            <p v-if="formErrors.registration_closes_at" class="text-xs text-destructive">{{ formErrors.registration_closes_at[0] }}</p>
            <div>
                <label class="text-xs text-muted-foreground">Program</label>
                <p v-if="lockedProgram" class="mt-1.5 rounded-md border border-border bg-accent/40 px-3 py-2 text-sm font-medium text-foreground">
                    {{ lockedProgram.name }}
                </p>
                <template v-else>
                    <select v-model="form.program_id" :class="[selectClass, 'mt-1.5']">
                        <option value="">Pilih program…</option>
                        <option v-for="program in programs ?? []" :key="program.id" :value="program.id">{{ program.name }}</option>
                    </select>
                </template>
                <p v-if="formErrors.program_id" class="mt-1 text-xs text-destructive">{{ formErrors.program_id[0] }}</p>
            </div>
            <div>
                <label class="text-xs text-muted-foreground">Mentor</label>
                <select v-model="form.mentor_id" :class="[selectClass, 'mt-1.5']">
                    <option value="">Tanpa mentor</option>
                    <option v-for="mentor in mentors ?? []" :key="mentor.id" :value="mentor.id">{{ mentor.name }}</option>
                </select>
                <p v-if="formErrors.mentor_id" class="mt-1 text-xs text-destructive">{{ formErrors.mentor_id[0] }}</p>
            </div>
            <div class="flex justify-end gap-2 pt-2">
                <Button type="button" variant="outline" size="sm" @click="open = false">Batal</Button>
                <Button type="submit" size="sm" :disabled="saving">{{ saving ? 'Menyimpan…' : 'Simpan' }}</Button>
            </div>
        </form>
    </Dialog>
</template>
