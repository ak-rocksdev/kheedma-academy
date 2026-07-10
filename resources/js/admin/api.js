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
 * Session-expiry hook. main.js registers a handler that opens the global
 * re-authentication dialog; api() calls it on any 401 outside /login so the
 * user can re-enter their password without losing page state.
 */
let sessionExpiredHandler = null;

export function onSessionExpired(handler) {
    sessionExpiredHandler = handler;
}

/**
 * Call the admin API. Sends cookies + the X-XSRF-TOKEN header. On a non-OK
 * response throws an error carrying { status, message, errors }. A 401 from
 * any endpoint except /login also sets err.sessionExpired and triggers the
 * session-expired handler (global re-login dialog).
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

        // A dead session answers 401 everywhere except the login endpoint
        // itself. Flag the error (so callers can stay quiet) and open the
        // global re-login dialog instead of hard-redirecting.
        if (res.status === 401 && path !== '/login' && sessionExpiredHandler) {
            err.sessionExpired = true;
            sessionExpiredHandler();
        }

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

export const users = {
    list(query = '') {
        return api(`/admin/users${query}`);
    },
    create(payload) {
        return api('/admin/users', { method: 'POST', body: payload });
    },
    update(id, payload) {
        return api(`/admin/users/${id}`, { method: 'PATCH', body: payload });
    },
    remove(id) {
        return api(`/admin/users/${id}`, { method: 'DELETE' });
    },
};

export const programs = {
    list() {
        return api('/admin/programs');
    },
    create(payload) {
        return api('/admin/programs', { method: 'POST', body: payload });
    },
    update(id, payload) {
        return api(`/admin/programs/${id}`, { method: 'PATCH', body: payload });
    },
    remove(id) {
        return api(`/admin/programs/${id}`, { method: 'DELETE' });
    },
};

export const people = {
    list(query = '') {
        return api(`/admin/people${query}`);
    },
    mergePreview(survivorId, duplicateId) {
        return api(`/admin/people/merge-preview?survivor_id=${survivorId}&duplicate_id=${duplicateId}`);
    },
    merge(survivorId, duplicateId) {
        return api('/admin/people/merge', {
            method: 'POST',
            body: { survivor_id: survivorId, duplicate_id: duplicateId },
        });
    },
    updateAccount(personId, payload) {
        return api(`/admin/people/${personId}/account`, { method: 'PATCH', body: payload });
    },
};

export const communityMembers = {
    list(query = '') {
        return api(`/admin/community-members${query}`);
    },
};

export const cohorts = {
    list() {
        return api('/admin/cohorts');
    },
    detail(id) {
        return api(`/admin/cohorts/${id}`);
    },
    create(payload) {
        return api('/admin/cohorts', { method: 'POST', body: payload });
    },
    update(id, payload) {
        return api(`/admin/cohorts/${id}`, { method: 'PATCH', body: payload });
    },
    remove(id) {
        return api(`/admin/cohorts/${id}`, { method: 'DELETE' });
    },
};

export const enrollments = {
    create(payload) {
        return api('/admin/enrollments', { method: 'POST', body: payload });
    },
    remove(id) {
        return api(`/admin/enrollments/${id}`, { method: 'DELETE' });
    },
    drop(id, note) {
        return api(`/admin/enrollments/${id}/drop`, { method: 'POST', body: { note } });
    },
};

export const sessions = {
    create(cohortId, payload) {
        return api(`/admin/cohorts/${cohortId}/sessions`, { method: 'POST', body: payload });
    },
    update(id, payload) {
        return api(`/admin/sessions/${id}`, { method: 'PATCH', body: payload });
    },
    remove(id) {
        return api(`/admin/sessions/${id}`, { method: 'DELETE' });
    },
    setAttendance(id, enrollmentIds) {
        return api(`/admin/sessions/${id}/attendance`, { method: 'PUT', body: { enrollment_ids: enrollmentIds } });
    },
};
