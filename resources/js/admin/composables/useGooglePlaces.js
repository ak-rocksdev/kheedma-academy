import { readonly, ref } from 'vue';

/**
 * Lazy Google Maps JS bootstrap, shared by every LocationPicker instance.
 *
 * Module-scope state means the bootstrap script and the `places` library are
 * fetched at most once per page session no matter how many components call
 * loadPlaces() (dialog re-opens, multiple pickers, etc).
 */
const ready = ref(false);
const error = ref('');
let placesLibraryPromise = null;

/**
 * Google's official inline bootstrap loader (unminified), scoped to a
 * function so it only ever runs once via the placesLibraryPromise cache
 * below. See https://developers.google.com/maps/documentation/javascript/load-maps-js-api
 *
 * @param {string} apiKey
 */
function injectBootstrapLoader(apiKey) {
    ((g) => {
        let h;
        let a;
        let k;
        const p = 'The Google Maps JavaScript API';
        const c = 'google';
        const l = 'importLibrary';
        const q = '__ib__';
        const m = document;
        let b = window;
        b = b[c] || (b[c] = {});
        const d = b.maps || (b.maps = {});
        const r = new Set();
        const e = new URLSearchParams();
        const u = () =>
            h ||
            (h = new Promise(async (resolve, reject) => {
                await (a = m.createElement('script'));
                e.set('libraries', [...r] + '');
                for (k in g) {
                    e.set(k.replace(/[A-Z]/g, (t) => '_' + t[0].toLowerCase()), g[k]);
                }
                e.set('callback', c + '.maps.' + q);
                a.src = `https://maps.${c}apis.com/maps/api/js?` + e;
                d[q] = resolve;
                a.onerror = () => (h = reject(new Error(p + ' could not load.')));
                a.nonce = m.querySelector('script[nonce]')?.nonce || '';
                m.head.append(a);
            }));
        d[l] ? console.warn(p + ' only loads once. Ignoring:', g) : (d[l] = (f, ...n) => r.add(f) && u().then(() => d[l](f, ...n)));
    })({ key: apiKey, v: 'weekly' });
}

/**
 * @returns {{ready: import('vue').Readonly<import('vue').Ref<boolean>>, error: import('vue').Readonly<import('vue').Ref<string>>, loadPlaces: () => Promise<google.maps.places.PlacesLibrary|null>}}
 */
export function useGooglePlaces() {
    /**
     * Resolves the `places` library, bootstrapping the Maps JS API on first
     * call. Never throws: on any failure (missing key, network, API error)
     * it sets `error` and resolves to null so callers fall back to manual input.
     */
    function loadPlaces() {
        if (placesLibraryPromise) {
            return placesLibraryPromise;
        }

        const key = document.querySelector('meta[name="google-maps-key"]')?.content?.trim();
        if (!key) {
            error.value = 'Pencarian tempat otomatis tidak tersedia. Isi lokasi secara manual di bawah.';
            placesLibraryPromise = Promise.resolve(null);
            return placesLibraryPromise;
        }

        placesLibraryPromise = (async () => {
            try {
                injectBootstrapLoader(key);
                const places = await window.google.maps.importLibrary('places');
                ready.value = true;
                return places;
            } catch {
                error.value = 'Gagal memuat pencarian tempat. Isi lokasi secara manual di bawah.';
                return null;
            }
        })();

        return placesLibraryPromise;
    }

    return {
        ready: readonly(ready),
        error: readonly(error),
        loadPlaces,
    };
}
