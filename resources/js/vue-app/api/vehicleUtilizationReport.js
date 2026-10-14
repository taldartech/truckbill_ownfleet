import api from '../api';
import { parsePaginatedReportResponse } from './reportPagination';

const reportsBase = '/app/api/reports/vehicle-utilization';

/**
 * @param {Record<string, unknown>} filters
 * @param {Record<string, unknown>} pagination
 */
export async function fetchVehicleUtilizationReport(filters = {}, pagination = {}) {
    const { data } = await api.get(reportsBase, {
        params: {
            ...filters,
            ...pagination,
        },
    });

    const parsed = parsePaginatedReportResponse(data);

    return {
        rows: parsed.rows,
        meta: parsed.meta,
        summary: parsed.summary ?? {},
        company_name: data.company_name ?? '',
        period: data.period ?? {},
    };
}

/**
 * Trigger browser download for Excel export
 * @param {Record<string, unknown>} filters
 */
export function exportVehicleUtilizationReport(filters = {}) {
    const cleanParams = new URLSearchParams();
    Object.entries(filters).forEach(([key, val]) => {
        if (val !== null && val !== undefined && val !== '') {
            cleanParams.append(key, String(val));
        }
    });
    window.location.href = `${reportsBase}/export?${cleanParams.toString()}`;
}
