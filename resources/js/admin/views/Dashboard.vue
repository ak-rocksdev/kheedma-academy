<script setup>
import { ref, computed, onMounted } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { BookOpen, Check, ChevronRight, GraduationCap, Images, LayoutTemplate } from 'lucide-vue-next';
import { api, applications as applicationsApi } from '@/api';
import { useAuthStore } from '@/stores/auth';
import { fmtDate } from '@/lib/format';
import StatTile from '@/components/StatTile.vue';

const auth = useAuthStore();
const router = useRouter();

const stats = ref(null);
const pending = ref([]);
const pendingLoaded = ref(false);

const todayLabel = new Date().toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

// Satu kalimat yang menjawab "ada kerjaan apa hari ini?" — hanya bila memang
// ADA; kartu antrean sudah menyuarakan keadaan bersih, tak perlu dua kali.
const summary = computed(() => {
    const waiting = stats.value?.pending_applications;

    return waiting > 0 ? `${waiting} pendaftaran menunggu keputusanmu.` : '';
});

// Tahapan perjalanan peserta: urutan strip ini adalah urutan funnel sungguhan.
const JOURNEY = [
    { key: 'pending_applications', label: 'Menunggu review', hot: true, to: { name: 'people', query: { segment: 'needs-review' } } },
    { key: 'active_participants', label: 'Peserta aktif', to: { name: 'people', query: { segment: 'participants' } } },
    { key: 'attended_participants', label: 'Pernah hadir', to: { name: 'people', query: { segment: 'participants' } } },
];

onMounted(async () => {
    try {
        const res = await api('/admin/stats');
        stats.value = res.stats;
    } catch {
        stats.value = null; // strip funnel tidak dirender; antrean tetap tampil
    }

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
        <!-- Sapaan + ringkasan hari ini -->
        <header class="rise">
            <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Dashboard</p>
            <h1 class="mt-2 text-2xl font-bold text-foreground sm:text-3xl">Selamat datang, {{ auth.user?.name }}.</h1>
            <p class="mt-1.5 text-sm text-muted-foreground">
                {{ todayLabel }}<template v-if="summary"> · <span :class="stats?.pending_applications ? 'font-medium text-orange-600' : ''">{{ summary }}</span></template>
            </p>
        </header>

        <div class="mt-6 gap-6 sm:mt-8 lg:grid lg:grid-cols-5">
            <!-- Antrean kerja: alasan halaman ini dibuka -->
            <section class="rise lg:col-span-3" style="--rise-delay: 60ms">
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
                    <p v-if="!pendingLoaded" class="px-5 py-10 text-center text-sm text-muted-foreground">Memuat…</p>
                    <div v-else-if="!pending.length" class="flex flex-col items-center gap-2 px-5 py-10 text-center">
                        <span class="flex size-9 items-center justify-center rounded-full bg-teal-700/10">
                            <Check class="size-4 text-teal-700" />
                        </span>
                        <p class="text-sm text-muted-foreground">Antrean bersih. Semua pendaftaran sudah ditinjau.</p>
                    </div>
                    <ul v-else class="divide-y divide-border">
                        <li
                            v-for="application in pending"
                            :key="application.id"
                            class="flex cursor-pointer items-center justify-between gap-3 px-4 py-3.5 transition-colors hover:bg-accent/50 sm:px-5"
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

                <!-- Aksi cepat: jalur tersering, mengisi ruang di bawah antrean. -->
                <div class="mt-6">
                    <h2 class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Aksi Cepat</h2>
                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <RouterLink
                            v-for="action in [
                                { to: { name: 'cohorts' }, label: 'Kelola Angkatan', icon: GraduationCap, show: auth.can('cohorts.view') },
                                { to: { name: 'programs' }, label: 'Kelola Program', icon: BookOpen, show: auth.can('programs.manage') },
                                { to: { name: 'content' }, label: 'Tulis Konten', icon: LayoutTemplate, show: auth.can('content.manage') },
                                { to: { name: 'media' }, label: 'Unggah Media', icon: Images, show: auth.can('content.manage') },
                            ].filter((a) => a.show)"
                            :key="action.label"
                            :to="action.to"
                            class="flex items-center gap-2.5 rounded-xl border border-border bg-card px-4 py-3 text-sm font-medium text-foreground transition hover:border-primary/40 hover:shadow-sm"
                        >
                            <component :is="action.icon" class="size-4 shrink-0 text-teal-700" />
                            {{ action.label }}
                        </RouterLink>
                    </div>
                </div>
            </section>

            <!-- Denyut funnel + kondisi -->
            <div class="mt-8 space-y-6 lg:col-span-2 lg:mt-0">
                <!-- Strip perjalanan: tiga angka ini adalah tahapan satu perjalanan -->
                <section v-if="stats" class="rise" style="--rise-delay: 120ms">
                    <h2 class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Perjalanan Peserta</h2>
                    <div class="mt-3 flex items-center rounded-xl border border-border bg-card px-2 py-5">
                        <template v-for="(stage, index) in JOURNEY" :key="stage.key">
                            <div v-if="index > 0" class="flex shrink-0 items-center" aria-hidden="true">
                                <span class="h-px w-3 bg-border sm:w-5"></span>
                                <ChevronRight class="-ml-1 size-3.5 text-muted-foreground/50" />
                            </div>
                            <RouterLink :to="stage.to" class="group min-w-0 flex-1 text-center">
                                <p
                                    class="text-2xl font-bold tabular-nums"
                                    :class="stage.hot && stats[stage.key]
                                        ? 'text-orange-600'
                                        : stats[stage.key] ? 'text-foreground' : 'text-muted-foreground/40'"
                                >
                                    {{ stats[stage.key] }}
                                </p>
                                <p class="mt-1 text-[0.65rem] uppercase tracking-wide text-muted-foreground group-hover:text-foreground">
                                    {{ stage.label }}
                                </p>
                            </RouterLink>
                        </template>
                    </div>
                </section>

                <!-- Kondisi di luar funnel -->
                <section v-if="stats" class="rise" style="--rise-delay: 180ms">
                    <h2 class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Kondisi</h2>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        <StatTile :value="stats.active_cohorts" label="Kelas berjalan" :to="{ name: 'cohorts' }" :value-class="stats.active_cohorts ? 'text-foreground' : 'text-muted-foreground/40'" />
                        <StatTile :value="stats.community_members" label="Member komunitas" :to="{ name: 'community' }" :value-class="stats.community_members ? 'text-foreground' : 'text-muted-foreground/40'" />
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Orkestrasi masuk satu arah: tiap seksi naik berurutan, sekali saja. */
@media (prefers-reduced-motion: no-preference) {
    .rise {
        opacity: 0;
        transform: translateY(10px);
        animation: rise 0.45s ease-out forwards;
        animation-delay: var(--rise-delay, 0ms);
    }

    @keyframes rise {
        to {
            opacity: 1;
            transform: none;
        }
    }
}
</style>
