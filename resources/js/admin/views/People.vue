<script setup>
import { ref, watch, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import { people } from '@/api';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

const SEGMENTS = [
    { value: 'pendaftar', label: 'Pendaftar' },
    { value: 'komunitas', label: 'Anggota komunitas' },
    { value: 'peserta', label: 'Peserta program' },
    { value: 'berakun', label: 'Punya akun' },
];

const items = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const q = ref('');
const segment = ref('');
const loading = ref(false);
const error = ref('');

let debounce;

async function fetchPage(page = 1) {
    loading.value = true;
    error.value = '';
    const params = new URLSearchParams();
    if (q.value) params.set('q', q.value);
    if (segment.value) params.set('segment', segment.value);
    params.set('page', page);
    try {
        const res = await people.list(`?${params.toString()}`);
        items.value = res.data;
        meta.value = { current_page: res.current_page, last_page: res.last_page, total: res.total };
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        error.value = e.message ?? 'Gagal memuat data.';
    } finally {
        loading.value = false;
    }
}

onMounted(() => fetchPage());
watch(q, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => fetchPage(1), 300);
});
watch(segment, () => fetchPage(1));

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
                <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Orang</p>
                <h1 class="mt-2 text-2xl font-bold text-foreground">Semua Orang</h1>
            </div>
            <span class="text-sm text-muted-foreground">{{ meta.total }} orang</span>
        </div>

        <!-- Filters -->
        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
            <Input v-model="q" placeholder="Cari nama, HP, atau email…" class="sm:max-w-xs" />
            <select v-model="segment" :class="selectClass">
                <option value="">Semua segmen</option>
                <option v-for="s in SEGMENTS" :key="s.value" :value="s.value">{{ s.label }}</option>
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
                        <th class="px-4 py-3 font-semibold">Jejak</th>
                        <th class="px-4 py-3 font-semibold">Terdaftar</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading">
                        <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">Memuat…</td>
                    </tr>
                    <tr v-else-if="!items.length">
                        <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">Belum ada data.</td>
                    </tr>
                    <tr
                        v-for="item in items"
                        :key="item.id"
                        class="border-b border-border last:border-0 transition-colors hover:bg-accent/50"
                    >
                        <td class="px-4 py-3 font-medium text-foreground">{{ item.name }}</td>
                        <td class="px-4 py-3 text-muted-foreground">
                            <div>{{ item.phone }}</div>
                            <div class="text-xs">{{ item.email }}</div>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">{{ item.city ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1.5">
                                <Badge v-if="item.applications_count" variant="secondary">{{ item.applications_count }}× daftar</Badge>
                                <Badge v-if="item.enrollments_count" variant="secondary">{{ item.enrollments_count }} angkatan</Badge>
                                <Badge v-if="item.is_community_member" variant="secondary">Komunitas</Badge>
                                <Badge v-if="item.has_account" variant="success">Akun</Badge>
                                <span v-if="!item.applications_count && !item.enrollments_count && !item.is_community_member && !item.has_account" class="text-muted-foreground">—</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">{{ fmtDate(item.created_at) }}</td>
                        <td class="px-4 py-3 text-right">
                            <RouterLink :to="{ name: 'person', params: { id: item.id } }">
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
