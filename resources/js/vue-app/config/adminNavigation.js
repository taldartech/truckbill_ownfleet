/**
 * Sidebar navigation items. Add your menu entries here.
 *
 * @example
 * {
 *     label: 'Dashboard',
 *     icon: 'bi bi-speedometer',
 *     to: '/dashboard',
 *     exact: true,
 * }
 *
 * @example With children (legacy href or Vue route)
 * {
 *     label: 'Transporter',
 *     icon: 'fas fa-truck',
 *     children: [
 *         { label: 'List', icon: 'fas fa-list', href: '/transporters' },
 *     ],
 * }
 */
export const adminNavigation = [
    {
        label: 'Dashboard',
        icon: 'bi bi-speedometer',
        to: '/dashboard',
        exact: true,
    },
    {
        label: 'Truck',
        icon: 'fas fa-truck-moving',
        children: [
            { label: 'Truck List', icon: 'fas fa-list', href: '/trucks' },
            { label: 'Driver List', icon: 'fas fa-user-tie', to: '/drivers' },
        ],
    },
];
