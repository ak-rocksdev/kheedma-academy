<script setup>
import { Check, X } from 'lucide-vue-next';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

/**
 * Two-option intake decision on a list row: Diterima or Ditolak. "Menunggu"
 * is not a choice (one-way rule); re-picking the current status and the
 * ToggleGroup's deselect-to-empty are ignored.
 */
const props = defineProps({
    status: { type: String, required: true }, // pending | accepted | rejected
    disabled: { type: Boolean, default: false },
});

const emit = defineEmits(['decide']);

function onUpdate(value) {
    if (!value || value === props.status) return;
    emit('decide', value);
}
</script>

<template>
    <div class="flex items-center gap-2">
        <ToggleGroup
            type="single"
            variant="outline"
            :model-value="status === 'pending' ? '' : status"
            :disabled="disabled"
            @update:model-value="onUpdate"
        >
            <ToggleGroupItem value="accepted" class="gap-1 text-teal-700 data-[state=on]:bg-teal-50 data-[state=on]:text-teal-700">
                <Check class="size-3.5" /> Diterima
            </ToggleGroupItem>
            <ToggleGroupItem value="rejected" class="gap-1 text-destructive data-[state=on]:bg-red-50 data-[state=on]:text-destructive">
                <X class="size-3.5" /> Ditolak
            </ToggleGroupItem>
        </ToggleGroup>
        <span v-if="status === 'pending'" class="text-xs text-muted-foreground">menunggu</span>
    </div>
</template>
