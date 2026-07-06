import '../../css/app.css';

import { createApp } from 'vue';
import { createPinia } from 'pinia';
import App from './App.vue';
import router from './router';
import { onSessionExpired } from './api';
import { useAuthStore } from './stores/auth';

const app = createApp(App);
app.use(createPinia());

// Hydrate the session before mounting so route guards see the real auth state.
const auth = useAuthStore();

// A 401 mid-use opens the global re-login dialog instead of losing the page.
onSessionExpired(() => auth.expireSession());

auth.fetchUser().finally(() => {
    app.use(router);
    app.mount('#admin-app');
});
