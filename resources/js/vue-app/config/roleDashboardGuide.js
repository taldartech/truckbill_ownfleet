/**
 * What each role sees on /app/dashboard (permission + tenant context).
 */
export const ROLE_DASHBOARD_GUIDE = [
    {
        role: 'master_admin',
        tenant_id: 'null (platform)',
        effective_tenant_id: '—',
        dashboard_view: false,
        screen: 'Lottie placeholder — no dashboard.view permission',
    },
    {
        role: 'super_admin',
        tenant_id: 'null',
        effective_tenant_id: 'null until Enter company',
        dashboard_view: true,
        screen: 'Lottie — pick a company from Companies, then KPIs load',
    },
    {
        role: 'super_admin',
        tenant_id: 'null',
        effective_tenant_id: 'session (selected company)',
        dashboard_view: true,
        screen: 'Full KPI dashboard for selected company',
    },
    {
        role: 'admin',
        tenant_id: 'fixed UUID',
        effective_tenant_id: 'same as tenant_id',
        dashboard_view: true,
        screen: 'Full KPI dashboard',
    },
    {
        role: 'moderator',
        tenant_id: 'fixed UUID',
        effective_tenant_id: 'same as tenant_id',
        dashboard_view: true,
        screen: 'Full KPI dashboard',
    },
    {
        role: 'accountant',
        tenant_id: 'fixed UUID',
        effective_tenant_id: 'same as tenant_id',
        dashboard_view: true,
        screen: 'Full KPI dashboard',
    },
    {
        role: 'transporter',
        tenant_id: 'fixed UUID',
        effective_tenant_id: 'same as tenant_id',
        dashboard_view: false,
        screen: 'Lottie placeholder — no dashboard.view',
    },
    {
        role: 'customer',
        tenant_id: 'fixed UUID',
        effective_tenant_id: 'same as tenant_id',
        dashboard_view: false,
        screen: 'Lottie placeholder — no dashboard.view',
    },
];
