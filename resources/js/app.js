// Public site enhancements (Blade pages). Currently: searchable region
// selects on the application form.

import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.css';

/** Turn a native select into a searchable single-value combobox. */
function makeSearchable(el, placeholder) {
    const instance = new TomSelect(el, {
        maxItems: 1,
        create: false,
        allowEmptyOption: false,
        placeholder,
        // Keep the server's alphabetical order instead of re-sorting by score.
        sortField: [{ field: '$order' }],
        render: {
            no_results: () => '<div class="no-results">Tidak ditemukan. Coba kata lain.</div>',
        },
    });

    // Chrome ignores autocomplete="off" for address-like fields and overlays
    // its own autofill popup on the search input. A readonly-until-focus input
    // plus password-manager ignore hints is the reliable way to suppress it.
    const input = instance.control_input;
    input.setAttribute('autocomplete', 'off');
    input.setAttribute('autocorrect', 'off');
    input.setAttribute('autocapitalize', 'off');
    input.setAttribute('spellcheck', 'false');
    input.setAttribute('data-lpignore', 'true');
    input.setAttribute('data-1p-ignore', 'true');
    input.setAttribute('readonly', '');
    input.addEventListener('focus', () => input.removeAttribute('readonly'));
    input.addEventListener('blur', () => input.setAttribute('readonly', ''));

    return instance;
}

/**
 * Province -> city dependent pair on the application form. The city list is
 * fetched per province; old() values survive a validation redirect.
 */
function initRegionSelects() {
    const provinceEl = document.getElementById('province_code');
    const cityEl = document.getElementById('city_code');
    if (!provinceEl || !cityEl) {
        return;
    }

    const citiesBase = cityEl.dataset.citiesUrl;
    const province = makeSearchable(provinceEl, 'Pilih provinsi…');
    const city = makeSearchable(cityEl, 'Pilih kota/kabupaten…');
    city.disable();

    async function loadCities(code, selected = '') {
        city.clear(true);
        city.clearOptions();
        city.disable();
        if (!code) {
            return;
        }
        try {
            const res = await fetch(`${citiesBase}/${code}`, { headers: { Accept: 'application/json' } });
            const list = await res.json();
            city.addOptions(list.map((c) => ({ value: c.code, text: c.name })));
            city.enable();
            if (selected) {
                city.setValue(selected, true);
            }
        } catch {
            city.disable();
        }
    }

    province.on('change', (value) => loadCities(value));

    // Repopulate after a validation redirect (old input).
    if (province.getValue()) {
        loadCities(province.getValue(), cityEl.dataset.old || '');
    }
}

/**
 * Double-submit guard: once a marked form submits, lock the button so an
 * impatient double click cannot POST twice.
 */
function initSubmitOnce() {
    document.querySelectorAll('form[data-submit-once]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('button[type="submit"]');
            if (button && !button.disabled) {
                button.disabled = true;
                button.classList.add('opacity-60', 'cursor-not-allowed');
                button.textContent = 'Mengirim…';
            }
        });
    });
}

initRegionSelects();
initSubmitOnce();
