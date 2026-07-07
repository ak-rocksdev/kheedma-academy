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

/**
 * Password visibility toggles: every password input on the public pages gains
 * an eye button that previews the value while filling it in.
 */
const EYE_ICON =
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>';
const EYE_OFF_ICON =
    '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.88 9.88a3 3 0 1 0 4.24 4.24"/><path d="M10.73 5.08A10.4 10.4 0 0 1 12 5c6.5 0 10 7 10 7a13.2 13.2 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.5 13.5 0 0 0 2 12s3.5 7 10 7a9.7 9.7 0 0 0 5.39-1.61"/><line x1="2" y1="2" x2="22" y2="22"/></svg>';

function initPasswordToggles() {
    document.querySelectorAll('input[type="password"]').forEach((input) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'password-field';
        // The input's own top margin (label spacing) must move to the wrapper,
        // otherwise the absolutely-centered toggle sits above the field.
        wrapper.style.marginTop = getComputedStyle(input).marginTop;
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);
        input.classList.add('password-field-input');
        input.style.marginTop = '0';

        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'password-toggle';
        toggle.setAttribute('aria-label', 'Tampilkan kata sandi');
        toggle.innerHTML = EYE_ICON;
        toggle.addEventListener('click', () => {
            const show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            toggle.innerHTML = show ? EYE_OFF_ICON : EYE_ICON;
            toggle.setAttribute('aria-label', show ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
        });
        wrapper.appendChild(toggle);
    });
}

/** Close the header account menu when clicking anywhere outside it. */
function initAccountMenu() {
    const menu = document.querySelector('details.account-menu');
    if (!menu) {
        return;
    }
    document.addEventListener('click', (event) => {
        if (menu.open && !menu.contains(event.target)) {
            menu.open = false;
        }
    });
}

/**
 * Conditional affiliate chain on the public forms: filling in a TikTok
 * username reveals the followers/started-affiliate questions, and answering
 * "Sudah" to started-affiliate reveals the level/GMV questions.
 */
function initAffiliateChain() {
    const tiktok = document.getElementById('tiktok_username');
    const dependents = document.querySelector('[data-tiktok-dependents]');
    if (!tiktok || !dependents) return;
    const affiliateDependents = document.querySelector('[data-affiliate-dependents]');

    function syncTiktok() {
        const has = tiktok.value.trim() !== '';
        dependents.classList.toggle('hidden', !has);
        if (!has && affiliateDependents) affiliateDependents.classList.add('hidden');
    }
    function syncStarted() {
        if (!affiliateDependents) return;
        const started = document.querySelector('input[name="has_started_affiliate"]:checked')?.value === '1';
        affiliateDependents.classList.toggle('hidden', !started);
    }
    tiktok.addEventListener('input', syncTiktok);
    document.querySelectorAll('input[name="has_started_affiliate"]').forEach((r) => r.addEventListener('change', syncStarted));
    syncTiktok();
    syncStarted();
}

/**
 * Social-follow nudge: clicking a TikTok/Instagram CTA reveals its check
 * mark; answering "Belum" to the pill question below nudges the user back
 * to the CTAs instead of silently accepting it.
 */
function initSocialFollow() {
    document.querySelectorAll('[data-social-cta]').forEach((cta) => {
        cta.addEventListener('click', () => {
            cta.querySelector('[data-social-check]')?.classList.remove('hidden');
        });
    });

    const nudge = document.querySelector('[data-follow-nudge]');
    const radios = document.querySelectorAll('input[name="followed_socials"]');
    if (!radios.length) return;

    function syncFollowNudge() {
        const checked = document.querySelector('input[name="followed_socials"]:checked')?.value;
        nudge?.classList.toggle('hidden', checked !== '0');
    }
    radios.forEach((r) => r.addEventListener('change', syncFollowNudge));
    syncFollowNudge();
}

initRegionSelects();
initSubmitOnce();
initPasswordToggles();
initAccountMenu();
initAffiliateChain();
initSocialFollow();
