<script setup>
import { ref, watch, onMounted } from 'vue';
import { Phone, UserMinus } from 'lucide-vue-next';
import { communityMembers as communityApi } from '@/api';
import { useAuthStore } from '@/stores/auth';
import { Alert } from '@/components/ui/alert';
import { Dialog } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { fmtDate } from '@/lib/format';

const auth = useAuthStore();

const items = ref([]);
const meta = ref({ current_page: 1, last_page: 1, total: 0 });
const q = ref('');
const loading = ref(false);
const error = ref('');

let debounce;

const REFERRAL_LABELS = {
    instagram: 'Instagram',
    tiktok: 'TikTok',
    whatsapp: 'WhatsApp',
    teman: 'Teman/keluarga',
    google: 'Google',
    lainnya: 'Lainnya',
};

async function fetchPage(page = 1) {
    loading.value = true;
    error.value = '';
    const params = new URLSearchParams();
    if (q.value) params.set('q', q.value);
    params.set('page', page);
    try {
        const res = await communityApi.list(`?${params.toString()}`);
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

// Keluarkan anggota: revoke the membership only; the person and their
// account stay, and the join gate applies to them again.
const removeTarget = ref(null);
const removeError = ref('');
const removing = ref(false);

function openRemove(member) {
    removeError.value = '';
    removeTarget.value = member;
}

async function confirmRemove() {
    removing.value = true;
    try {
        await communityApi.remove(removeTarget.value.id);
        removeTarget.value = null;
        await fetchPage(meta.value.current_page);
    } catch (e) {
        if (!e.sessionExpired) removeError.value = e.message ?? 'Gagal mengeluarkan anggota.';
    } finally {
        removing.value = false;
    }
}
</script>

<template>
    <div>
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Komunitas</p>
                <h1 class="mt-2 text-2xl font-bold text-foreground">Anggota Komunitas</h1>
            </div>
            <span class="shrink-0 whitespace-nowrap text-sm text-muted-foreground">{{ meta.total }} anggota</span>
        </div>

        <div class="mt-6">
            <Input v-model="q" placeholder="Cari nama, HP, atau email…" class="sm:max-w-xs" />
        </div>

        <Alert v-if="error" class="mt-4">{{ error }}</Alert>

        <!-- ≥md: tabel. Mobile: kartu — kolom sumber & tanggal terpotong di layar sempit. -->
        <div class="mt-5 overflow-hidden rounded-xl border border-border bg-card">
            <ul class="divide-y divide-border md:hidden">
                <li v-if="loading" class="px-4 py-10 text-center text-sm text-muted-foreground">Memuat…</li>
                <li v-else-if="!items.length" class="px-4 py-10 text-center text-sm text-muted-foreground">Belum ada anggota.</li>
                <li v-for="member in items" :key="`card-${member.id}`" class="px-4 py-3.5">
                    <p class="truncate font-medium text-foreground">{{ member.person.name }}</p>
                    <!-- Di perangkat telepon, nomor adalah aksi — bukan teks. -->
                    <a
                        v-if="member.person.phone"
                        :href="`tel:${member.person.phone}`"
                        class="mt-0.5 inline-flex items-center gap-1.5 py-0.5 text-sm font-medium text-teal-700"
                    >
                        <Phone class="size-3.5" /> {{ member.person.phone }}
                    </a>
                    <p v-if="member.person.email" class="truncate text-xs text-muted-foreground">{{ member.person.email }}</p>
                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                        <Badge variant="secondary">{{ REFERRAL_LABELS[member.referral_source] ?? member.referral_source ?? '—' }}</Badge>
                        <span class="ml-auto text-[11px] text-muted-foreground">Bergabung {{ fmtDate(member.joined_at) }}</span>
                    </div>
                    <Button
                        v-if="auth.can('community.manage')"
                        variant="outline"
                        size="sm"
                        class="mt-2.5 text-red-700"
                        @click="openRemove(member)"
                    >
                        <UserMinus class="mr-1 h-3.5 w-3.5" /> Keluarkan
                    </Button>
                </li>
            </ul>

            <table class="hidden w-full text-sm md:table">
                <thead>
                    <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <th class="px-4 py-3 font-semibold">Nama</th>
                        <th class="px-4 py-3 font-semibold">Kontak</th>
                        <th class="px-4 py-3 font-semibold">Sumber</th>
                        <th class="px-4 py-3 font-semibold">Bergabung</th>
                        <th v-if="auth.can('community.manage')" class="px-4 py-3"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading"><td colspan="5" class="px-4 py-10 text-center text-muted-foreground">Memuat…</td></tr>
                    <tr v-else-if="!items.length"><td colspan="5" class="px-4 py-10 text-center text-muted-foreground">Belum ada anggota.</td></tr>
                    <tr v-for="member in items" :key="member.id" class="border-b border-border last:border-0">
                        <td class="px-4 py-3 font-medium text-foreground">{{ member.person.name }}</td>
                        <td class="px-4 py-3 text-muted-foreground">
                            <div>{{ member.person.phone }}</div>
                            <div class="text-xs">{{ member.person.email }}</div>
                        </td>
                        <td class="px-4 py-3">
                            <Badge variant="secondary">{{ REFERRAL_LABELS[member.referral_source] ?? member.referral_source ?? '—' }}</Badge>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">{{ fmtDate(member.joined_at) }}</td>
                        <td v-if="auth.can('community.manage')" class="px-4 py-3 text-right">
                            <Button variant="outline" size="sm" class="text-red-700" @click="openRemove(member)">
                                <UserMinus class="mr-1 h-3.5 w-3.5" /> Keluarkan
                            </Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex items-center justify-between text-sm text-muted-foreground">
            <span>Halaman {{ meta.current_page }} dari {{ meta.last_page }}</span>
            <div class="flex gap-2">
                <Button variant="outline" size="sm" :disabled="meta.current_page <= 1" @click="fetchPage(meta.current_page - 1)">Sebelumnya</Button>
                <Button variant="outline" size="sm" :disabled="meta.current_page >= meta.last_page" @click="fetchPage(meta.current_page + 1)">Berikutnya</Button>
            </div>
        </div>

        <Dialog :open="removeTarget !== null" title="Keluarkan dari komunitas?" @update:open="removeTarget = null">
            <p class="text-sm text-muted-foreground">
                {{ removeTarget?.person.name }} akan dikeluarkan dari komunitas. Data orang dan akunnya tetap ada,
                tapi ia harus memenuhi syarat kelulusan lagi untuk bisa bergabung kembali.
            </p>
            <Alert v-if="removeError" class="mt-3">{{ removeError }}</Alert>
            <div class="mt-4 flex justify-end gap-2">
                <Button variant="outline" size="sm" @click="removeTarget = null">Batal</Button>
                <Button variant="destructive" size="sm" :disabled="removing" @click="confirmRemove">
                    {{ removing ? 'Mengeluarkan…' : 'Keluarkan' }}
                </Button>
            </div>
        </Dialog>
    </div>
</template>
