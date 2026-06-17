import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

/**
 * Auth store skeleton. Layer 2 will replace the placeholder login with a real
 * Sanctum-backed session (cookie + CSRF) and load the authenticated admin user.
 */
export const useAuthStore = defineStore('auth', () => {
    const user = ref(null);

    const isAuthenticated = computed(() => user.value !== null);

    function loginAs(name) {
        user.value = { name };
    }

    function logout() {
        user.value = null;
    }

    return { user, isAuthenticated, loginAs, logout };
});
