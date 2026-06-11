/**
 * Sidebar navigation. Each item may set `permission` (slug) — hidden when user lacks it.
 */
export const adminNavigation = [
    {
        label: 'Dashboard',
        icon: 'bi bi-speedometer nav-icon',
        to: '/dashboard',
        exact: true,
        permission: 'dashboard.view',
    },
    {
        label: 'Company',
        icon: 'fas fa-building nav-icon',
        permission: 'companies.view',
        children: [
            {
                label: 'Company List',
                icon: 'fas fa-list nav-icon',
                to: '/companies',
                permission: 'companies.view',
            },
        ],
    },
    {
        label: 'Fleet',
        icon: 'fas fa-truck-moving nav-icon',
        children: [
            {
                label: 'Truck List',
                icon: 'fas fa-list nav-icon',
                to: '/trucks',
                permission: 'vehicles.view',
                requiresTenant: true,
            },
            {
                label: 'Driver List',
                icon: 'fas fa-user-tie nav-icon',
                to: '/drivers',
                permission: 'drivers.view',
                requiresTenant: true,
            },
        ],
    },
    {
        label: 'Operations',
        icon: 'bi bi-signpost-split nav-icon',
        children: [
            {
                label: 'Challans',
                icon: 'bi bi-receipt',
                to: '/challans',
                permission: 'challans.view',
                requiresTenant: true,
            },
            {
                label: 'Trips',
                icon: 'bi bi-signpost-split nav-icon',
                to: '/trips',
                permission: 'trips.view',
                requiresTenant: true,
            },
            {
                label: 'Stations',
                icon: 'bi bi-geo-alt nav-icon',
                to: '/stations',
                permission: 'stations.view',
                requiresTenant: true,
            },
            {
                label: 'Brokers',
                icon: 'bi bi-person-badge nav-icon',
                to: '/brokers',
                permission: 'brokers.view',
                requiresTenant: true,
            },
            {
                label: 'Customers',
                icon: 'bi bi-building nav-icon',
                to: '/customers',
                permission: 'customers.view',
                requiresTenant: true,
            },
            {
                label: 'States & Cities',
                icon: 'bi bi-map nav-icon',
                to: '/locations',
                permission: 'locations.view',
                requiresTenant: true,
            },
        ],
    },
    {
        label: 'Attendance',
        icon: 'bi bi-calendar-check nav-icon',
        children: [
            {
                label: 'Attendance & Wages',
                icon: 'bi bi-calendar-check nav-icon',
                to: '/attendance',
                permission: 'attendances.view',
                requiresTenant: true,
            },
            {
                label: 'Monthly Settlement',
                icon: 'bi bi-table nav-icon',
                to: '/attendance/settlement',
                permission: 'attendances.view',
                requiresTenant: true,
            },
        ],
    },
    {
        label: 'Reports',
        icon: 'bi bi-graph-up-arrow nav-icon',
        children: [
            {
                label: 'Trip Reports',
                icon: 'bi bi-graph-up-arrow nav-icon',
                to: '/reports/trips',
                permission: 'trip_reports.view',
                requiresTenant: true,
            },
            {
                label: 'Monthly Fleet Report',
                icon: 'bi bi-truck nav-icon',
                to: '/reports/fleet-monthly',
                permission: 'fleet_monthly_report.view',
                requiresTenant: true,
            },
            {
                label: 'Attendance Reports',
                icon: 'bi bi-file-earmark-bar-graph nav-icon',
                to: '/reports/attendance',
                permission: 'reports.view',
                requiresTenant: true,
            },
            {
                label: 'Tyre Reports',
                icon: 'bi bi-pie-chart nav-icon',
                to: '/reports/tyres',
                permission: 'tyre_reports.view',
                requiresTenant: true,
            },
        ],
    },
    {
        label: 'Tyres',
        icon: 'bi bi-circle nav-icon',
        requiresTenant: true,
        children: [
            {
                label: 'Tyre List',
                icon: 'bi bi-list-ul nav-icon',
                to: '/tyres',
                permission: 'tyres.view',
                requiresTenant: true,
            },
            {
                label: 'Tyre Layouts',
                icon: 'bi bi-diagram-3 nav-icon',
                to: '/tyre-layouts',
                permission: 'tyre_layouts.view',
                requiresTenant: true,
            },
        ],
    },
    {
        label: 'Access Control',
        icon: 'bi bi-shield-lock nav-icon',
        children: [
            {
                label: 'Roles',
                icon: 'bi bi-person-badge nav-icon',
                to: '/access/roles',
                permission: 'roles.view',
            },
            {
                label: 'Permissions',
                icon: 'bi bi-key nav-icon',
                to: '/access/permissions',
                permission: 'permissions.view',
            },
            {
                label: 'Super Admins',
                icon: 'bi bi-person-gear nav-icon',
                to: '/access/users',
                permission: 'super_admins.view',
            },
            {
                label: 'Users',
                icon: 'bi bi-people nav-icon',
                to: '/access/users',
                permission: 'users.view',
            },
        ],
    },
];
