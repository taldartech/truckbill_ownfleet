import api from '../api';

const reportsBase = '/app/api/reports/trips';

export async function fetchTripReport(type, filters = {}) {
    const endpoints = {
        profitability: `${reportsBase}/profitability`,
        'vehicle-wise': `${reportsBase}/vehicle-wise`,
        'driver-wise': `${reportsBase}/driver-wise`,
        'empty-km': `${reportsBase}/empty-km`,
        'expense-summary': `${reportsBase}/expense-summary`,
        'route-profitability': `${reportsBase}/route-profitability`,
    };

    const url = endpoints[type] ?? endpoints.profitability;
    const { data } = await api.get(url, { params: filters });

    return {
        rows: data.data ?? [],
        summary: data.summary ?? { trip_count: (data.data ?? []).length },
    };
}

export function exportTripReport(type, filters = {}) {
    const params = new URLSearchParams({ type, ...filters });
    window.location.href = `${reportsBase}/export?${params.toString()}`;
}

export const TRIP_REPORT_TYPES = [
    { value: 'profitability', label: 'Trip Profitability' },
    { value: 'vehicle-wise', label: 'Truck-wise Trips' },
    { value: 'driver-wise', label: 'Driver-wise Trips' },
    { value: 'empty-km', label: 'Empty KM Report' },
    { value: 'expense-summary', label: 'Expense Summary' },
    { value: 'route-profitability', label: 'Route Profitability' },
];
