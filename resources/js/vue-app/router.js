import { createRouter, createWebHistory } from 'vue-router';
import { clearClientAuthState, fetchCurrentUser } from './api';
import { userHasAnyPermission } from './utils/permissionAlias';
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
                    meta: { requiresTenant: true, permission: 'dashboard.view' },
                },
                {
                    path: 'companies',
                    name: 'companies.index',
                    component: () => import('./pages/companies/CompaniesIndexPage.vue'),
                    meta: { allowWithoutTenant: true, permission: 'companies.view' },
                },
                {
                    path: 'branches',
                    name: 'branches.index',
                    component: () => import('./pages/branches/BranchesIndexPage.vue'),
                    meta: { allowWithoutTenant: true, permission: 'branches.view' },
                },
                {
                    path: 'trucks',
                    name: 'trucks.index',
                    component: () => import('./pages/trucks/TrucksIndexPage.vue'),
                    meta: { requiresTenant: true, permission: 'vehicles.view' },
                },
                {
                    path: 'drivers',
                    name: 'drivers.index',
                    component: () => import('./pages/drivers/DriversIndexPage.vue'),
                    meta: { requiresTenant: true, permission: 'drivers.view' },
                },
                {
                    path: 'challans',
                    name: 'challans.index',
                    component: () => import('./pages/challans/ChallansIndexPage.vue'),
                    meta: { requiresTenant: true, permission: 'challans.view' },
                },
                {
                    path: 'maintenance/records',
                    name: 'maintenance.records',
                    component: () => import('./pages/maintenance/MaintenanceRecordsIndexPage.vue'),
                    meta: { requiresTenant: true, permission: 'maintenance.view' },
                },
                {
                    path: 'maintenance/create',
                    name: 'maintenance.create',
                    component: () => import('./pages/maintenance/MaintenanceCreatePage.vue'),
                    meta: { requiresTenant: true, permission: 'maintenance.create' },
                },
                {
                    path: 'maintenance/records/:id',
                    name: 'maintenance.show',
                    component: () => import('./pages/maintenance/MaintenanceDetailPage.vue'),
                    meta: { requiresTenant: true, permission: 'maintenance.view' },
                },
                {
                    path: 'maintenance/records/:id/edit',
                    name: 'maintenance.edit',
                    component: () => import('./pages/maintenance/MaintenanceEditPage.vue'),
                    meta: { requiresTenant: true, permission: 'maintenance.edit' },
                },
                {
                    path: 'maintenance/due',
                    name: 'maintenance.due',
                    component: () => import('./pages/maintenance/MaintenanceDueIndexPage.vue'),
                    meta: { requiresTenant: true, permission: 'maintenance.view' },
                },
                {
                    path: 'maintenance/reports',
                    name: 'maintenance.reports',
                    component: () => import('./pages/maintenance/MaintenanceReportsPage.vue'),
                    meta: { requiresTenant: true, permission: 'maintenance_reports.view' },
                },
                {
                    path: 'attendance',
                    name: 'attendance.index',
                    component: () => import('./pages/attendance/AttendancesIndexPage.vue'),
                    meta: { requiresTenant: true, permission: 'attendances.view' },
                },
                {
                    path: 'attendance/trucks/:vehicleId',
                    redirect: (to) => ({
                        name: 'trucks.index',
                        query: { vehicle_id: to.params.vehicleId },
                    }),
                },
                {
                    path: 'attendance/settlement',
                    name: 'attendance.settlement',
                    component: () => import('./pages/attendance/MonthlySettlementPage.vue'),
                    meta: { requiresTenant: true, permission: 'wages.view' },
                },
                {
                    path: 'trips',
                    name: 'trips.index',
                    component: () => import('./pages/trips/TripsIndexPage.vue'),
                    meta: { requiresTenant: true, permission: 'trips.view' },
                },
                {
                    path: 'trips/create',
                    name: 'trips.create',
                    component: () => import('./pages/trips/TripCreatePage.vue'),
                    meta: { requiresTenant: true, permission: 'trips.create' },
                },
                {
                    path: 'trips/archived',
                    name: 'trips.archived',
                    component: () => import('./pages/trips/TripsArchivedIndexPage.vue'),
                    meta: {
                        requiresTenant: true,
                        permission: 'trips.view_archived',
                        roles: ['super_admin', 'admin'],
                    },
                },
                {
                    path: 'trips/:id',
                    name: 'trips.show',
                    component: () => import('./pages/trips/TripDetailPage.vue'),
                    meta: { requiresTenant: true, permission: 'trips.view' },
                },
                {
                    path: 'trips/:id/edit',
                    name: 'trips.edit',
                    component: () => import('./pages/trips/TripEditPage.vue'),
                    meta: { requiresTenant: true, permission: 'trips.edit' },
                },
                {
                    path: 'trips/:id/expenses',
                    name: 'trips.expenses',
                    component: () => import('./pages/trips/TripExpensesPage.vue'),
                    meta: { requiresTenant: true, permission: 'trips.edit' },
                },
                {
                    path: 'invoices',
                    name: 'invoices.index',
                    component: () => import('./pages/invoices/InvoicesIndexPage.vue'),
                    meta: { requiresTenant: true, permission: 'invoices.view' },
                },
                {
                    path: 'invoices/:id',
                    name: 'invoices.show',
                    component: () => import('./pages/invoices/InvoiceDetailPage.vue'),
                    meta: { requiresTenant: true, permission: 'invoices.view' },
                },
                {
                    path: 'stations',
                    name: 'stations.index',
                    component: () => import('./pages/stations/StationsIndexPage.vue'),
                    meta: { requiresTenant: true, permission: 'stations.view' },
                },
                {
                    path: 'references',
                    name: 'references.index',
                    component: () => import('./pages/brokers/BrokersIndexPage.vue'),
                    meta: { requiresTenant: true, permission: 'brokers.view' },
                },
                {
                    path: 'brokers',
                    redirect: { name: 'references.index' },
                },
                {
                    path: 'customers',
                    name: 'customers.index',
                    component: () => import('./pages/customers/CustomersIndexPage.vue'),
                    meta: { requiresTenant: true, permission: 'customers.view' },
                },
                {
                    path: 'locations',
                    name: 'locations.index',
                    component: () => import('./pages/locations/LocationsIndexPage.vue'),
                    meta: { requiresTenant: true, permission: 'locations.view' },
                },
                {
                    path: 'reports/attendance',
                    name: 'reports.attendance',
                    component: () => import('./pages/reports/AttendanceReportsPage.vue'),
                    meta: { requiresTenant: true, permission: 'reports.view' },
                },
                {
                    path: 'reports/trips',
                    name: 'reports.trips',
                    component: () => import('./pages/reports/TripReportsPage.vue'),
                    meta: { requiresTenant: true, permission: 'trip_reports.view' },
                },
                {
                    path: 'reports/fleet-monthly',
                    name: 'reports.fleet-monthly',
                    component: () => import('./pages/reports/FleetMonthlyReportPage.vue'),
                    meta: { requiresTenant: true, permission: 'fleet_monthly_report.view' },
                },
                {
                    path: 'tyre-layouts',
                    name: 'tyre-layouts.index',
                    component: () => import('./pages/tyre-layouts/TyreLayoutsIndexPage.vue'),
                    meta: { requiresTenant: true, permission: 'tyre_layouts.view' },
                },
                {
                    path: 'tyres',
                    name: 'tyres.index',
                    component: () => import('./pages/tyres/TyresIndexPage.vue'),
                    meta: { requiresTenant: true, permission: 'tyres.view' },
                },
                {
                    path: 'trucks/:vehicleId/tyres',
                    name: 'vehicles.tyres',
                    component: () => import('./pages/tyres/VehicleTyresPage.vue'),
                    meta: { requiresTenant: true, permission: 'tyres.view' },
                },
                {
                    path: 'trucks/:vehicleId',
                    name: 'trucks.show',
                    component: () => import('./pages/trucks/TruckDetailPage.vue'),
                    meta: { requiresTenant: true, permission: 'vehicles.view' },
                },
                {
                    path: 'tyres/:id',
                    name: 'tyres.show',
                    component: () => import('./pages/tyres/TyreDetailPage.vue'),
                    meta: { requiresTenant: true, permission: 'tyres.view' },
                },
                {
                    path: 'reports/tyres',
                    name: 'reports.tyres',
                    component: () => import('./pages/reports/TyreReportsPage.vue'),
                    meta: { requiresTenant: true, permission: 'tyre_reports.view' },
                },
                {
                    path: 'access/roles',
                    name: 'access.roles',
                    component: () => import('./pages/access/RolesIndexPage.vue'),
                    meta: { allowWithoutTenant: true, permission: 'roles.view', roles: ['master_admin'] },
                },
                {
                    path: 'access/roles/:roleId/permissions',
                    name: 'access.roles.permissions',
                    component: () => import('./pages/access/RolePermissionsPage.vue'),
                    meta: { allowWithoutTenant: true, permission: 'roles.view', roles: ['master_admin'] },
                },
                {
                    path: 'access/permissions',
                    name: 'access.permissions',
                    component: () => import('./pages/access/PermissionsIndexPage.vue'),
                    meta: { allowWithoutTenant: true, permission: 'permissions.view', roles: ['master_admin'] },
                },
                {
                    path: 'access/users',
                    name: 'access.users',
                    component: () => import('./pages/access/UsersIndexPage.vue'),
                    meta: {
                        allowWithoutTenant: true,
                        permission: ['users.view', 'super_admins.view'],
                        roles: ['master_admin', 'super_admin'],
                    },
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

    if (to.name === 'trips.index') {
        const showArchived = String(to.query.show_archived ?? '');

        if (['1', 'true'].includes(showArchived)) {
            const nextQuery = { ...to.query };

            delete nextQuery.show_archived;

            return {
                name: 'trips.archived',
                query: nextQuery,
                replace: true,
            };
        }
    }

    if (to.meta.roles?.length && !to.meta.roles.includes(user.role)) {
        return {
            name: 'dashboard',
            query: {
                denied: '1',
                from: to.name ?? to.path,
            },
        };
    }

    if (to.meta.permission) {
        const userPermissions = user.permissions ?? [];
        const required = Array.isArray(to.meta.permission)
            ? to.meta.permission
            : [to.meta.permission];
        const hasPermission = userHasAnyPermission(userPermissions, required);
        const inCompany =
            !user.requires_company_selection &&
            (user.effective_tenant_id ?? user.company_id) != null;

        if (!hasPermission || (to.meta.requiresTenant && !inCompany)) {
            return {
                name: 'dashboard',
                query: {
                    denied: '1',
                    from: to.name ?? to.path,
                },
            };
        }
    }

    return true;
});

export default router;
