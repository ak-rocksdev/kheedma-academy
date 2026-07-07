<script setup>
import { computed } from 'vue';
import { DateFormatter, getLocalTimeZone, parseDate } from '@internationalized/date';
import { Calendar as CalendarIcon, X } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';

/** Two-way value is a plain 'YYYY-MM-DD' string ('' = empty), matching the API payloads. */
const model = defineModel({ type: String, default: '' });

defineProps({ placeholder: { type: String, default: 'Pilih tanggal' } });

const formatter = new DateFormatter('id-ID', { dateStyle: 'medium' });

const dateValue = computed({
    get: () => (model.value ? parseDate(model.value) : undefined),
    set: (value) => {
        model.value = value ? value.toString() : '';
    },
});

const label = computed(() =>
    dateValue.value ? formatter.format(dateValue.value.toDate(getLocalTimeZone())) : '',
);
</script>

<template>
    <div class="flex items-center gap-1">
        <Popover>
            <PopoverTrigger as-child>
                <Button
                    type="button"
                    variant="outline"
                    :class="cn('w-full justify-start text-left font-normal', !model && 'text-muted-foreground')"
                >
                    <CalendarIcon class="size-4" />
                    {{ label || placeholder }}
                </Button>
            </PopoverTrigger>
            <PopoverContent class="w-auto p-0">
                <Calendar v-model="dateValue" locale="id" />
            </PopoverContent>
        </Popover>
        <Button
            v-if="model"
            type="button"
            variant="ghost"
            size="icon"
            aria-label="Hapus tanggal"
            @click="model = ''"
        >
            <X class="size-4" />
        </Button>
    </div>
</template>
