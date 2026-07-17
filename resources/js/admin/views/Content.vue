<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { ArrowUp, ArrowDown, Pencil, Trash2, Plus } from 'lucide-vue-next';
import { contentSections as sectionsApi, programs as programsApi } from '@/api';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import SectionFormDialog from '@/components/SectionFormDialog.vue';

const programs = ref([]);
const selected = ref('community'); // 'community' | program id as string
const sections = ref([]);
const loading = ref(false);
const error = ref('');

const dialogOpen = ref(false);
const editingSection = ref(null);
const deletingId = ref(null);

const page = computed(() => (selected.value === 'community' ? 'community' : 'program'));
const programId = computed(() => (page.value === 'program' ? Number(selected.value) : null));

// Object shape for the reorder endpoint, which takes it as a JSON body.
const listParams = computed(() => ({
    page: page.value,
    ...(programId.value ? { program_id: programId.value } : {}),
}));

// contentSections.list() follows the codebase convention of taking a query
// string, not a params object, so build one from listParams for GET /index.
const listQuery = computed(() => {
    const params = new URLSearchParams(listParams.value);
    return `?${params.toString()}`;
});

async function loadPrograms() {
    const response = await programsApi.list();
    programs.value = response.data ?? [];
}

async function loadSections() {
    loading.value = true;
    error.value = '';
    try {
        const response = await sectionsApi.list(listQuery.value);
        sections.value = response.sections;
    } catch (e) {
        if (e.sessionExpired) return; // the global re-login dialog takes over
        error.value = e.message ?? 'Gagal memuat konten.';
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    loadPrograms();
    loadSections();
});
// Switching pages disarms any pending two-click delete: without this, the arm
// survives the switch and one click after switching back deletes with no confirm.
watch(selected, () => {
    deletingId.value = null;
    loadSections();
});

function openCreate() {
    editingSection.value = null;
    dialogOpen.value = true;
}

function openEdit(section) {
    editingSection.value = section;
    dialogOpen.value = true;
}

async function removeSection(section) {
    error.value = '';
    try {
        await sectionsApi.remove(section.id);
        await loadSections();
    } catch (e) {
        if (!e.sessionExpired) error.value = e.message ?? 'Gagal menghapus section.';
    } finally {
        deletingId.value = null;
    }
}

async function move(index, delta) {
    error.value = '';
    const ids = sections.value.map((s) => s.id);
    const [id] = ids.splice(index, 1);
    ids.splice(index + delta, 0, id);
    try {
        await sectionsApi.reorder({ ...listParams.value, ids });
        await loadSections();
    } catch (e) {
        if (!e.sessionExpired) error.value = e.message ?? 'Gagal mengubah urutan.';
    }
}
</script>

<template>
    <div>
        <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Konten Halaman</h1>
                <p class="text-sm text-muted-foreground">
                    Susun kartu konten yang tampil di halaman publik. Urutan di sini = urutan tampil.
                </p>
            </div>
            <Button @click="openCreate"><Plus class="mr-1.5 h-4 w-4" /> Tambah Section</Button>
        </div>

        <div class="mb-4 max-w-xs">
            <label class="mb-1.5 block text-sm font-medium">Halaman</label>
            <NativeSelect v-model="selected">
                <option value="community">Komunitas (/komunitas)</option>
                <option v-for="program in programs" :key="program.id" :value="String(program.id)">
                    Program: {{ program.name }}
                </option>
            </NativeSelect>
        </div>

        <p v-if="error" class="mb-4 rounded-md border border-destructive/30 bg-destructive/10 px-3 py-2 text-sm text-destructive">
            {{ error }}
        </p>

        <div class="space-y-3">
            <div
                v-for="(section, index) in sections"
                :key="section.id"
                class="rounded-lg border bg-card p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h2 v-if="section.heading" class="font-semibold">{{ section.heading }}</h2>
                        <p v-else class="text-sm italic text-muted-foreground">(tanpa judul)</p>
                        <!-- v-html: admin-authored, server-sanitized body HTML — same trust level as the public page. -->
                        <div class="kh-prose mt-2 max-h-32 overflow-hidden rounded bg-white p-3" v-html="section.body" />
                    </div>
                    <div class="flex shrink-0 gap-1">
                        <Button variant="ghost" size="icon" class="h-8 w-8" title="Naikkan" :disabled="index === 0" @click="move(index, -1)">
                            <ArrowUp class="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="icon" class="h-8 w-8" title="Turunkan" :disabled="index === sections.length - 1" @click="move(index, 1)">
                            <ArrowDown class="h-4 w-4" />
                        </Button>
                        <Button variant="ghost" size="icon" class="h-8 w-8" title="Ubah" @click="openEdit(section)">
                            <Pencil class="h-4 w-4" />
                        </Button>
                        <Button
                            variant="ghost" size="icon" class="h-8 w-8 text-destructive"
                            :title="deletingId === section.id ? 'Klik lagi untuk hapus' : 'Hapus'"
                            @click="deletingId === section.id ? removeSection(section) : (deletingId = section.id)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <p v-if="!loading && sections.length === 0" class="mt-8 text-center text-sm text-muted-foreground">
            Belum ada konten di halaman ini. Klik "Tambah Section" untuk memulai.
        </p>

        <SectionFormDialog
            v-model:open="dialogOpen"
            :section="editingSection"
            :page="page"
            :program-id="programId"
            @saved="loadSections"
        />
    </div>
</template>
