/**
 * Sidebar navigation. Each item may set `permission` (slug) — hidden when user lacks it.
 */
export const adminNavigation = [
    {
        label: 'Dashboard',
        icon: 'bi bi-speedometer',
        to: '/dashboard',
        exact: true,
        permission: 'dashboard.view',
    },
    {
        label: 'Company',
        icon: 'fas fa-building',
        permission: 'companies.view',
        children: [
            {
                label: 'Company List',
                icon: 'fas fa-list',
                to: '/companies',
                permission: 'companies.view',
            },
        ],
    },
    {
        label: 'Truck',
        icon: 'fas fa-truck-moving',
        children: [
            {
                label: 'Truck List',
                icon: 'fas fa-list',
                to: '/trucks',
                permission: 'vehicles.view',
                requiresTenant: true,
            },
            {
                label: 'Driver List',
                icon: 'fas fa-user-tie',
                to: '/drivers',
                permission: 'drivers.view',
                requiresTenant: true,
            },
            {
                label: 'Attendance & Wages',
                icon: 'bi bi-calendar-check',
                to: '/attendance',
                permission: 'attendances.view',
                requiresTenant: true,
            },
            {
                label: 'Attendance Reports',
                icon: 'bi bi-file-earmark-bar-graph',
                to: '/reports/attendance',
                permission: 'reports.view',
                requiresTenant: true,
            },
        ],
    },
];
