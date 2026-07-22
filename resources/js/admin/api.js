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
        throw buildApiError(res.status, data, path);
    }

    return data;
}

/**
 * Shared error shaping for both transports (fetch in api(), XHR in
 * apiUpload()): message from the body, { status, errors } attached, and the
 * global re-login dialog triggered on a dead session. A dead session answers
 * 401 on reads and can answer 419 (stale CSRF) on writes; /login itself is
 * exempt so a wrong password never opens the re-login dialog.
 */
function buildApiError(status, data, path) {
    const err = new Error((data && data.message) || `Request failed (${status})`);
    err.status = status;
    err.errors = (data && data.errors) || {};

    if ((status === 401 || status === 419) && path !== '/login' && sessionExpiredHandler) {
        err.sessionExpired = true;
        sessionExpiredHandler();
    }

    return err;
}

/**
 * Multipart POST variant of api() — browser sets the Content-Type boundary.
 * Built on XMLHttpRequest instead of fetch because only XHR exposes upload
 * progress; pass onProgress(loadedBytes, totalBytes) to observe it.
 */
export async function apiUpload(path, formData, { onProgress = null } = {}) {
    if (!getCookie('XSRF-TOKEN')) {
        await csrf();
    }
    const xsrf = getCookie('XSRF-TOKEN');

    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', `/api${path}`);
        xhr.withCredentials = true;
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        if (xsrf) xhr.setRequestHeader('X-XSRF-TOKEN', xsrf);

        if (onProgress) {
            xhr.upload.addEventListener('progress', (event) => {
                if (event.lengthComputable) onProgress(event.loaded, event.total);
            });
        }

        xhr.onload = () => {
            let data = null;
            try {
                data = JSON.parse(xhr.responseText);
            } catch {
                // Non-JSON body; the status check below decides the outcome.
            }
            if (xhr.status >= 200 && xhr.status < 300) {
                resolve(data);
                return;
            }
            reject(buildApiError(xhr.status, data, path));
        };
        xhr.onerror = () => reject(new Error('Koneksi terputus saat mengunggah. Coba lagi ya.'));
        xhr.send(formData);
    });
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
    detail(id) {
        return api(`/admin/programs/${id}`);
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
    uploadThumbnail(id, file) {
        const body = new FormData();
        body.append('thumbnail', file);
        return apiUpload(`/admin/programs/${id}/thumbnail`, body);
    },
    removeThumbnail(id) {
        return api(`/admin/programs/${id}/thumbnail`, { method: 'DELETE' });
    },
};

export const applications = {
    list(query = '') {
        return api(`/admin/applications${query}`);
    },
    review(id, status, extra = {}) {
        return api(`/admin/applications/${id}`, { method: 'PATCH', body: { status, ...extra } });
    },
};

export const people = {
    list(query = '') {
        return api(`/admin/people${query}`);
    },
    updateAccount(personId, payload) {
        return api(`/admin/people/${personId}/account`, { method: 'PATCH', body: payload });
    },
};

export const communityMembers = {
    list(query = '') {
        return api(`/admin/community-members${query}`);
    },
    remove(id) {
        return api(`/admin/community-members/${id}`, { method: 'DELETE' });
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

export const assignments = {
    upsert(sessionId, payload) {
        return api(`/admin/sessions/${sessionId}/assignment`, { method: 'PUT', body: payload });
    },
};

export const submissions = {
    history(assignmentId, enrollmentId) {
        return api(`/admin/assignments/${assignmentId}/enrollments/${enrollmentId}/submissions`);
    },
    grade(id, payload) {
        return api(`/admin/submissions/${id}/grade`, { method: 'PATCH', body: payload });
    },
};

export const contentSections = {
    list(query = '') {
        return api(`/admin/content-sections${query}`);
    },
    create(payload) {
        return api('/admin/content-sections', { method: 'POST', body: payload });
    },
    update(id, payload) {
        return api(`/admin/content-sections/${id}`, { method: 'PATCH', body: payload });
    },
    remove(id) {
        return api(`/admin/content-sections/${id}`, { method: 'DELETE' });
    },
    reorder(payload) {
        return api('/admin/content-sections-order', { method: 'PATCH', body: payload });
    },
};

export const media = {
    list(query = '') {
        return api(`/admin/media${query}`);
    },
    show(id) {
        return api(`/admin/media/${id}`);
    },
    upload(file, onProgress = null) {
        const formData = new FormData();
        formData.append('file', file);
        return apiUpload('/admin/media', formData, { onProgress });
    },
    update(id, payload) {
        return api(`/admin/media/${id}`, { method: 'PATCH', body: payload });
    },
    remove(id) {
        return api(`/admin/media/${id}`, { method: 'DELETE' });
    },
};
