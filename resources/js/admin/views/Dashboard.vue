<script setup>
import { useRouter } from 'vue-router';
import { useAuthStore } from '../stores/auth';

const router = useRouter();
const auth = useAuthStore();

// The six entities of the v1 data foundation — the spine of the admin tool.
const entities = [
    { name: 'Person', desc: 'Satu record per manusia, anchor nomor HP.' },
    { name: 'Application', desc: 'Submission formulir + hasil tugas pra-seleksi.' },
    { name: 'Cohort', desc: 'Kelas nyata: nama, tanggal, satu mentor.' },
    { name: 'Mentor', desc: 'Entitas tipis, direferensi oleh cohort.' },
    { name: 'Enrollment', desc: 'Tautan Person ↔ Cohort saat diterima.' },
    { name: 'Status Event', desc: 'Log append-only transisi status.' },
];

function logout() {
    auth.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <div>
        <header class="border-b border-teal-900/10 bg-white/70 backdrop-blur">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <div class="flex items-center gap-3">
                    <img :src="'/images/kheedma-academy-horizontal.png'" width="1408" height="492" alt="Kheedma Academy" class="h-8 w-auto" />
                    <span class="rounded-full bg-teal-700/10 px-2.5 py-0.5 text-[0.6rem] font-semibold uppercase tracking-[0.2em] text-teal-700">Admin</span>
                </div>
                <div class="flex items-center gap-4 text-sm">
                    <span class="text-teal-800/70">{{ auth.user?.name }}</span>
                    <button class="font-medium text-teal-700 hover:text-orange-600" @click="logout">Keluar</button>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-6 py-12">
            <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Dashboard</p>
            <h1 class="mt-3 text-3xl font-bold text-teal-900">Selamat datang.</h1>
            <p class="mt-3 max-w-2xl text-teal-800/70">
                Kerangka admin panel sudah berjalan. Modul operasional (pelamar, cohort,
                enrollment, status event) dibangun di Layer 2 di atas fondasi data berikut.
            </p>

            <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="entity in entities"
                    :key="entity.name"
                    class="rounded-2xl border border-teal-900/10 bg-white p-5 transition hover:border-orange-500/40 hover:shadow-sm"
                >
                    <h2 class="font-display text-sm font-bold uppercase tracking-wide text-teal-700">{{ entity.name }}</h2>
                    <p class="mt-2 text-sm leading-relaxed text-teal-800/70">{{ entity.desc }}</p>
                </div>
            </div>
        </main>
    </div>
</template>
