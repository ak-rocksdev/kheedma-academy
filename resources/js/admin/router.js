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
            {
                path: 'pelamar',
                name: 'applicants',
                component: () => import('./views/Applicants.vue'),
            },
            {
                path: 'pelamar/:id',
                name: 'person',
                component: () => import('./views/PersonDetail.vue'),
                props: true,
            },
            {
                path: 'programs',
                name: 'programs',
                component: () => import('./views/Programs.vue'),
                meta: { permission: 'programs.manage' },
            },
            {
                path: 'cohorts',
                name: 'cohorts',
                component: () => import('./views/Cohorts.vue'),
                meta: { permission: 'cohorts.view' },
            },
            {
                path: 'users',
                name: 'users',
                component: () => import('./views/Users.vue'),
                meta: { permission: 'users.manage' },
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
    if (to.meta.permission && !auth.can(to.meta.permission)) {
        return { name: 'dashboard' };
    }
});

export default router;
