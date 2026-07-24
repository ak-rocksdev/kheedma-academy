<script setup>
// House-style confirmation: one question, explicit action buttons. Built on
// Dialog so overlay, Escape, and the close button behave like every dialog.
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';

const open = defineModel('open', { type: Boolean, default: false });

defineProps({
    title: { type: String, required: true },
    confirmLabel: { type: String, default: 'Ya, lanjutkan' },
    cancelLabel: { type: String, default: 'Batal' },
    /** Visual weight of the confirm button: 'default' | 'destructive'. */
    variant: { type: String, default: 'default' },
    /** Disables both buttons while the confirmed action runs. */
    busy: { type: Boolean, default: false },
});

const emit = defineEmits(['confirm']);
</script>

<template>
    <Dialog v-model:open="open" :title="title">
        <div class="space-y-5">
            <div class="text-sm text-muted-foreground"><slot /></div>
            <div class="flex justify-end gap-2">
                <Button variant="outline" :disabled="busy" @click="open = false">{{ cancelLabel }}</Button>
                <Button :variant="variant" :disabled="busy" @click="emit('confirm')">{{ confirmLabel }}</Button>
            </div>
        </div>
    </Dialog>
</template>
