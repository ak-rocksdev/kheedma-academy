<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { ChevronRight } from 'lucide-vue-next';
import { api, applications as applicationsApi } from '@/api';
import { useAuthStore } from '@/stores/auth';
import { fmtDate } from '@/lib/format';
import StatTile from '@/components/StatTile.vue';

const auth = useAuthStore();
const router = useRouter();

const stats = ref(null);
const pending = ref([]);
const pendingLoaded = ref(false);

// Setiap angka bisa diketuk menuju daftar yang menjelaskannya.
const TILES = [
    { key: 'pending_applications', label: 'Pelamar menunggu', highlight: true, to: { name: 'people', query: { segment: 'needs-review' } } },
    { key: 'community_members', label: 'Member komunitas', to: { name: 'community' } },
    { key: 'active_cohorts', label: 'Angkatan / Kelas berjalan', to: { name: 'cohorts' } },
    { key: 'active_participants', label: 'Peserta aktif', to: { name: 'people', query: { segment: 'participants' } } },
    { key: 'attended_participants', label: 'Pernah hadir', to: { name: 'people', query: { segment: 'participants' } } },
];

onMounted(async () => {
    try {
        const res = await api('/admin/stats');
        stats.value = res.stats;
    } catch {
        stats.value = null; // tiles simply don't render; the action list remains
    }

    // Antrean review terbaru: pekerjaan yang paling sering ditunggu tim.
    try {
        const res = await applicationsApi.list('?status=pending&per_page=5');
        pending.value = res.data;
    } catch {
        pending.value = [];
    } finally {
        pendingLoaded.value = true;
    }
});

function goPerson(application) {
    if (!application.person) return;
    router.push({ name: 'person', params: { id: application.person.id } });
}
</script>

<template>
    <div>
        <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Dashboard</p>
        <h1 class="mt-2 text-2xl font-bold text-foreground sm:text-3xl">Selamat datang, {{ auth.user?.name }}.</h1>

        <!-- Angka kunci: 2 kolom di ponsel agar sekali pandang, melebar di layar besar -->
        <div v-if="stats" class="mt-6 grid grid-cols-2 gap-3 sm:mt-8 sm:gap-4 md:grid-cols-3 lg:grid-cols-5">
            <StatTile
                v-for="tile in TILES"
                :key="tile.key"
                :value="stats[tile.key]"
                :label="tile.label"
                :to="tile.to ?? null"
                :value-class="tile.highlight && stats[tile.key] ? 'text-orange-600' : 'text-foreground'"
            />
        </div>

        <!-- Antrean kerja: pendaftaran yang menunggu keputusan -->
        <div class="mt-8">
            <div class="flex items-end justify-between gap-4">
                <h2 class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Perlu Tindakan</h2>
                <RouterLink
                    v-if="pending.length"
                    :to="{ name: 'people', query: { segment: 'needs-review' } }"
                    class="text-sm font-medium text-teal-700 hover:underline"
                >
                    Lihat semua
                </RouterLink>
            </div>
            <div class="mt-3 overflow-hidden rounded-xl border border-border bg-card">
                <p v-if="!pendingLoaded" class="px-5 py-8 text-center text-sm text-muted-foreground">Memuat…</p>
                <p v-else-if="!pending.length" class="px-5 py-8 text-center text-sm text-muted-foreground">
                    Tidak ada pendaftaran yang menunggu review. Semua sudah tertangani.
                </p>
                <ul v-else class="divide-y divide-border">
                    <li
                        v-for="application in pending"
                        :key="application.id"
                        class="flex cursor-pointer items-center justify-between gap-3 px-4 py-3 transition-colors hover:bg-accent/50 sm:px-5"
                        @click="goPerson(application)"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium text-foreground">{{ application.person?.name ?? 'Orang dihapus' }}</p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ application.program ?? 'Program' }}<span v-if="application.cohort"> · {{ application.cohort.name }}</span>
                                · {{ fmtDate(application.created_at) }}
                            </p>
                        </div>
                        <ChevronRight class="size-4 shrink-0 text-muted-foreground" />
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
