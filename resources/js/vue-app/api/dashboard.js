import api from '../api';

export async function fetchComplianceAlerts() {
    const { data } = await api.get('/app/dashboard/compliance-alerts');

    return {
        documents: data.documents ?? [],
        licenses: data.licenses ?? [],
        dueServices: data.due_services ?? [],
        requiresCompany: Boolean(data.requires_company),
    };
}
