<script setup>
import { RouterView } from 'vue-router';
import SessionExpiredDialog from './components/SessionExpiredDialog.vue';
import { useAuthStore } from './stores/auth';

const auth = useAuthStore();
</script>

<template>
    <div class="min-h-screen bg-sand-50 text-teal-900">
        <RouterView />
        <!-- Global re-login dialog: keeps page state alive when the session dies. -->
        <SessionExpiredDialog v-if="auth.sessionExpired && auth.user" />
    </div>
</template>
