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
                label: 'Trips',
                icon: 'bi bi-signpost-split',
                to: '/trips',
                permission: 'trips.view',
                requiresTenant: true,
            },
            {
                label: 'Stations',
                icon: 'bi bi-geo-alt',
                to: '/stations',
                permission: 'stations.view',
                requiresTenant: true,
            },
            {
                label: 'States & Cities',
                icon: 'bi bi-map',
                to: '/locations',
                permission: 'locations.view',
                requiresTenant: true,
            },
            {
                label: 'Trip Reports',
                icon: 'bi bi-graph-up-arrow',
                to: '/reports/trips',
                permission: 'trip_reports.view',
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
                label: 'Monthly Settlement',
                icon: 'bi bi-table',
                to: '/attendance/settlement',
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
            {
                label: 'Tyres',
                icon: 'bi bi-circle',
                to: '/tyres',
                permission: 'tyres.view',
                requiresTenant: true,
            },
            {
                label: 'Tyre Reports',
                icon: 'bi bi-pie-chart',
                to: '/reports/tyres',
                permission: 'tyre_reports.view',
                requiresTenant: true,
            },
        ],
    },
];
