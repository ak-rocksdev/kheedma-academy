<script setup>
import { useAuthStore } from '@/stores/auth';

const auth = useAuthStore();

// The data foundation the admin tool is built on (Layer 2 modules land here).
const entities = [
    { name: 'Person', desc: 'Satu record per manusia, anchor nomor HP.' },
    { name: 'Application', desc: 'Submission formulir + hasil tugas pra-seleksi.' },
    { name: 'Angkatan', desc: 'Kelas nyata: nama, tanggal, satu mentor.' },
    { name: 'Mentor', desc: 'User dengan role mentor, memimpin cohort.' },
    { name: 'Enrollment', desc: 'Tautan Person ↔ Cohort saat diterima.' },
    { name: 'Status Event', desc: 'Log append-only transisi status.' },
];
</script>

<template>
    <div class="mx-auto max-w-6xl">
        <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Dashboard</p>
        <h1 class="mt-2 text-3xl font-bold text-foreground">Selamat datang, {{ auth.user?.name }}.</h1>
        <p class="mt-3 max-w-2xl text-muted-foreground">
            Autentikasi admin sudah aktif. Modul operasional (pelamar, angkatan, enrollment,
            status event, merge) dibangun bertahap di atas fondasi data berikut.
        </p>

        <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="entity in entities"
                :key="entity.name"
                class="rounded-xl border border-border bg-card p-5 transition hover:border-primary/30 hover:shadow-sm"
            >
                <h2 class="font-display text-sm font-bold uppercase tracking-wide text-primary">{{ entity.name }}</h2>
                <p class="mt-2 text-sm leading-relaxed text-muted-foreground">{{ entity.desc }}</p>
            </div>
        </div>
    </div>
</template>
