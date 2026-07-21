<script setup>
// Direct enrolment from a Person's page (no funnel application): pick any
// cohort, and when the person has no login yet, optionally set their initial
// PIN. The sibling of EnrollToCohortDialog, which is application-scoped.
import { ref, computed, watch } from 'vue';
import { cohorts as cohortsApi, enrollments as enrollmentsApi } from '@/api';
import { Alert } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';
import PinInput from '@/components/PinInput.vue';

const props = defineProps({
    /** Person row ({id, name, account}); non-null opens the dialog. */
    person: { type: Object, default: null },
    /** Cohort ids the person is already in (hidden from the options). */
    excludeCohortIds: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'enrolled']);

const cohorts = ref([]);
const error = ref('');
const pin = ref('');
const busyCohortId = ref(null);
const createdPin = ref(''); // non-empty = success panel with the PIN to relay

/** No linked login yet: enrolling will provision one, so offer the PIN field. */
const needsAccount = computed(() => props.person !== null && !props.person.account);

watch(
    () => props.person,
    async (person) => {
        if (!person) return;
        cohorts.value = [];
        error.value = '';
        pin.value = '';
        createdPin.value = '';
        try {
            const res = await cohortsApi.list();
            cohorts.value = res.data.filter((c) => !props.excludeCohortIds.includes(c.id));
        } catch (e) {
            if (!e.sessionExpired) error.value = e.message;
        }
    },
);

async function enrollInto(cohort) {
    error.value = '';
    busyCohortId.value = cohort.id;
    try {
        const payload = { cohort_id: cohort.id, people_id: props.person.id };
        if (needsAccount.value && pin.value) payload.password = pin.value;
        const res = await enrollmentsApi.create(payload);
        if (res.generated_password) {
            createdPin.value = res.generated_password;
            emit('enrolled', cohort);
        } else {
            emit('enrolled', cohort);
            emit('close');
        }
    } catch (e) {
        if (!e.sessionExpired) error.value = e.errors ? Object.values(e.errors)[0][0] : e.message;
    } finally {
        busyCohortId.value = null;
    }
}
</script>

<template>
    <Dialog :open="person !== null" title="Daftarkan ke Angkatan" @update:open="emit('close')">
        <!-- Success: the account was provisioned; show the PIN to relay once. -->
        <div v-if="createdPin" class="space-y-3">
            <p class="text-sm text-foreground">
                {{ person?.name }} terdaftar dan akunnya dibuat. Sampaikan PIN awal ini ke peserta:
            </p>
            <code class="block rounded-md border border-border bg-background px-3 py-2 text-center text-lg font-semibold tracking-[0.4em]">{{ createdPin }}</code>
            <div class="flex justify-end">
                <Button size="sm" @click="emit('close')">Selesai</Button>
            </div>
        </div>

        <template v-else>
            <p class="text-sm text-muted-foreground">
                Pilih angkatan untuk mendaftarkan {{ person?.name }} secara langsung, tanpa lewat formulir pendaftaran.
            </p>

            <div v-if="needsAccount" class="mt-3 rounded-lg border border-border bg-background/60 px-3 py-2.5">
                <label class="text-xs text-muted-foreground">PIN awal (6 digit)</label>
                <PinInput v-model="pin" class="mt-1.5" />
                <p class="mt-1.5 text-xs text-muted-foreground">
                    Belum punya akun; akun dibuat otomatis saat didaftarkan. Kosongkan untuk memakai PIN awal 123456.
                </p>
            </div>

            <Alert v-if="error" class="mt-3 px-3.5 py-2.5">{{ error }}</Alert>
            <p v-if="!cohorts.length && !error" class="mt-3 text-sm text-muted-foreground">Tidak ada angkatan yang bisa dipilih.</p>
            <div v-else class="mt-3 max-h-72 space-y-2 overflow-y-auto">
                <div v-for="c in cohorts" :key="c.id" class="flex items-center justify-between gap-3 rounded-lg border border-border px-3 py-2 text-sm">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-foreground">{{ c.name }}</p>
                        <p class="truncate text-xs text-muted-foreground">{{ c.program?.name ?? 'Tanpa program' }} · {{ c.enrollments_count }} peserta</p>
                    </div>
                    <Button size="sm" variant="outline" :disabled="busyCohortId !== null" @click="enrollInto(c)">
                        {{ busyCohortId === c.id ? 'Menyimpan…' : 'Daftarkan' }}
                    </Button>
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <Button variant="outline" size="sm" @click="emit('close')">Batal</Button>
            </div>
        </template>
    </Dialog>
</template>
