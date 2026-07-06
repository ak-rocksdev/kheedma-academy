<script setup>
import { ref, onMounted } from 'vue';
import { programs as programsApi } from '@/api';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';

const items = ref([]);
const loading = ref(false);
const error = ref('');

const dialogOpen = ref(false);
const editing = ref(null);
const form = ref({ name: '', slug: '', tagline: '', description: '', status: 'draft', selection_mode: 'selective', registration_opens_at: '', registration_closes_at: '' });
const formErrors = ref({});
const saving = ref(false);

const selectClass =
    'h-9 w-full rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

const STATUS = {
    draft: { label: 'Draf', variant: 'secondary' },
    active: { label: 'Aktif', variant: 'success' },
    inactive: { label: 'Nonaktif', variant: 'destructive' },
};

function slugify(text) {
    return text.toLowerCase().trim().replace(/[^a-z0-9\s-]/g, '').replace(/[\s-]+/g, '-').replace(/^-|-$/g, '');
}

async function load() {
    loading.value = true;
    error.value = '';
    try {
        const res = await programsApi.list();
        items.value = res.data;
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        error.value = e.message ?? 'Gagal memuat data.';
    } finally {
        loading.value = false;
    }
}

onMounted(load);

function openCreate() {
    editing.value = null;
    form.value = { name: '', slug: '', tagline: '', description: '', status: 'draft', selection_mode: 'selective', registration_opens_at: '', registration_closes_at: '' };
    formErrors.value = {};
    dialogOpen.value = true;
}

function openEdit(program) {
    editing.value = program;
    form.value = {
        name: program.name,
        slug: program.slug,
        tagline: program.tagline ?? '',
        description: program.description ?? '',
        status: program.status,
        selection_mode: program.selection_mode,
        registration_opens_at: program.registration_opens_at?.slice(0, 10) ?? '',
        registration_closes_at: program.registration_closes_at?.slice(0, 10) ?? '',
    };
    formErrors.value = {};
    dialogOpen.value = true;
}

function onNameInput() {
    // Auto-suggest the slug only while creating and before manual edits diverge.
    if (!editing.value && (!form.value.slug || form.value.slug === slugify(form.value.name.slice(0, -1)))) {
        form.value.slug = slugify(form.value.name);
    }
}

async function save() {
    saving.value = true;
    formErrors.value = {};
    try {
        const payload = {
            name: form.value.name,
            slug: form.value.slug,
            tagline: form.value.tagline || null,
            description: form.value.description || null,
            status: form.value.status,
            selection_mode: form.value.selection_mode,
            registration_opens_at: form.value.registration_opens_at || null,
            registration_closes_at: form.value.registration_closes_at || null,
        };
        if (editing.value) {
            await programsApi.update(editing.value.id, payload);
        } else {
            await programsApi.create(payload);
        }
        dialogOpen.value = false;
        await load();
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        formErrors.value = e.errors ?? {};
        if (!Object.keys(formErrors.value).length) error.value = e.message;
    } finally {
        saving.value = false;
    }
}

async function remove(program) {
    error.value = '';
    try {
        await programsApi.remove(program.id);
        await load();
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        error.value = e.message ?? 'Gagal menghapus program.';
    }
}

function fmtDate(iso) {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
}
</script>

<template>
    <div class="mx-auto max-w-6xl">
        <div class="flex items-end justify-between gap-4">
            <div>
                <p class="font-display text-xs uppercase tracking-[0.3em] text-orange-600">Program</p>
                <h1 class="mt-2 text-2xl font-bold text-foreground">Katalog Program</h1>
            </div>
            <Button variant="accent" size="sm" @click="openCreate">Tambah Program</Button>
        </div>

        <div v-if="error" class="mt-4 rounded-lg border border-destructive/30 bg-red-50 px-4 py-3 text-sm text-destructive">
            {{ error }}
        </div>

        <div class="mt-5 overflow-hidden rounded-xl border border-border bg-card">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                        <th class="px-4 py-3 font-semibold">Nama</th>
                        <th class="px-4 py-3 font-semibold">Slug</th>
                        <th class="px-4 py-3 font-semibold">Status</th>
                        <th class="px-4 py-3 font-semibold">Pendaftaran</th>
                        <th class="px-4 py-3 font-semibold">Angkatan</th>
                        <th class="px-4 py-3 font-semibold">Pendaftar</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="loading"><td colspan="7" class="px-4 py-10 text-center text-muted-foreground">Memuat…</td></tr>
                    <tr v-else-if="!items.length"><td colspan="7" class="px-4 py-10 text-center text-muted-foreground">Belum ada program.</td></tr>
                    <tr v-for="program in items" :key="program.id" class="border-b border-border last:border-0">
                        <td class="px-4 py-3 font-medium text-foreground">{{ program.name }}</td>
                        <td class="px-4 py-3 text-muted-foreground"><code class="text-xs">/program/{{ program.slug }}</code></td>
                        <td class="px-4 py-3">
                            <Badge :variant="STATUS[program.status]?.variant ?? 'secondary'">
                                {{ STATUS[program.status]?.label ?? program.status }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3">
                            <Badge :variant="program.is_open ? 'success' : 'secondary'">
                                {{ program.is_open ? 'Buka' : 'Tutup' }}
                            </Badge>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">{{ program.cohorts_count }}</td>
                        <td class="px-4 py-3 text-muted-foreground">{{ program.applications_count }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <Button variant="ghost" size="sm" @click="openEdit(program)">Ubah</Button>
                            <Button variant="ghost" size="sm" @click="remove(program)">Hapus</Button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <Dialog v-model:open="dialogOpen" :title="editing ? 'Ubah Program' : 'Tambah Program'">
            <form class="space-y-3" @submit.prevent="save">
                <div>
                    <Input v-model="form.name" placeholder="Nama program" @input="onNameInput" />
                    <p v-if="formErrors.name" class="mt-1 text-xs text-destructive">{{ formErrors.name[0] }}</p>
                </div>
                <div>
                    <Input v-model="form.slug" placeholder="slug-url" />
                    <p class="mt-1 text-xs text-muted-foreground">/program/{{ form.slug || '…' }}</p>
                    <p v-if="formErrors.slug" class="mt-1 text-xs text-destructive">{{ formErrors.slug[0] }}</p>
                </div>
                <Input v-model="form.tagline" placeholder="Tagline singkat (opsional)" />
                <div>
                    <textarea
                        v-model="form.description"
                        rows="4"
                        placeholder="Deskripsi program (opsional)"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    ></textarea>
                    <p v-if="formErrors.description" class="mt-1 text-xs text-destructive">{{ formErrors.description[0] }}</p>
                </div>
                <div class="flex gap-3">
                    <div class="flex-1">
                        <label class="text-xs text-muted-foreground">Status</label>
                        <select v-model="form.status" :class="selectClass">
                            <option value="draft">Draf</option>
                            <option value="active">Aktif</option>
                            <option value="inactive">Nonaktif</option>
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="text-xs text-muted-foreground">Mode seleksi</label>
                        <select v-model="form.selection_mode" :class="selectClass">
                            <option value="selective">Selektif (dinilai admin)</option>
                            <option value="instant">Langsung masuk</option>
                        </select>
                    </div>
                </div>
                <div class="flex gap-3">
                    <div class="flex-1">
                        <label class="text-xs text-muted-foreground">Pendaftaran dibuka</label>
                        <Input v-model="form.registration_opens_at" type="date" />
                    </div>
                    <div class="flex-1">
                        <label class="text-xs text-muted-foreground">Pendaftaran ditutup</label>
                        <Input v-model="form.registration_closes_at" type="date" />
                    </div>
                </div>
                <p v-if="formErrors.registration_closes_at" class="text-xs text-destructive">{{ formErrors.registration_closes_at[0] }}</p>
                <div class="flex justify-end gap-2 pt-2">
                    <Button type="button" variant="outline" size="sm" @click="dialogOpen = false">Batal</Button>
                    <Button type="submit" size="sm" :disabled="saving">{{ saving ? 'Menyimpan…' : 'Simpan' }}</Button>
                </div>
            </form>
        </Dialog>
    </div>
</template>
