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
                    meta: { requiresTenant: true },
                },
                {
                    path: 'companies',
                    name: 'companies.index',
                    component: () => import('./pages/companies/CompaniesIndexPage.vue'),
                    meta: { allowWithoutTenant: true },
                },
                {
                    path: 'trucks',
                    name: 'trucks.index',
                    component: () => import('./pages/trucks/TrucksIndexPage.vue'),
                    meta: { requiresTenant: true },
                },
                {
                    path: 'drivers',
                    name: 'drivers.index',
                    component: () => import('./pages/drivers/DriversIndexPage.vue'),
                    meta: { requiresTenant: true },
                },
                {
                    path: 'attendance',
                    name: 'attendance.index',
                    component: () => import('./pages/attendance/AttendancesIndexPage.vue'),
                    meta: { requiresTenant: true },
                },
                {
                    path: 'reports/attendance',
                    name: 'reports.attendance',
                    component: () => import('./pages/reports/AttendanceReportsPage.vue'),
                    meta: { requiresTenant: true, permission: 'reports.view' },
                },
            ],
        },
        {
            path: '/:pathMatch(.*)*',
            redirect: { name: 'dashboard' },
        },
    ],
});

async function resolveAuthUser(to) {
    if (window.__AUTH_USER__) {
        to.meta.user = window.__AUTH_USER__;

        return window.__AUTH_USER__;
    }

    const user = await fetchCurrentUser();

    if (!user) {
        return null;
    }

    window.__AUTH_USER__ = user;
    to.meta.user = user;

    return user;
}

router.beforeEach(async (to) => {
    if (to.meta.guest) {
        if (to.query.logged_out === '1') {
            clearClientAuthState();

            return true;
        }

        if (window.__AUTH_USER__) {
            const user = window.__AUTH_USER__;

            if (user.requires_company_selection) {
                return { name: 'companies.index' };
            }

            return { name: 'dashboard' };
        }

        return true;
    }

    if (!to.matched.some((record) => record.meta.requiresAuth)) {
        return true;
    }

    let user;

    try {
        user = await resolveAuthUser(to);
    } catch {
        return { name: 'login' };
    }

    if (!user) {
        return { name: 'login' };
    }

    if (user.requires_company_selection && !to.meta.allowWithoutTenant && to.meta.requiresTenant) {
        return { name: 'companies.index' };
    }

    if (user.role === 'master_admin' && to.meta.requiresTenant) {
        return { name: 'companies.index' };
    }

    if (to.meta.permission) {
        const permissions = user.permissions ?? [];
        const hasPermission = permissions.includes(to.meta.permission);
        const inCompany =
            !user.requires_company_selection &&
            (user.effective_tenant_id ?? user.company_id) != null;

        if (!hasPermission || (to.meta.requiresTenant && !inCompany)) {
            return { name: 'dashboard' };
        }
    }

    return true;
});

export default router;
