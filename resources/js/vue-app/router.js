import { createRouter, createWebHistory } from 'vue-router';
import { clearClientAuthState, fetchCurrentUser } from './api';
import AppShellLayout from './layouts/AppShellLayout.vue';
import DashboardPage from './pages/DashboardPage.vue';
import LoginPage from './pages/LoginPage.vue';

const router = createRouter({
    history: createWebHistory('/app'),
    routes: [
        {
            path: '/login',
            name: 'login',
            component: LoginPage,
            meta: { guest: true },
        },
        {
            path: '/',
            component: AppShellLayout,
            meta: { requiresAuth: true },
            children: [
                {
                    path: '',
                    redirect: { name: 'dashboard' },
                },
                {
                    path: 'dashboard',
                    name: 'dashboard',
                    component: DashboardPage,
                },
                {
                    path: 'drivers',
                    name: 'drivers.index',
                    component: () => import('./pages/drivers/DriversIndexPage.vue'),
                },
            ],
        },
        {
            path: '/:pathMatch(.*)*',
            redirect: { name: 'dashboard' },
        },
    ],
});

router.beforeEach(async (to) => {
    if (to.meta.guest) {
        if (to.query.logged_out === '1') {
            clearClientAuthState();

            return true;
        }

        if (window.__AUTH_USER__) {
            return { name: 'dashboard' };
        }

        return true;
    }

    if (!to.matched.some((record) => record.meta.requiresAuth)) {
        return true;
    }

    if (window.__AUTH_USER__) {
        to.meta.user = window.__AUTH_USER__;

        return true;
    }

    try {
        const user = await fetchCurrentUser();

        if (!user) {
            return { name: 'login' };
        }

        window.__AUTH_USER__ = user;
        to.meta.user = user;

        return true;
    } catch {
        return { name: 'login' };
    }
});

export default router;
