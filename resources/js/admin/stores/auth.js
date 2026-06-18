import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { auth as authApi } from '../api';

export const useAuthStore = defineStore('auth', () => {
    const user = ref(null);
    const ready = ref(false); // becomes true once the initial session check completes

    const isAuthenticated = computed(() => user.value !== null);

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
        }
    }

    function hasRole(role) {
        return Array.isArray(user.value?.roles) && user.value.roles.includes(role);
    }

    return { user, ready, isAuthenticated, fetchUser, login, logout, hasRole };
});
