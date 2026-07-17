<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useGooglePlaces } from '@/composables/useGooglePlaces';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

/**
 * Offline-cohort location field: Places autocomplete resolves a place, then
 * an interactive map with a draggable pin (click to drop, drag to fine-tune,
 * scroll to zoom) owns the exact coordinates. Lat/lng are never typed when
 * the map is available — a status label says whether the point is set.
 * Keyless/map-failure environments fall back to manual inputs.
 */
const props = defineProps({ class: { type: null, default: '' } });

const model = defineModel({
    type: Object,
    default: () => ({ name: '', address: '', lat: null, lng: null }),
});

const { error: placesError, loadPlaces, loadMapKit } = useGooglePlaces();

// Manual mode is the permanent fallback when Places never becomes usable
// (no key, or the element failed to attach); otherwise it's a user choice.
const manualMode = ref(false);
const placesUsable = ref(false);
const mapUsable = ref(false);
const autocompleteHost = ref(null);
const mapHost = ref(null);
let autocompleteEl = null;
let map = null;
let marker = null;
let AdvancedMarker = null;
// The dialog can unmount this component while the async loads are pending;
// this flag stops the mount continuation from attaching after teardown.
let disposed = false;

// Kantor Kheedma neighbourhood (Pasar Kliwon, Surakarta) — a sensible map
// start before any point exists.
const FALLBACK_CENTER = { lat: -7.5755, lng: 110.8317 };

// A 0 coordinate is valid (equator/meridian); only null/'' mean "not set".
const hasPoint = computed(
    () => model.value.lat != null && model.value.lat !== '' && model.value.lng != null && model.value.lng !== ''
);
const hasLocation = computed(() => Boolean(model.value.address) || hasPoint.value);

function currentPoint() {
    return hasPoint.value ? { lat: Number(model.value.lat), lng: Number(model.value.lng) } : null;
}

function setPoint(lat, lng) {
    model.value = {
        ...model.value,
        lat: Number(lat.toFixed(7)),
        lng: Number(lng.toFixed(7)),
    };
}

/** Keeps the pin in sync with the model (drop, move, or remove). */
function syncMarker({ pan = false } = {}) {
    if (!map) return;
    const point = currentPoint();
    if (!point) {
        if (marker) marker.map = null;
        marker = null;
        return;
    }
    if (!marker) {
        marker = new AdvancedMarker({ map, position: point, gmpDraggable: true });
        marker.addListener('dragend', (event) => {
            if (event.latLng) setPoint(event.latLng.lat(), event.latLng.lng());
        });
    } else {
        marker.position = point;
    }
    if (pan) {
        map.panTo(point);
        if (map.getZoom() < 15) map.setZoom(16);
    }
}

watch(hasPoint, () => syncMarker());

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
        syncMarker({ pan: true });
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
    } else {
        try {
            const { PlaceAutocompleteElement } = places;
            autocompleteEl = new PlaceAutocompleteElement();
            autocompleteEl.addEventListener('gmp-select', handleSelect);
            autocompleteHost.value?.appendChild(autocompleteEl);
            placesUsable.value = true;
        } catch {
            manualMode.value = true;
        }
    }

    const kit = await loadMapKit();
    if (disposed || !kit) return;
    AdvancedMarker = kit.AdvancedMarkerElement;
    map = new kit.Map(mapHost.value, {
        center: currentPoint() ?? FALLBACK_CENTER,
        zoom: currentPoint() ? 16 : 12,
        mapId: 'DEMO_MAP_ID', // required by AdvancedMarkerElement; default styling
        gestureHandling: 'greedy', // plain mouse scroll zooms, no Ctrl needed
        streetViewControl: false,
        mapTypeControl: false,
        fullscreenControl: false,
        clickableIcons: false,
    });
    map.addListener('click', (event) => {
        if (event.latLng) setPoint(event.latLng.lat(), event.latLng.lng());
    });
    mapUsable.value = true;
    syncMarker();
});

onBeforeUnmount(() => {
    disposed = true;
    autocompleteEl?.removeEventListener('gmp-select', handleSelect);
});
</script>

<template>
    <div :class="cn('space-y-2', props.class)">
        <template v-if="!manualMode">
            <!-- Bordered like Input so the element reads as a field even in
                 its bare idle state (it only paints its own box on focus). -->
            <div
                ref="autocompleteHost"
                class="mt-1.5 rounded-md border border-input bg-background px-1 focus-within:ring-2 focus-within:ring-ring [&>gmp-place-autocomplete]:block [&>gmp-place-autocomplete]:w-full"
            ></div>

            <div v-if="hasLocation" class="mt-2 rounded-md border border-border bg-accent/30 px-3 py-2 text-sm">
                <p class="text-xs font-semibold tracking-wide text-muted-foreground uppercase">Lokasi terpilih</p>
                <p class="font-medium text-foreground">{{ model.name || '—' }}</p>
                <p class="text-muted-foreground">{{ model.address }}</p>
            </div>
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
            <!-- Typed coordinates are the last resort for environments where
                 the map itself cannot load; with a map, the pin is the input. -->
            <div v-if="!mapUsable" class="flex gap-3">
                <div class="min-w-0 flex-1">
                    <label class="text-xs text-muted-foreground">Latitude</label>
                    <Input v-model="model.lat" type="number" step="any" placeholder="-6.200000" class="mt-1.5" />
                </div>
                <div class="min-w-0 flex-1">
                    <label class="text-xs text-muted-foreground">Longitude</label>
                    <Input v-model="model.lng" type="number" step="any" placeholder="106.816666" class="mt-1.5" />
                </div>
            </div>
        </template>

        <div v-show="mapUsable" class="mt-2 overflow-hidden rounded-md border border-border">
            <div ref="mapHost" class="h-64 w-full"></div>
        </div>
        <p v-if="mapUsable" class="text-xs text-muted-foreground">
            Titik lokasi:
            <span v-if="hasPoint" class="font-semibold text-primary">sudah dipilih ✓</span>
            <span v-else class="font-semibold text-orange-600">belum dipilih</span>
            · klik peta untuk meletakkan pin, geser pin untuk menyesuaikan, scroll untuk zoom.
        </p>

        <p v-if="placesError" class="text-xs text-muted-foreground">{{ placesError }}</p>

        <button v-if="placesUsable" type="button" class="text-xs font-medium text-primary hover:underline" @click="manualMode = !manualMode">
            {{ manualMode ? 'Gunakan pencarian tempat' : 'Ubah manual' }}
        </button>
    </div>
</template>
