<script setup>
import { computed, ref, watch } from 'vue';
import { RouterView, RouterLink, useRouter, useRoute } from 'vue-router';
import { Ellipsis, LayoutDashboard, BookUser, BookOpen, GraduationCap, UserCog, HeartHandshake } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const route = useRoute();
const auth = useAuthStore();

// `match` is the path prefix that keeps an item highlighted, so detail pages
// (e.g. /people/5) still light up their section. Dashboard matches exactly.
// `short` is the compact label for the mobile bottom bar; `mobilePrimary`
// decides whether the item sits on the bar or in the Lainnya drop-up.
const nav = computed(() =>
    [
        { to: { name: 'dashboard' }, match: '/', label: 'Dashboard', short: 'Dashboard', icon: LayoutDashboard, mobilePrimary: true, show: true },
        { to: { name: 'people' }, match: '/people', label: 'Orang', short: 'Orang', icon: BookUser, mobilePrimary: true, show: auth.can('people.view') },
        { to: { name: 'programs' }, match: '/programs', label: 'Program', short: 'Program', icon: BookOpen, mobilePrimary: true, show: auth.can('programs.manage') },
        { to: { name: 'community' }, match: '/community', label: 'Komunitas', short: 'Komunitas', icon: HeartHandshake, mobilePrimary: false, show: auth.can('community.view') },
        { to: { name: 'cohorts' }, match: '/cohorts', label: 'Angkatan / Kelas', short: 'Kelas', icon: GraduationCap, mobilePrimary: true, show: auth.can('cohorts.view') },
        { to: { name: 'users' }, match: '/users', label: 'Tim', short: 'Tim', icon: UserCog, mobilePrimary: false, show: auth.can('users.manage') },
    ].filter((item) => item.show),
);

// Bottom bar: primary items on the bar, the rest behind one Lainnya drop-up.
const mobileNav = computed(() => nav.value.filter((item) => item.mobilePrimary));
const mobileMoreNav = computed(() => nav.value.filter((item) => ! item.mobilePrimary));

const moreOpen = ref(false);
watch(() => route.fullPath, () => (moreOpen.value = false)); // navigasi menutup drop-up

function isActive(item) {
    if (item.match === '/') {
        return route.path === '/';
    }

    return route.path === item.match || route.path.startsWith(`${item.match}/`);
}

const moreActive = computed(() => mobileMoreNav.value.some((item) => isActive(item)));

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

            <!-- Single shared content container: fluid, fills the real screen width.
                 The key remounts the active view after a session renewal so its
                 data loads fresh instead of showing the failed empty state. -->
            <main class="flex-1 overflow-y-auto">
                <div class="w-full p-6 pb-24 md:pb-6 lg:p-8">
                    <RouterView :key="auth.viewEpoch" />
                </div>
            </main>
        </div>

        <!-- Navigasi bawah (mobile): pengganti sidebar, ramah jangkauan jempol -->
        <nav
            class="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-card/95 backdrop-blur md:hidden"
            style="padding-bottom: env(safe-area-inset-bottom)"
            aria-label="Menu admin"
        >
            <div class="flex">
                <RouterLink
                    v-for="item in mobileNav"
                    :key="item.label"
                    :to="item.to"
                    class="flex flex-1 flex-col items-center gap-1 py-2.5 text-[0.65rem] font-semibold"
                    :class="isActive(item) ? 'text-orange-600' : 'text-foreground/60'"
                >
                    <component :is="item.icon" class="size-5" />
                    {{ item.short }}
                </RouterLink>
                <div v-if="mobileMoreNav.length" class="relative flex flex-1">
                    <button
                        type="button"
                        class="flex w-full flex-col items-center gap-1 py-2.5 text-[0.65rem] font-semibold"
                        :class="moreActive || moreOpen ? 'text-orange-600' : 'text-foreground/60'"
                        @click="moreOpen = !moreOpen"
                    >
                        <Ellipsis class="size-5" />
                        Lainnya
                    </button>
                    <template v-if="moreOpen">
                        <!-- Backdrop transparan: ketuk di luar menutup drop-up -->
                        <div class="fixed inset-0 z-40" @click="moreOpen = false"></div>
                        <div class="absolute bottom-full right-2 z-50 mb-3 w-48 rounded-xl border border-border bg-card p-1.5 shadow-lg">
                            <RouterLink
                                v-for="item in mobileMoreNav"
                                :key="item.label"
                                :to="item.to"
                                class="flex items-center gap-3 rounded-lg px-3.5 py-2 text-sm"
                                :class="isActive(item) ? 'bg-teal-700 text-white' : 'text-foreground/80 hover:bg-accent'"
                            >
                                <component :is="item.icon" class="size-4" />
                                {{ item.label }}
                            </RouterLink>
                        </div>
                    </template>
                </div>
            </div>
        </nav>
    </div>
</template>
