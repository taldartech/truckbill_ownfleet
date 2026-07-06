import api from '../api';
import { parsePaginatedReportResponse } from './reportPagination';

const reportsBase = '/app/api/reports/fleet-monthly';

export async function fetchFleetMonthlyReport(month, pagination = {}) {
    const { data } = await api.get(reportsBase, {
        params: {
            month,
            ...pagination,
        },
    });

    const parsed = parsePaginatedReportResponse(data);

    return {
        rows: parsed.rows,
        meta: parsed.meta,
        summary: parsed.summary ?? {},
        company_name: parsed.companyName ?? '',
        month: parsed.month ?? month,
    };
}

export function exportFleetMonthlyReport(month) {
    const params = new URLSearchParams({ month });
    window.location.href = `${reportsBase}/export?${params.toString()}`;
}

export { currentMonthValue } from '../utils/datetime';
