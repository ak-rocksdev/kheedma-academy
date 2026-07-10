<script setup>
import { computed } from 'vue';
import { RouterView, RouterLink, useRouter, useRoute } from 'vue-router';
import { LayoutDashboard, Users, BookUser, BookOpen, GraduationCap, UserCog, HeartHandshake } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const route = useRoute();
const auth = useAuthStore();

// `match` is the path prefix that keeps an item highlighted, so detail pages
// (e.g. /pelamar/5) still light up their section. Dashboard matches exactly.
const nav = computed(() =>
    [
        { to: { name: 'dashboard' }, match: '/', label: 'Dashboard', icon: LayoutDashboard, show: true },
        { to: { name: 'applicants' }, match: '/pelamar', label: 'Pelamar', icon: Users, show: auth.can('applications.view') },
        { to: { name: 'people' }, match: '/orang', label: 'Orang', icon: BookUser, show: auth.can('people.view') },
        { to: { name: 'programs' }, match: '/programs', label: 'Program', icon: BookOpen, show: auth.can('programs.manage') },
        { to: { name: 'community' }, match: '/community', label: 'Komunitas', icon: HeartHandshake, show: auth.can('community.view') },
        { to: { name: 'cohorts' }, match: '/cohorts', label: 'Angkatan', icon: GraduationCap, show: auth.can('cohorts.view') },
        { to: { name: 'users' }, match: '/users', label: 'Tim', icon: UserCog, show: auth.can('users.manage') },
    ].filter((item) => item.show),
);

function isActive(item) {
    if (item.match === '/') {
        return route.path === '/';
    }

    return route.path === item.match || route.path.startsWith(`${item.match}/`);
}

async function logout() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <!-- h-screen + internal scroll on <main>: sidebar and topbar stay put. -->
    <div class="flex h-screen overflow-hidden bg-background text-foreground">
        <!-- Sidebar -->
        <aside class="hidden w-60 shrink-0 flex-col border-r border-border bg-card md:flex">
            <div class="flex h-16 shrink-0 items-center border-b border-border px-5">
                <img :src="'/images/kheedma-academy-horizontal.png'" width="1408" height="492" alt="Kheedma Academy" class="h-7 w-auto" />
            </div>
            <nav class="flex-1 space-y-1 overflow-y-auto p-3">
                <RouterLink
                    v-for="item in nav"
                    :key="item.label"
                    :to="item.to"
                    class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors"
                    :class="isActive(item)
                        ? 'bg-teal-700 text-white shadow-sm'
                        : 'text-foreground/70 hover:bg-accent hover:text-accent-foreground'"
                >
                    <component :is="item.icon" class="size-4" />
                    {{ item.label }}
                </RouterLink>
            </nav>
            <div class="border-t border-border p-4 text-[0.7rem] tracking-wide text-muted-foreground">
                Khidmat · Amanah · Itqan · Barakah
            </div>
        </aside>

        <!-- Main column -->
        <div class="flex min-w-0 flex-1 flex-col">
            <header class="flex h-16 items-center justify-between border-b border-border bg-card/60 px-6 backdrop-blur">
                <img :src="'/images/kheedma-academy-horizontal.png'" width="1408" height="492" alt="Kheedma Academy" class="h-6 w-auto md:hidden" />
                <div class="hidden text-sm font-medium text-muted-foreground md:block">Panel Admin</div>
                <div class="flex items-center gap-3 text-sm">
                    <span class="hidden text-foreground/70 sm:inline">
                        {{ auth.user?.name }}
                        <span v-if="auth.user?.roles?.length" class="ml-1 text-muted-foreground">· {{ auth.user.roles.join(', ') }}</span>
                    </span>
                    <Button variant="outline" size="sm" @click="logout">Keluar</Button>
                </div>
            </header>

            <!-- Single shared content container: every view follows this width. -->
            <main class="flex-1 overflow-y-auto">
                <div class="mx-auto w-full max-w-6xl p-6 lg:p-8">
                    <RouterView />
                </div>
            </main>
        </div>
    </div>
</template>
