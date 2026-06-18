import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from './stores/auth';
import AppShell from './components/AppShell.vue';

const routes = [
    {
        path: '/login',
        name: 'login',
        component: () => import('./views/Login.vue'),
        meta: { guest: true },
    },
    {
        // Authenticated area — wrapped in the admin shell (sidebar + topbar).
        path: '/',
        component: AppShell,
        meta: { auth: true },
        children: [
            {
                path: '',
                name: 'dashboard',
                component: () => import('./views/Dashboard.vue'),
            },
        ],
    },
];

const router = createRouter({
    history: createWebHistory('/admin'),
    routes,
});

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
