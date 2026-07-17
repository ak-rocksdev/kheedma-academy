<script setup>
import { computed } from 'vue';
import { DateFormatter, getLocalTimeZone, parseDate } from '@internationalized/date';
import { CalendarClock, X } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Calendar } from '@/components/ui/calendar';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { cn } from '@/lib/utils';

/**
 * Datetime picker composed from shadcn parts (shadcn ships no official
 * datetime component): Popover + Calendar pick the date, a time field sets
 * the clock — typed entry on desktop, the native wheel on mobile.
 *
 * Two-way value is a datetime-local string 'YYYY-MM-DDTHH:mm' ('' = empty),
 * a drop-in for the API's datetime fields.
 */
const model = defineModel({ type: String, default: '' });

defineProps({
    placeholder: { type: String, default: 'Pilih tanggal & jam' },
    /** Optional field: shows a "Kosongkan" action when a value is set. */
    clearable: { type: Boolean, default: false },
});

const DEFAULT_TIME = '09:00';

const datePart = computed(() => (model.value ? model.value.slice(0, 10) : ''));
const timePart = computed(() => (model.value ? model.value.slice(11, 16) : ''));

const calendarValue = computed({
    get: () => (datePart.value ? parseDate(datePart.value) : undefined),
    set: (value) => {
        model.value = value ? `${value.toString()}T${timePart.value || DEFAULT_TIME}` : '';
    },
});

function onTimeChange(event) {
    const time = event.target.value;
    if (!datePart.value) return; // a clock without a date is meaningless
    model.value = `${datePart.value}T${time || DEFAULT_TIME}`;
}

const dateFormatter = new DateFormatter('id-ID', { dateStyle: 'medium' });

const label = computed(() => {
    if (!model.value) return '';
    const date = dateFormatter.format(parseDate(datePart.value).toDate(getLocalTimeZone()));
    return `${date}, ${timePart.value.replace(':', '.')}`;
});
</script>

<template>
    <!-- Real root element so fallthrough attrs (class, etc.) land somewhere;
         the Popover root itself is renderless. -->
    <div>
    <Popover>
        <PopoverTrigger as-child>
            <Button
                type="button"
                variant="outline"
                :class="cn('w-full justify-between text-left font-normal', !model && 'text-muted-foreground')"
            >
                <span class="truncate">{{ label || placeholder }}</span>
                <CalendarClock class="size-4 shrink-0 opacity-60" />
            </Button>
        </PopoverTrigger>
        <PopoverContent class="w-auto p-0">
            <Calendar v-model="calendarValue" locale="id" />
            <div class="flex items-center gap-2 border-t border-border p-3">
                <label class="text-xs font-medium text-muted-foreground">Jam</label>
                <Input
                    type="time"
                    :model-value="timePart"
                    class="h-8 w-auto"
                    :disabled="!datePart"
                    @change="onTimeChange"
                />
                <Button
                    v-if="clearable && model"
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="ml-auto text-muted-foreground"
                    @click="model = ''"
                >
                    <X class="mr-1 size-3.5" /> Kosongkan
                </Button>
            </div>
        </PopoverContent>
    </Popover>
    </div>
</template>
