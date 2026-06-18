// Minimal Sanctum SPA client (cookie + CSRF) built on fetch — no extra deps.

function getCookie(name) {
    const match = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
    return match ? decodeURIComponent(match.pop()) : '';
}

/** Prime the XSRF-TOKEN cookie before any state-changing request. */
async function csrf() {
    await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
}

/**
 * Call the admin API. Sends cookies + the X-XSRF-TOKEN header. On a non-OK
 * response throws an error carrying { status, message, errors }.
 */
export async function api(path, { method = 'GET', body = null } = {}) {
    // For state-changing requests, make sure the XSRF cookie exists (it may be
    // missing/expired even when the session is still valid) to avoid a 419.
    const mutating = ['POST', 'PUT', 'PATCH', 'DELETE'].includes(method.toUpperCase());
    if (mutating && !getCookie('XSRF-TOKEN')) {
        await csrf();
    }

    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    if (body) headers['Content-Type'] = 'application/json';

    const xsrf = getCookie('XSRF-TOKEN');
    if (xsrf) headers['X-XSRF-TOKEN'] = xsrf;

    const res = await fetch(`/api${path}`, {
        method,
        credentials: 'include',
        headers,
        body: body ? JSON.stringify(body) : null,
    });

    const data = res.status === 204 ? null : await res.json().catch(() => null);

    if (!res.ok) {
        const err = new Error((data && data.message) || `Request failed (${res.status})`);
        err.status = res.status;
        err.errors = (data && data.errors) || {};
        throw err;
    }

    return data;
}

export const auth = {
    async login(payload) {
        await csrf();
        return api('/login', { method: 'POST', body: payload });
    },
    me() {
        return api('/me');
    },
    async logout() {
        await csrf();
        return api('/logout', { method: 'POST' });
    },
};
