import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from './stores/auth';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('./views/Login.vue'),
        meta: { guest: true },
    },
    {
        path: '/',
        name: 'dashboard',
        component: () => import('./views/Dashboard.vue'),
        meta: { auth: true },
    },
];

const router = createRouter({
    // Served from the /admin Blade entrypoint.
    history: createWebHistory('/admin'),
    routes,
});

// Skeleton guard — wired to the auth store; real session check lands with Layer 2 auth.
router.beforeEach((to) => {
    const auth = useAuthStore();

    if (to.meta.auth && !auth.isAuthenticated) {
        return { name: 'login' };
    }

    if (to.meta.guest && auth.isAuthenticated) {
        return { name: 'dashboard' };
    }
});

export default router;
