<script setup>
import { ref, computed, watch } from 'vue';
import { sessions as sessionsApi } from '@/api';
import { Input } from '@/components/ui/input';
import { DateTimePicker } from '@/components/ui/date-picker';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';
import { Alert } from '@/components/ui/alert';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import LocationPicker from '@/components/LocationPicker.vue';
import { toDatetimeLocal } from '@/lib/format';

const TYPE_OPTIONS = [
    { value: 'offline', label: 'Offline (tatap muka)' },
    { value: 'online', label: 'Online' },
];

const props = defineProps({
    cohortId: { type: [String, Number], required: true },
    /** Session row (API shape) to edit; null opens the dialog in create mode. */
    session: { type: Object, default: null },
});

const open = defineModel('open', { type: Boolean, default: false });
const emit = defineEmits(['saved']);

const isEditing = computed(() => props.session !== null);

const form = ref({});
const formErrors = ref({});
const saving = ref(false);

/** ToggleGroup can deselect to empty; class type is mandatory, so ignore that. */
function setType(value) {
    if (value) form.value.type = value;
}

// Every open re-seeds the form so a reopened dialog never shows stale values.
watch(open, (isOpen) => {
    if (!isOpen) return;
    form.value = {
        title: props.session?.title ?? '',
        scheduled_at: toDatetimeLocal(props.session?.scheduled_at),
        type: props.session?.type ?? 'offline',
        location: {
            name: props.session?.location_name ?? '',
            address: props.session?.location_address ?? '',
            lat: props.session?.location_lat ?? null,
            lng: props.session?.location_lng ?? null,
        },
        meeting_url: props.session?.meeting_url ?? '',
    };
    formErrors.value = {};
});

async function save() {
    saving.value = true;
    formErrors.value = {};
    try {
        const payload = {
            title: form.value.title,
            scheduled_at: form.value.scheduled_at || null,
            type: form.value.type,
            location_name: form.value.location.name || null,
            location_address: form.value.location.address || null,
            location_lat: form.value.location.lat === '' ? null : form.value.location.lat ?? null,
            location_lng: form.value.location.lng === '' ? null : form.value.location.lng ?? null,
            meeting_url: form.value.meeting_url || null,
        };
        const res = isEditing.value
            ? await sessionsApi.update(props.session.id, payload)
            : await sessionsApi.create(props.cohortId, payload);
        open.value = false;
        emit('saved', res.session);
    } catch (e) {
        if (e.sessionExpired) return;
        formErrors.value = e.errors ?? {};
        if (!Object.keys(formErrors.value).length) formErrors.value = { title: [e.message ?? 'Gagal menyimpan.'] };
    } finally {
        saving.value = false;
    }
}
</script>

<template>
    <Dialog v-model:open="open" :title="isEditing ? 'Ubah Kelas' : 'Tambah Kelas'">
        <form @submit.prevent="save" class="space-y-3">
            <div>
                <label class="text-xs text-muted-foreground">Judul kelas</label>
                <Input v-model="form.title" placeholder="Contoh: Kelas 1: Riset Produk" class="mt-1.5" />
                <p v-if="formErrors.title" class="mt-1 text-xs text-destructive">{{ formErrors.title[0] }}</p>
            </div>
            <div>
                <label class="text-xs text-muted-foreground">Jadwal</label>
                <DateTimePicker v-model="form.scheduled_at" clearable placeholder="Pilih tanggal & jam" class="mt-1.5" />
                <p v-if="formErrors.scheduled_at" class="mt-1 text-xs text-destructive">{{ formErrors.scheduled_at[0] }}</p>
            </div>
            <div>
                <label class="text-xs text-muted-foreground">Tipe kelas</label>
                <ToggleGroup type="single" variant="outline" class="mt-1.5 w-full" :model-value="form.type" @update:model-value="setType">
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
            <div class="mt-4 flex justify-end gap-2 border-t border-border pt-4">
                <Button type="button" variant="outline" size="sm" @click="open = false">Batal</Button>
                <Button type="submit" size="sm" :disabled="saving">{{ saving ? 'Menyimpan…' : 'Simpan' }}</Button>
            </div>
        </form>
    </Dialog>
</template>
</content>
