import api from '../api';

const reportBase = '/app/api/reports/maintenance';

export async function fetchMaintenanceCostSummary(params = {}) {
    const { data } = await api.get(`${reportBase}/cost-summary`, { params });

    return data.data ?? [];
}

export async function fetchMaintenanceTruckWiseReport(params = {}) {
    const { data } = await api.get(`${reportBase}/truck-wise`, { params });

    return data.data ?? [];
}

export async function fetchMaintenanceDueServicesReport() {
    const { data } = await api.get(`${reportBase}/due-services`);

    return data.data ?? [];
}
