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
                // English paths are the codebase convention; the old Indonesian
                // paths stay alive as aliases so bookmarks keep working.
                path: 'people/:id',
                name: 'person',
                alias: 'pelamar/:id',
                component: () => import('./views/PersonDetail.vue'),
                props: true,
                meta: { permission: 'people.view' },
            },
            {
                path: 'people',
                name: 'people',
                alias: 'orang',
                component: () => import('./views/People.vue'),
                meta: { permission: 'people.view' },
            },
            {
                path: 'programs',
                name: 'programs',
                component: () => import('./views/Programs.vue'),
                meta: { permission: 'programs.manage' },
            },
            {
                path: 'programs/:id',
                name: 'program-detail',
                component: () => import('./views/ProgramDetail.vue'),
                props: true,
                meta: { permission: 'programs.manage' },
            },
            {
                path: 'content',
                name: 'content',
                component: () => import('./views/Content.vue'),
                meta: { permission: 'content.manage' },
            },
            {
                path: 'community',
                name: 'community',
                component: () => import('./views/Community.vue'),
                meta: { permission: 'community.view' },
            },
            {
                path: 'cohorts',
                name: 'cohorts',
                component: () => import('./views/Cohorts.vue'),
                meta: { permission: 'cohorts.view' },
            },
            {
                path: 'cohorts/:id',
                name: 'cohort-detail',
                component: () => import('./views/CohortDetail.vue'),
                props: true,
                meta: { permission: 'cohorts.view' },
            },
            {
                path: 'users',
                name: 'users',
                component: () => import('./views/Users.vue'),
                meta: { permission: 'users.manage' },
            },
            {
                path: 'media',
                name: 'media',
                component: () => import('./views/Media.vue'),
                meta: { permission: 'content.manage' },
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
    const isStaff = auth.hasRole('admin') || auth.hasRole('mentor');

    if (to.meta.auth && !auth.isAuthenticated) {
        return { name: 'login' };
    }
    // Sesi web dibagi dengan area member: member yang mengetik /admin harus
    // dilempar ke rumahnya sendiri, bukan melihat cangkang panel staf.
    if (to.meta.auth && auth.isAuthenticated && !isStaff) {
        window.location.assign('/akun');
        return false;
    }
    if (to.meta.guest && auth.isAuthenticated) {
        if (isStaff) {
            return { name: 'dashboard' };
        }
        window.location.assign('/akun');
        return false;
    }
    if (to.meta.permission && !auth.can(to.meta.permission)) {
        return { name: 'dashboard' };
    }
});

export default router;
