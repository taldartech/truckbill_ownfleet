import api from '../api';
import { parsePaginatedReportResponse } from './reportPagination';

const reportBase = '/app/api/reports/maintenance';

async function fetchMaintenanceReport(endpoint, params = {}) {
    const { data } = await api.get(`${reportBase}/${endpoint}`, { params });

    return parsePaginatedReportResponse(data);
}

export async function fetchMaintenanceCostSummary(params = {}) {
    return fetchMaintenanceReport('cost-summary', params);
}

export async function fetchMaintenanceTruckWiseReport(params = {}) {
    return fetchMaintenanceReport('truck-wise', params);
}

export async function fetchMaintenanceDueServicesReport(params = {}) {
    return fetchMaintenanceReport('due-services', params);
}

export function exportMaintenanceReport(type, filters = {}) {
    const params = new URLSearchParams({ type, ...filters });
    window.location.href = `${reportBase}/export?${params.toString()}`;
}
