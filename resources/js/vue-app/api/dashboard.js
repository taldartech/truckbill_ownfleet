import api from '../api';

export async function fetchComplianceAlerts(params = {}) {
    const { data } = await api.get(
        '/app/dashboard/compliance-alerts',
        {
            params,
        },
    );

    return {
        documents: data.documents ?? [],
        licenses: data.licenses ?? [],
        dueServices: data.due_services ?? [],
        ewayBills: data.eway_bills ?? [],
        fuelAlerts: data.fuel_alerts ?? [],
        alerts: data.alerts ?? [],
        requiresCompany: Boolean(data.requires_company),
    };
}

export async function fetchGroupDashboard(params = {}) {
    const { data } = await api.get('/app/dashboard/group', { params });

    return data;
}