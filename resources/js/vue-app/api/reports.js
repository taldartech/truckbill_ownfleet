import api from '../api';

export async function fetchAttendanceReport(params = {}) {
    const { data } = await api.get('/app/api/reports/attendance', { params });

    return data;
}

export function attendanceReportExportUrl(params = {}) {
    const search = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        if (value !== '' && value != null) {
            search.set(key, String(value));
        }
    });

    const query = search.toString();

    return query ? `/app/api/reports/attendance/export?${query}` : '/app/api/reports/attendance/export';
}
