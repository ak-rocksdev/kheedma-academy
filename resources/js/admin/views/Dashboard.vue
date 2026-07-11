<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { api } from '@/api';
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();
const stats = ref(null);

const TILES = [
    { key: 'pending_applications', label: 'Pelamar menunggu', to: { name: 'people', query: { segment: 'needs-review' } } },
    { key: 'community_members', label: 'Member komunitas' },
    { key: 'active_cohorts', label: 'Angkatan berjalan' },
    { key: 'active_participants', label: 'Peserta aktif' },
    { key: 'attended_participants', label: 'Pernah hadir' },
];

onMounted(async () => {
    try {
        const res = await api('/admin/stats');
        stats.value = res.stats;
    } catch {
        stats.value = null; // tiles simply don't render; entity cards remain
    }
});

// The data foundation the admin tool is built on.
const entities = [
    { name: 'Person', desc: 'Satu record per manusia, anchor nomor HP.' },
    { name: 'Application', desc: 'Submission formulir pendaftaran program.' },
    { name: 'Angkatan', desc: 'Kelas nyata: nama, tanggal, sesi, satu mentor.' },
    { name: 'Enrollment', desc: 'Tautan Person ke Angkatan saat diterima.' },
    { name: 'Absensi', desc: 'Kehadiran per sesi; dasar kelulusan otomatis.' },
    { name: 'Status Event', desc: 'Log append-only transisi status.' },
];
</script>

<template>
    <div>
        <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Dashboard</p>
        <h1 class="mt-2 text-3xl font-bold text-foreground">Selamat datang, {{ auth.user?.name }}.</h1>

        <div v-if="stats" class="mt-8 grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
            <component
                :is="tile.to ? RouterLink : 'div'"
                v-for="tile in TILES"
                :key="tile.key"
                :to="tile.to"
                class="block rounded-xl border border-border bg-card p-5"
                :class="tile.to ? 'transition hover:border-primary/40 hover:shadow-sm' : ''"
            >
                <p class="text-3xl font-bold tabular-nums text-foreground">{{ stats[tile.key] }}</p>
                <p class="mt-1 text-xs uppercase tracking-wide text-muted-foreground">{{ tile.label }}</p>
            </component>
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="entity in entities" :key="entity.name" class="rounded-xl border border-border bg-card p-5 transition hover:border-primary/30 hover:shadow-sm">
                <h2 class="font-display text-sm font-bold uppercase tracking-wide text-primary">{{ entity.name }}</h2>
                <p class="mt-2 text-sm leading-relaxed text-muted-foreground">{{ entity.desc }}</p>
            </div>
        </div>
    </div>
</template>
