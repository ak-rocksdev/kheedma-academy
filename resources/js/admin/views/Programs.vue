<script setup>
import { ref, computed, onMounted } from 'vue';
import { programs as programsApi } from '@/api';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog } from '@/components/ui/dialog';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

const items = ref([]);
const loading = ref(false);
const error = ref('');

const dialogOpen = ref(false);
const editing = ref(null);
const form = ref({
    name: '',
    slug: '',
    tagline: '',
    description: '',
    status: 'draft',
    selection_mode: 'selective',
    type: 'general',
    level: '',
    locked_message: '',
});
const formErrors = ref({});
const saving = ref(false);
const thumbError = ref('');

const STATUS = {
    draft: { label: 'Draf', variant: 'secondary' },
    active: { label: 'Aktif', variant: 'success' },
    inactive: { label: 'Nonaktif', variant: 'destructive' },
};

const STATUS_OPTIONS = [
    { value: 'draft', label: 'Draf' },
    { value: 'active', label: 'Aktif' },
    { value: 'inactive', label: 'Nonaktif' },
];

const MODE_OPTIONS = [
    { value: 'selective', label: 'Selektif' },
    { value: 'instant', label: 'Langsung masuk' },
];

const TYPE_OPTIONS = [
    { value: 'general', label: 'Program Umum' },
    { value: 'affiliate_community', label: 'Affiliate Community' },
];

/** The slug is derived, never typed: live from the name on create, frozen on edit. */
const previewSlug = computed(() => (editing.value ? editing.value.slug : form.value.slug));

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
    form.value = {
        name: '',
        slug: '',
        tagline: '',
        description: '',
        status: 'draft',
        selection_mode: 'selective',
        type: 'general',
        level: '',
        locked_message: '',
    };
    formErrors.value = {};
    thumbError.value = '';
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
        type: program.type,
        level: program.level ?? '',
        locked_message: program.locked_message ?? '',
    };
    formErrors.value = {};
    thumbError.value = '';
    dialogOpen.value = true;
}

function onNameInput() {
    // The slug always follows the name while creating; edits never touch it
    // (published URLs must stay stable).
    if (!editing.value) {
        form.value.slug = slugify(form.value.name);
    }
}

/** ToggleGroup allows deselect-to-empty; these options are mandatory, so ignore it. */
function setStatus(value) {
    if (value) form.value.status = value;
}

function setSelectionMode(value) {
    if (value) form.value.selection_mode = value;
}

function setType(value) {
    if (value) {
        form.value.type = value;
        if (value === 'general') {
            form.value.level = '';
            form.value.locked_message = '';
        }
    }
}

async function save() {
    saving.value = true;
    formErrors.value = {};
    try {
        const payload = {
            name: form.value.name,
            tagline: form.value.tagline || null,
            description: form.value.description || null,
            status: form.value.status,
            selection_mode: form.value.selection_mode,
            type: form.value.type,
            level: form.value.type === 'affiliate_community' ? Number(form.value.level) : undefined,
            locked_message: form.value.locked_message || null,
        };
        if (editing.value) {
            // Slug omitted on purpose: it never changes once published.
            await programsApi.update(editing.value.id, payload);
        } else {
            await programsApi.create({ ...payload, slug: form.value.slug });
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

async function uploadThumbnail(event) {
    const file = event.target.files?.[0];
    if (!file || !editing.value) return;
    thumbError.value = '';
    try {
        const res = await programsApi.uploadThumbnail(editing.value.id, file);
        editing.value.thumbnail_url = res.program.thumbnail_url;
        await load();
    } catch (e) {
        if (!e.sessionExpired) thumbError.value = e.errors?.thumbnail?.[0] ?? e.message;
    } finally {
        event.target.value = '';
    }
}

async function removeThumbnail() {
    thumbError.value = '';
    try {
        await programsApi.removeThumbnail(editing.value.id);
        editing.value.thumbnail_url = null;
        await load();
    } catch (e) {
        if (!e.sessionExpired) thumbError.value = e.message;
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
                        <td class="px-4 py-3 font-medium text-foreground">
                            <div class="flex items-center gap-2">
                                <img v-if="program.thumbnail_url" :src="program.thumbnail_url" alt="" class="h-8 w-14 rounded object-cover" />
                                {{ program.name }}
                            </div>
                        </td>
                        <td class="px-4 py-3 text-muted-foreground"><code class="text-xs">/program/{{ program.slug }}</code></td>
                        <td class="px-4 py-3">
                            <Badge :variant="STATUS[program.status]?.variant ?? 'secondary'">
                                {{ STATUS[program.status]?.label ?? program.status }}
                            </Badge>
                            <Badge variant="secondary" class="ml-1">
                                {{ program.type === 'affiliate_community' ? 'Affiliate L' + program.level : 'Umum' }}
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
            <form class="flex flex-col gap-4" @submit.prevent="save">
                <div>
                    <Input v-model="form.name" placeholder="Nama program" @input="onNameInput" />
                    <p class="mt-1.5 text-xs text-muted-foreground">
                        <code>/program/{{ previewSlug || '…' }}</code>
                        <span v-if="editing" class="ml-1">· URL tidak berubah saat nama diedit</span>
                    </p>
                    <p v-if="formErrors.name" class="mt-1 text-xs text-destructive">{{ formErrors.name[0] }}</p>
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
                <div>
                    <label class="text-xs text-muted-foreground">Status</label>
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        class="mt-1.5 w-full"
                        :model-value="form.status"
                        @update:model-value="setStatus"
                    >
                        <ToggleGroupItem v-for="option in STATUS_OPTIONS" :key="option.value" :value="option.value" class="flex-1">
                            {{ option.label }}
                        </ToggleGroupItem>
                    </ToggleGroup>
                </div>
                <div>
                    <label class="text-xs text-muted-foreground">Mode seleksi</label>
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        class="mt-1.5 w-full"
                        :model-value="form.selection_mode"
                        @update:model-value="setSelectionMode"
                    >
                        <ToggleGroupItem v-for="option in MODE_OPTIONS" :key="option.value" :value="option.value" class="flex-1">
                            {{ option.label }}
                        </ToggleGroupItem>
                    </ToggleGroup>
                </div>
                <div>
                    <label class="text-xs text-muted-foreground">Tipe</label>
                    <ToggleGroup
                        type="single"
                        variant="outline"
                        class="mt-1.5 w-full"
                        :model-value="form.type"
                        @update:model-value="setType"
                    >
                        <ToggleGroupItem v-for="option in TYPE_OPTIONS" :key="option.value" :value="option.value" class="flex-1">
                            {{ option.label }}
                        </ToggleGroupItem>
                    </ToggleGroup>
                </div>
                <div v-if="form.type === 'affiliate_community'">
                    <label class="text-xs text-muted-foreground">Level</label>
                    <Input v-model="form.level" type="number" min="1" max="255" placeholder="1" class="mt-1.5" />
                    <p v-if="formErrors.level" class="mt-1 text-xs text-destructive">{{ formErrors.level[0] }}</p>
                </div>
                <div v-if="form.type === 'affiliate_community'">
                    <label class="text-xs text-muted-foreground">Pesan terkunci (opsional)</label>
                    <textarea
                        v-model="form.locked_message"
                        rows="3"
                        placeholder="Kosongkan untuk memakai pesan default."
                        class="mt-1.5 w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                    ></textarea>
                </div>
                <div v-if="editing">
                    <label class="text-xs text-muted-foreground">Thumbnail kelas</label>
                    <div class="mt-1.5 flex items-center gap-3">
                        <img v-if="editing.thumbnail_url" :src="editing.thumbnail_url" alt="" class="h-14 w-24 rounded-lg object-cover" />
                        <div v-else class="flex h-14 w-24 items-center justify-center rounded-lg border border-dashed border-input text-[0.6rem] uppercase tracking-wide text-muted-foreground">Otomatis</div>
                        <input ref="thumbInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="uploadThumbnail" />
                        <Button type="button" variant="outline" size="sm" @click="$refs.thumbInput.click()">{{ editing.thumbnail_url ? 'Ganti' : 'Unggah' }}</Button>
                        <Button v-if="editing.thumbnail_url" type="button" variant="ghost" size="sm" @click="removeThumbnail">Hapus</Button>
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">JPEG/PNG/WebP, maks 2 MB. Kosong = cover otomatis bermotif brand.</p>
                    <p v-if="thumbError" class="mt-1 text-xs text-destructive">{{ thumbError }}</p>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <Button type="button" variant="outline" size="sm" @click="dialogOpen = false">Batal</Button>
                    <Button type="submit" size="sm" :disabled="saving">{{ saving ? 'Menyimpan…' : 'Simpan' }}</Button>
                </div>
            </form>
        </Dialog>
    </div>
</template>
