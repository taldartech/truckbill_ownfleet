import api from '../api';
import { currentMonthValue } from '../utils/datetime';

const reportsBase = '/app/api/reports/fleet-monthly';

export async function fetchFleetMonthlyReport(month) {
    const { data } = await api.get(reportsBase, { params: { month } });

    return data.data ?? { rows: [], summary: {} };
}

export function exportFleetMonthlyReport(month) {
    const params = new URLSearchParams({ month });
    window.location.href = `${reportsBase}/export?${params.toString()}`;
}

export { currentMonthValue };
