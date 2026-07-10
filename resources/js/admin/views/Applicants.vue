<script setup>
import { ref, watch, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { api, programs as programsApi } from '@/api';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { APPLICATION_STATUSES, statusVariant, statusLabel } from '@/lib/status';

const items = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const q = ref('');
const status = ref('');
const programs = ref([]);
const program = ref('');
const loading = ref(false);
const error = ref('');

let debounce;

async function fetchPage(page = 1) {
    loading.value = true;
    error.value = '';
    const params = new URLSearchParams();
    if (q.value) params.set('q', q.value);
    if (status.value) params.set('status', status.value);
    if (program.value) params.set('program', program.value);
    params.set('page', page);
    try {
        const res = await api(`/admin/applications?${params.toString()}`);
        items.value = res.data;
        meta.value = { current_page: res.current_page, last_page: res.last_page, total: res.total };
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        error.value = e.message ?? 'Gagal memuat data.';
    } finally {
        loading.value = false;
    }
}

onMounted(async () => {
    fetchPage();
    try {
        const res = await programsApi.list();
        programs.value = res.data;
    } catch {
        programs.value = [];
    }
});
watch(q, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => fetchPage(1), 300);
});
watch(status, () => fetchPage(1));
watch(program, () => fetchPage(1));

function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}

const selectClass =
    'h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
</script>

<template>
    <div>
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Pelamar</p>
                <h1 class="mt-2 text-2xl font-bold text-foreground">Daftar Pendaftar</h1>
            </div>
            <span class="text-sm text-muted-foreground">{{ meta.total }} pendaftaran</span>
        </div>

        <!-- Filters -->
        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
            <Input v-model="q" placeholder="Cari nama, HP, atau email…" class="sm:max-w-xs" />
            <select v-model="status" :class="selectClass">
                <option value="">Semua status</option>
                <option v-for="s in APPLICATION_STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
            </select>
            <select v-model="program" :class="selectClass">
                <option value="">Semua program</option>
                <option v-for="p in programs" :key="p.id" :value="p.id">{{ p.name }}</option>
            </select>
        </div>

        <div v-if="error" class="mt-4 rounded-lg border border-destructive/30 bg-red-50 px-4 py-3 text-sm text-destructive">
            {{ error }}
        </div>

        <!-- Table -->
        <div class="mt-5 overflow-hidden rounded-xl border border-border bg-card">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <th class="px-4 py-3 font-semibold">Nama</th>
                        <th class="px-4 py-3 font-semibold">Kontak</th>
                        <th class="px-4 py-3 font-semibold">Domisili</th>
                        <th class="px-4 py-3 font-semibold">Program</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold">Tanggal</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">Memuat…</td>
                    </tr>
                    <tr v-else-if="!items.length">
                        <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">Belum ada pendaftar.</td>
                    </tr>
                    <tr
                        v-for="item in items"
                        :key="item.id"
                        class="border-b border-border last:border-0 transition-colors hover:bg-accent/50"
                    >
                        <td class="px-4 py-3 font-medium text-foreground">
                            {{ item.person.name }}
                            <Badge v-if="item.person.applications_count > 1" variant="warning" class="ml-1.5">
                                {{ item.person.applications_count }}× daftar
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            <div>{{ item.person.phone }}</div>
                            <div class="text-xs">{{ item.person.email }}</div>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">{{ item.person.city ?? '—' }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ item.program ?? '—' }}</td>
                        <td class="px-4 py-3"><Badge :variant="statusVariant(item.status)">{{ statusLabel(item.status) }}</Badge></td>
                        <td class="px-4 py-3 text-muted-foreground">{{ fmtDate(item.created_at) }}</td>
                        <td class="px-4 py-3 text-right">
                            <RouterLink :to="{ name: 'person', params: { id: item.person.id } }">
                                <Button variant="ghost" size="sm">Lihat</Button>
                            </RouterLink>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-4 flex items-center justify-between text-sm text-muted-foreground">
            <span>Halaman {{ meta.current_page }} dari {{ meta.last_page }}</span>
            <div class="flex gap-2">
                <Button variant="outline" size="sm" :disabled="meta.current_page <= 1" @click="fetchPage(meta.current_page - 1)">Sebelumnya</Button>
                <Button variant="outline" size="sm" :disabled="meta.current_page >= meta.last_page" @click="fetchPage(meta.current_page + 1)">Berikutnya</Button>
            </div>
        </div>
    </div>
</template>
