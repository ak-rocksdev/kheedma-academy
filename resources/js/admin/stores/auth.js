import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { auth as authApi } from '../api';

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null);
    const ready = ref(false); // becomes true once the initial session check completes
    const sessionExpired = ref(false); // true when the server answered 401 mid-use

    const isAuthenticated = computed(() => user.value !== null);

    /**
     * Called by the api client on a 401. Only meaningful while someone is
     * logged in; before hydration the router guard already handles it.
     */
    function expireSession() {
        if (user.value) {
            sessionExpired.value = true;
        }
    }

    /**
     * Re-login from the session-expired dialog: same account, password only.
     * On success the fresh profile (roles + permissions) replaces the stale one.
     */
    async function reauthenticate(password) {
        const { user: me } = await authApi.login({ email: user.value.email, password });
        user.value = me;
        sessionExpired.value = false;
        return me;
    }

    /** Hydrate from an existing session (called once on app boot). */
    async function fetchUser() {
        try {
            const { user: me } = await authApi.me();
            user.value = me;
        } catch {
            user.value = null;
        } finally {
            ready.value = true;
        }
    }

    async function login(credentials) {
        const { user: me } = await authApi.login(credentials);
        user.value = me;
        return me;
    }

    async function logout() {
        try {
            await authApi.logout();
        } finally {
            user.value = null;
            sessionExpired.value = false;
        }
    }

    function hasRole(role) {
        return Array.isArray(user.value?.roles) && user.value.roles.includes(role);
    }

    function can(permission) {
        return Array.isArray(user.value?.permissions) && user.value.permissions.includes(permission);
    }

    return {
        user,
        ready,
        isAuthenticated,
        sessionExpired,
        expireSession,
        reauthenticate,
        fetchUser,
        login,
        logout,
        hasRole,
        can,
    };
});
