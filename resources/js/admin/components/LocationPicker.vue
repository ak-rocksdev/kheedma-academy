<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useGooglePlaces } from '@/composables/useGooglePlaces';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

/**
 * Offline-cohort location field: a Google Places autocomplete (when the
 * admin-only API key is configured) that resolves to a flat
 * { name, address, lat, lng } object, with a manual-entry fallback for
 * keyless environments or when the Places element fails to load.
 */
const props = defineProps({ class: { type: null, default: '' } });

const model = defineModel({
    type: Object,
    default: () => ({ name: '', address: '', lat: null, lng: null }),
});

const { error: placesError, loadPlaces } = useGooglePlaces();

// Manual mode is the permanent fallback when Places never becomes usable
// (no key, or the element failed to attach); otherwise it's a user choice.
const manualMode = ref(false);
const placesUsable = ref(false);
const autocompleteHost = ref(null);
let autocompleteEl = null;
// The dialog can unmount this component while loadPlaces() is still pending;
// this flag stops the mount continuation from attaching after teardown.
let disposed = false;

// A 0 coordinate is valid (equator/meridian); only null/'' mean "not set".
const hasLocation = computed(() => Boolean(model.value.address) || (model.value.lat != null && model.value.lat !== ''));

async function handleSelect({ placePrediction }) {
    try {
        const place = placePrediction.toPlace();
        await place.fetchFields({ fields: ['displayName', 'formattedAddress', 'location'] });
        model.value = {
            name: place.displayName ?? '',
            address: place.formattedAddress ?? '',
            lat: place.location ? place.location.lat() : null,
            lng: place.location ? place.location.lng() : null,
        };
    } catch {
        // Selection didn't resolve to full place data; leave the model as-is,
        // the admin can still switch to manual entry.
    }
}

onMounted(async () => {
    const places = await loadPlaces();
    if (disposed) return;
    if (!places) {
        manualMode.value = true;
        return;
    }

    try {
        const { PlaceAutocompleteElement } = places;
        autocompleteEl = new PlaceAutocompleteElement();
        autocompleteEl.addEventListener('gmp-select', handleSelect);
        autocompleteHost.value?.appendChild(autocompleteEl);
        placesUsable.value = true;
    } catch {
        manualMode.value = true;
    }
});

onBeforeUnmount(() => {
    disposed = true;
    autocompleteEl?.removeEventListener('gmp-select', handleSelect);
});
</script>

<template>
    <div :class="cn('space-y-2', props.class)">
        <template v-if="!manualMode">
            <label class="text-xs text-muted-foreground">Cari tempat…</label>
            <div ref="autocompleteHost" class="mt-1.5"></div>

            <div v-if="hasLocation" class="mt-2 rounded-md border border-border bg-accent/30 px-3 py-2 text-sm">
                <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Lokasi terpilih</p>
                <p class="font-medium text-foreground">{{ model.name || '—' }}</p>
                <p class="text-muted-foreground">{{ model.address }}</p>
                <p v-if="model.lat != null && model.lng != null" class="mt-1 text-xs text-muted-foreground/70">{{ model.lat }}, {{ model.lng }}</p>
            </div>

            <p v-if="placesError" class="mt-1.5 text-xs text-muted-foreground">{{ placesError }}</p>
        </template>

        <template v-else>
            <div>
                <label class="text-xs text-muted-foreground">Nama tempat</label>
                <Input v-model="model.name" placeholder="Nama tempat (opsional)" class="mt-1.5" />
            </div>
            <div>
                <label class="text-xs text-muted-foreground">Alamat lengkap</label>
                <Textarea v-model="model.address" rows="2" placeholder="Alamat lengkap" class="mt-1.5" />
            </div>
            <div class="flex gap-3">
                <div class="min-w-0 flex-1">
                    <label class="text-xs text-muted-foreground">Latitude</label>
                    <Input v-model="model.lat" type="number" step="any" placeholder="-6.200000" class="mt-1.5" />
                </div>
                <div class="min-w-0 flex-1">
                    <label class="text-xs text-muted-foreground">Longitude</label>
                    <Input v-model="model.lng" type="number" step="any" placeholder="106.816666" class="mt-1.5" />
                </div>
            </div>
            <p v-if="placesError" class="text-xs text-muted-foreground">{{ placesError }}</p>
        </template>

        <button v-if="placesUsable" type="button" class="text-xs font-medium text-primary hover:underline" @click="manualMode = !manualMode">
            {{ manualMode ? 'Gunakan pencarian tempat' : 'Ubah manual' }}
        </button>
    </div>
</template>
