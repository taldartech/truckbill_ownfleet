import api from '../api';

const reportBase = '/app/api/reports/tyres';

export async function fetchTyreLifeReport(params = {}) {
    const { data } = await api.get(`${reportBase}/life`, { params });
    return data.data ?? [];
}

export async function fetchTyreCostPerKmReport(params = {}) {
    const { data } = await api.get(`${reportBase}/cost-per-km`, { params });
    return data.data ?? [];
}

export async function fetchTyreReplacementsReport(params = {}) {
    const { data } = await api.get(`${reportBase}/replacements`, { params });
    return data.data ?? [];
}

export async function fetchTyreRetreadHistoryReport(params = {}) {
    const { data } = await api.get(`${reportBase}/retread-history`, { params });
    return data.data ?? [];
}

export async function fetchTyreVehicleWiseReport(params = {}) {
    const { data } = await api.get(`${reportBase}/vehicle-wise`, { params });
    return data.data ?? [];
}

export async function fetchTyreExpenseSummary(params = {}) {
    const { data } = await api.get(`${reportBase}/expense-summary`, { params });
    return data.data ?? {};
}
