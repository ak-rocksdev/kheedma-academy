<script setup>
import { ref } from 'vue';
import { Eye, EyeOff } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';

const model = defineModel({ type: String, default: '' });

defineProps({
    placeholder: { type: String, default: '' },
    autocomplete: { type: String, default: 'current-password' },
    autofocus: { type: Boolean, default: false },
    required: { type: Boolean, default: false },
});

const visible = ref(false);
</script>

<template>
    <div class="relative">
        <Input
            v-model="model"
            :type="visible ? 'text' : 'password'"
            :placeholder="placeholder"
            :autocomplete="autocomplete"
            :autofocus="autofocus"
            :required="required"
            class="pr-10"
        />
        <button
            type="button"
            :aria-label="visible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi'"
            class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-muted-foreground transition-colors hover:text-foreground focus-visible:text-foreground focus-visible:outline-none"
            @click="visible = !visible"
        >
            <component :is="visible ? EyeOff : Eye" class="size-4" />
        </button>
    </div>
</template>
