<script setup>
import { ref, watch } from 'vue';
import { cohorts as cohortsApi, enrollments as enrollmentsApi } from '@/api';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';

const props = defineProps({
    /**
     * Freshly accepted application ({id, program_id}); non-null opens the
     * dialog listing that program's cohorts as placement options.
     */
    application: { type: Object, default: null },
    /** Cohort ids the person is already in (hidden from the options). */
    excludeCohortIds: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'enrolled']);

const cohorts = ref([]);
const error = ref('');

watch(
    () => props.application,
    async (application) => {
        if (!application) return;
        cohorts.value = [];
        error.value = '';
        try {
            const res = await cohortsApi.list();
            cohorts.value = res.data.filter(
                (c) => c.program?.id === application.program_id && !props.excludeCohortIds.includes(c.id),
            );
        } catch (e) {
            if (!e.sessionExpired) error.value = e.message;
        }
    },
);

async function enrollInto(cohort) {
    error.value = '';
    try {
        await enrollmentsApi.create({ cohort_id: cohort.id, application_id: props.application.id });
        emit('enrolled', cohort);
    } catch (e) {
        if (!e.sessionExpired) error.value = e.errors ? Object.values(e.errors)[0][0] : e.message;
    }
}
</script>

<template>
    <Dialog :open="application !== null" title="Masukkan ke Angkatan" @update:open="emit('close')">
        <p class="text-sm text-muted-foreground">Pelamar diterima. Pilih Angkatan untuk mendaftarkannya sekarang, atau lewati untuk melakukannya nanti dari halaman Angkatan.</p>
        <div v-if="error" class="mt-3 rounded-lg border border-destructive/30 bg-red-50 px-3.5 py-2.5 text-sm text-destructive">{{ error }}</div>
        <p v-if="!cohorts.length" class="mt-3 text-sm text-muted-foreground">Belum ada Angkatan untuk program ini.</p>
        <div v-else class="mt-3 space-y-2">
            <div v-for="c in cohorts" :key="c.id" class="flex items-center justify-between gap-3 rounded-lg border border-border px-3 py-2 text-sm">
                <div>
                    <p class="font-medium text-foreground">{{ c.name }}</p>
                    <p class="text-xs text-muted-foreground">{{ c.enrollments_count }} peserta</p>
                </div>
                <Button size="sm" variant="outline" @click="enrollInto(c)">Masukkan</Button>
            </div>
        </div>
        <div class="mt-4 flex justify-end">
            <Button variant="outline" size="sm" @click="emit('close')">Lewati</Button>
        </div>
    </Dialog>
</template>
