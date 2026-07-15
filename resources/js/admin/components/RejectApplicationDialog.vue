<script setup>
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';

/**
 * Confirm dialog for rejecting an application, with an optional reason that
 * lands in applications.review_note. Opens while `target` is non-null.
 */
const props = defineProps({
    target: { type: Object, default: null },
    personName: { type: String, default: '' },
    /** Honest side-effect note (placement/attendance survives), when relevant. */
    warning: { type: String, default: '' },
    /** true = softer styling for the warning; false = orange emphasis. */
    warningMuted: { type: Boolean, default: true },
});

const emit = defineEmits(['close', 'confirm']);

const note = ref('');

watch(
    () => props.target,
    (target) => {
        if (target) note.value = '';
    },
);
</script>

<template>
    <Dialog :open="target !== null" title="Tolak Pendaftaran" @update:open="emit('close')">
        <p class="text-sm text-muted-foreground">
            Tolak pendaftaran {{ personName || 'pendaftar ini' }}? Dia masih boleh mendaftar lagi di lain waktu.
        </p>
        <p v-if="warning" class="mt-2 text-sm" :class="warningMuted ? 'text-muted-foreground' : 'text-orange-700'">
            {{ warning }}
        </p>
        <div class="mt-3">
            <label class="text-xs text-muted-foreground">Alasan (opsional, tampil untuk pendaftar di akunnya)</label>
            <Textarea v-model="note" rows="3" placeholder="Contoh: belum sesuai kriteria angkatan / kelas ini." class="mt-1.5" />
        </div>
        <div class="mt-4 flex justify-end gap-2">
            <Button variant="outline" size="sm" @click="emit('close')">Batal</Button>
            <Button variant="destructive" size="sm" @click="emit('confirm', note || null)">Tolak Pendaftaran</Button>
        </div>
    </Dialog>
</template>
