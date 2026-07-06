import api from '../api';
import { parsePaginatedReportResponse, withReportPagination } from './reportPagination';

const reportBase = '/app/api/reports/tyres';

async function fetchTyreReport(endpoint, params = {}) {
    const { data } = await api.get(`${reportBase}/${endpoint}`, { params });

    return parsePaginatedReportResponse(data);
}

export async function fetchTyreLifeReport(params = {}) {
    return fetchTyreReport('life', params);
}

export async function fetchTyreCostPerKmReport(params = {}) {
    return fetchTyreReport('cost-per-km', params);
}

export async function fetchTyreReplacementsReport(params = {}) {
    return fetchTyreReport('replacements', params);
}

export async function fetchTyreVehicleWiseReport(params = {}) {
    return fetchTyreReport('vehicle-wise', params);
}

export async function fetchTyreExpenseSummary(params = {}) {
    return fetchTyreReport('expense-summary', params);
}

export function exportTyreReport(type, filters = {}) {
    const params = new URLSearchParams({ type, ...filters });
    window.location.href = `${reportBase}/export?${params.toString()}`;
}

export { withReportPagination };
