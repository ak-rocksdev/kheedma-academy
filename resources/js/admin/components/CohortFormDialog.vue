<script setup>
import { ref, computed, watch } from 'vue';
import { parseDate } from '@internationalized/date';
import { cohorts as cohortsApi, users as usersApi, programs as programsApi } from '@/api';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';
import { NativeSelect } from '@/components/ui/native-select';
import { Alert } from '@/components/ui/alert';
import { dateOnly, fmtDate, toDatetimeLocal } from '@/lib/format';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import LocationPicker from '@/components/LocationPicker.vue';

const TYPE_OPTIONS = [
    { value: 'offline', label: 'Offline (tatap muka)' },
    { value: 'online', label: 'Online' },
];

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

// start_date is a datetime-local string ('YYYY-MM-DDTHH:mm'); only the date
// part feeds the calendar-day arithmetic below.
const computedEndDate = computed(() => {
    if (!form.value.start_date) return '';
    return parseDate(dateOnly(form.value.start_date)).add({ days: durationDays.value - 1 }).toString();
});

/** ToggleGroup can deselect to empty; duration is mandatory, so ignore that. */
function setDuration(value) {
    if (value) duration.value = value;
}

/** ToggleGroup can deselect to empty; class type is mandatory, so ignore that. */
function setType(value) {
    if (value) form.value.type = value;
}

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
        start_date: toDatetimeLocal(props.cohort?.start_date),
        mentor_id: props.cohort?.mentor?.id ?? '',
        registration_opens_at: toDatetimeLocal(props.cohort?.registration_opens_at),
        registration_closes_at: toDatetimeLocal(props.cohort?.registration_closes_at),
        type: props.cohort?.type ?? 'offline',
        location: {
            name: props.cohort?.location_name ?? '',
            address: props.cohort?.location_address ?? '',
            lat: props.cohort?.location_lat ?? null,
            lng: props.cohort?.location_lng ?? null,
        },
        meeting_url: props.cohort?.meeting_url ?? '',
        materials_url: props.cohort?.materials_url ?? '',
    };

    // Recover the duration from the stored date pair (date-only comparison —
    // start_date carries a time-of-day, end_date doesn't).
    let days = 1;
    if (props.cohort?.start_date && props.cohort?.end_date) {
        const startDateOnly = new Date(dateOnly(props.cohort.start_date));
        const endDateOnly = new Date(dateOnly(props.cohort.end_date));
        days = Math.max(1, Math.round((endDateOnly - startDateOnly) / 86400000) + 1);
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
            type: form.value.type,
            location_name: form.value.location.name || null,
            location_address: form.value.location.address || null,
            location_lat: form.value.location.lat === '' ? null : form.value.location.lat ?? null,
            location_lng: form.value.location.lng === '' ? null : form.value.location.lng ?? null,
            meeting_url: form.value.meeting_url || null,
            materials_url: form.value.materials_url || null,
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
</script>

<template>
    <Dialog v-model:open="open" wide :title="isEditing ? 'Ubah Angkatan / Kelas' : 'Tambah Angkatan / Kelas'">
        <form @submit.prevent="save">
            <Alert v-if="optionsError" class="mb-3 px-3.5 py-2.5">{{ optionsError }}</Alert>
            <!-- Two columns on desktop (schedule | logistics); single column
                 on small screens. The form has grown too tall for one column. -->
            <div class="md:grid md:grid-cols-2 md:gap-x-8">
            <div class="space-y-3">
            <div>
                <label class="text-xs text-muted-foreground">Nama angkatan / kelas</label>
                <Input v-model="form.name" placeholder="Nama angkatan / kelas" class="mt-1.5" />
                <p v-if="formErrors.name" class="mt-1 text-xs text-destructive">{{ formErrors.name[0] }}</p>
            </div>
            <div>
                <label class="text-xs text-muted-foreground">Mulai Kelas</label>
                <Input v-model="form.start_date" type="datetime-local" class="mt-1.5" />
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
                    <Input v-model="form.registration_opens_at" type="datetime-local" class="mt-1.5" />
                </div>
                <div class="min-w-0 flex-1">
                    <label class="text-xs text-muted-foreground">Pendaftaran ditutup</label>
                    <Input v-model="form.registration_closes_at" type="datetime-local" class="mt-1.5" />
                </div>
            </div>
            <p v-if="formErrors.registration_closes_at" class="text-xs text-destructive">{{ formErrors.registration_closes_at[0] }}</p>
            </div>
            <div class="mt-3 space-y-3 md:mt-0">
            <div>
                <label class="text-xs text-muted-foreground">Tipe kelas</label>
                <ToggleGroup
                    type="single"
                    variant="outline"
                    class="mt-1.5 w-full"
                    :model-value="form.type"
                    @update:model-value="setType"
                >
                    <ToggleGroupItem v-for="option in TYPE_OPTIONS" :key="option.value" :value="option.value" class="flex-1">
                        {{ option.label }}
                    </ToggleGroupItem>
                </ToggleGroup>
                <p v-if="formErrors.type" class="mt-1 text-xs text-destructive">{{ formErrors.type[0] }}</p>
            </div>
            <div v-if="form.type === 'offline'">
                <label class="text-xs text-muted-foreground">Lokasi kelas</label>
                <LocationPicker v-model="form.location" class="mt-1.5" />
                <p v-if="formErrors.location_address" class="mt-1 text-xs text-destructive">{{ formErrors.location_address[0] }}</p>
                <p v-if="formErrors.location_lat" class="mt-1 text-xs text-destructive">{{ formErrors.location_lat[0] }}</p>
                <p v-if="formErrors.location_lng" class="mt-1 text-xs text-destructive">{{ formErrors.location_lng[0] }}</p>
            </div>
            <div v-else>
                <label class="text-xs text-muted-foreground">Link meeting (Google Meet / Zoom)</label>
                <Input v-model="form.meeting_url" placeholder="https://meet.google.com/…" class="mt-1.5" />
                <p class="mt-1 text-xs text-muted-foreground">Opsional. Bisa kamu isi atau ubah kapan saja.</p>
                <p v-if="formErrors.meeting_url" class="mt-1 text-xs text-destructive">{{ formErrors.meeting_url[0] }}</p>
            </div>
            <div>
                <label class="text-xs text-muted-foreground">Link materi (Google Drive)</label>
                <Input v-model="form.materials_url" placeholder="https://drive.google.com/…" class="mt-1.5" />
                <p class="mt-1 text-xs text-muted-foreground">Opsional. Hanya terlihat oleh peserta yang terdaftar.</p>
                <p v-if="formErrors.materials_url" class="mt-1 text-xs text-destructive">{{ formErrors.materials_url[0] }}</p>
            </div>
            <div>
                <label class="text-xs text-muted-foreground">Program</label>
                <p v-if="lockedProgram" class="mt-1.5 rounded-md border border-border bg-accent/40 px-3 py-2 text-sm font-medium text-foreground">
                    {{ lockedProgram.name }}
                </p>
                <template v-else>
                    <NativeSelect v-model="form.program_id" class="mt-1.5">
                        <option value="">Pilih program…</option>
                        <option v-for="program in programs ?? []" :key="program.id" :value="program.id">{{ program.name }}</option>
                    </NativeSelect>
                </template>
                <p v-if="formErrors.program_id" class="mt-1 text-xs text-destructive">{{ formErrors.program_id[0] }}</p>
            </div>
            <div>
                <label class="text-xs text-muted-foreground">Mentor</label>
                <NativeSelect v-model="form.mentor_id" class="mt-1.5">
                    <option value="">Tanpa mentor</option>
                    <option v-for="mentor in mentors ?? []" :key="mentor.id" :value="mentor.id">{{ mentor.name }}</option>
                </NativeSelect>
                <p v-if="formErrors.mentor_id" class="mt-1 text-xs text-destructive">{{ formErrors.mentor_id[0] }}</p>
            </div>
            </div>
            </div>
            <div class="mt-4 flex justify-end gap-2 border-t border-border pt-4">
                <Button type="button" variant="outline" size="sm" @click="open = false">Batal</Button>
                <Button type="submit" size="sm" :disabled="saving">{{ saving ? 'Menyimpan…' : 'Simpan' }}</Button>
            </div>
        </form>
    </Dialog>
</template>
