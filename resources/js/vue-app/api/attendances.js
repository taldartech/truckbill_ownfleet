import api from '../api';

const attendancesBase = '/app/api/attendances';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export async function fetchMonthOverview({ from_date, to_date }) {
    const { data } = await api.get(`${attendancesBase}/month-overview`, {
        params: { from_date, to_date },
    });

    return data.data ?? { summary: {}, rows: [] };
}

export async function listAttendancesPaginated(params = {}) {
    const { data } = await api.get(attendancesBase, {
        params: { per_page: 25, ...params },
    });

    return {
        rows: data.data ?? [],
        meta: data.meta ?? {},
    };
}

export async function getAttendance(id) {
    const { data } = await api.get(`${attendancesBase}/${id}`);

    return unwrapResource(data);
}

export async function markAttendance(payload) {
    const { data } = await api.post(attendancesBase, payload);

    return unwrapResource(data);
}

export async function updateAttendance(id, payload) {
    const { data } = await api.put(`${attendancesBase}/${id}`, payload);

    return unwrapResource(data);
}

export async function markWagePaid(id, payload = {}) {
    const { data } = await api.post(`${attendancesBase}/${id}/mark-wage-paid`, payload);

    return unwrapResource(data);
}

export async function deleteAttendance(id) {
    await api.delete(`${attendancesBase}/${id}`);
}
