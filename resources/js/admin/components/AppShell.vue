<script setup>
import { RouterView, RouterLink, useRouter } from 'vue-router';
import { LayoutDashboard, Users, GraduationCap, UserCog } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { useAuthStore } from '@/stores/auth';

const router = useRouter();
const auth = useAuthStore();

// Modules marked enabled:false are placeholders for the upcoming Layer 2 work.
const nav = [
    { to: { name: 'dashboard' }, label: 'Dashboard', icon: LayoutDashboard, enabled: true },
    { label: 'Pelamar', icon: Users, enabled: false },
    { label: 'Cohort', icon: GraduationCap, enabled: false },
    { label: 'Mentor', icon: UserCog, enabled: false },
];

async function logout() {
    await auth.logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <div class="flex min-h-screen bg-background text-foreground">
        <!-- Sidebar -->
        <aside class="hidden w-60 shrink-0 flex-col border-r border-border bg-card md:flex">
            <div class="flex h-16 items-center border-b border-border px-5">
                <img :src="'/images/kheedma-academy-horizontal.png'" width="1408" height="492" alt="Kheedma Academy" class="h-7 w-auto" />
            </div>
            <nav class="flex-1 space-y-1 p-3">
                <template v-for="item in nav" :key="item.label">
                    <RouterLink
                        v-if="item.enabled"
                        :to="item.to"
                        active-class="bg-accent text-accent-foreground"
                        class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-foreground/70 transition-colors hover:bg-accent hover:text-accent-foreground"
                    >
                        <component :is="item.icon" class="size-4" />
                        {{ item.label }}
                    </RouterLink>
                    <div v-else class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-foreground/35">
                        <component :is="item.icon" class="size-4" />
                        {{ item.label }}
                        <span class="ml-auto rounded bg-secondary px-1.5 py-0.5 text-[0.6rem] font-semibold uppercase tracking-wide text-muted-foreground">segera</span>
                    </div>
                </template>
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

            <main class="flex-1 overflow-y-auto p-6 lg:p-8">
                <RouterView />
            </main>
        </div>
    </div>
</template>
