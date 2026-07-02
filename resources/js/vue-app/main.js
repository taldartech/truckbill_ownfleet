import { createApp } from 'vue';
import App from './App.vue';
import AppDateInput from './components/ui/AppDateInput.vue';
import { refreshCsrfToken } from './api';
import { noScrollChange } from './directives/noScrollChange';
import router from './router';

async function bootstrap() {
    const meta = document.querySelector('meta[name="csrf-token"]');

    if (meta && window.__AUTH_USER__) {
        try {
            await refreshCsrfToken();
        } catch {
            // Blade meta token is enough if refresh fails.
        }
    }

    const app = createApp(App);

    app.component('AppDateInput', AppDateInput);
    app.directive('no-scroll-change', noScrollChange);
    app.use(router);
    await router.isReady();
    app.mount('#app');
}

bootstrap().catch((error) => {
    console.error('Vue app failed to start:', error);
});
