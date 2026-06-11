import api from '../api';

const reportsBase = '/app/api/reports/fleet-monthly';

export async function fetchFleetMonthlyReport(month) {
    const { data } = await api.get(reportsBase, { params: { month } });

    return data.data ?? { rows: [], summary: {} };
}

export function exportFleetMonthlyReport(month) {
    const params = new URLSearchParams({ month });
    window.location.href = `${reportsBase}/export?${params.toString()}`;
}

export function currentMonthValue() {
    const now = new Date();

    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
}
