<script setup>
// 6-digit PIN entry for admin forms — the SPA sibling of the public
// <x-pin-input> component: masked dots with one eye toggle, auto-advance,
// backspace steps back, paste distributes. Emits the composed digit string
// (may be partial while typing).
import { ref } from 'vue';
import { Eye, EyeOff } from 'lucide-vue-next';

const model = defineModel({ type: String, default: '' });

const BOX_COUNT = 6;
const boxes = ref([]);
const digits = ref(Array(BOX_COUNT).fill(''));
const masked = ref(true);

function sync() {
    model.value = digits.value.join('');
}

function fill(text, from) {
    text.split('').forEach((digit, offset) => {
        if (from + offset < BOX_COUNT) digits.value[from + offset] = digit;
    });
    boxes.value[Math.min(from + text.length, BOX_COUNT - 1)]?.focus();
    sync();
}

function onInput(index, event) {
    const typed = event.target.value.replace(/\D/g, '');
    digits.value[index] = '';
    event.target.value = '';
    if (typed) {
        fill(typed.slice(0, BOX_COUNT - index), index);
    } else {
        sync();
    }
}

function onKeydown(index, event) {
    if (event.key === 'Backspace' && digits.value[index] === '' && index > 0) {
        digits.value[index - 1] = '';
        boxes.value[index - 1]?.focus();
        sync();
        event.preventDefault();
    } else if (event.key === 'ArrowLeft' && index > 0) {
        boxes.value[index - 1]?.focus();
        event.preventDefault();
    } else if (event.key === 'ArrowRight' && index < BOX_COUNT - 1) {
        boxes.value[index + 1]?.focus();
        event.preventDefault();
    }
}

function onPaste(event) {
    const text = (event.clipboardData?.getData('text') ?? '').replace(/\D/g, '');
    if (text) {
        fill(text.slice(0, BOX_COUNT), 0);
    }
    event.preventDefault();
}
</script>

<template>
    <div class="flex items-center gap-1.5" role="group" aria-label="PIN 6 digit">
        <input
            v-for="index in BOX_COUNT"
            :key="index"
            :ref="(el) => (boxes[index - 1] = el)"
            :value="digits[index - 1]"
            :type="masked ? 'password' : 'text'"
            inputmode="numeric"
            maxlength="6"
            autocomplete="off"
            :aria-label="`Digit ${index}`"
            class="h-11 w-full min-w-0 max-w-10 rounded-md border border-input bg-background text-center text-base font-semibold text-foreground outline-none transition focus:border-ring focus:ring-2 focus:ring-ring/20"
            :class="{ 'ml-2': index === 4 }"
            @input="onInput(index - 1, $event)"
            @keydown="onKeydown(index - 1, $event)"
            @paste="onPaste"
        />
        <button
            type="button"
            :aria-label="masked ? 'Tampilkan PIN' : 'Sembunyikan PIN'"
            class="flex h-11 w-9 shrink-0 items-center justify-center rounded-md text-muted-foreground transition hover:bg-accent hover:text-foreground"
            @click="masked = !masked"
        >
            <Eye v-if="masked" class="size-4" />
            <EyeOff v-else class="size-4" />
        </button>
    </div>
</template>
