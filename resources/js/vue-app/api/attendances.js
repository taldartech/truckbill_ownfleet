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

export async function fetchSettlementSheet(month) {
    const { data } = await api.get(`${attendancesBase}/settlement-sheet`, {
        params: { month },
    });

    return data.data ?? {};
}

export function settlementSheetExportUrl(month) {
    const search = new URLSearchParams({ month });

    return `${attendancesBase}/settlement-sheet/export?${search.toString()}`;
}

export async function updateSettlementLine(monthlyWageId, payload) {
    const { data } = await api.patch(`/app/api/monthly-driver-wages/${monthlyWageId}/settlement`, payload);

    return data.data ?? data;
}

export async function updateVehicleSettlementLine(payload) {
    const { data } = await api.patch(`${attendancesBase}/vehicle-settlement-line`, payload);

    return data.data ?? {};
}

export async function fetchMonthlyWages(month) {
    const { data } = await api.get(`${attendancesBase}/monthly-wages`, {
        params: { month },
    });

    return data.data ?? [];
}

export async function closeMonthWages(month) {
    const { data } = await api.post(`${attendancesBase}/close-month`, { month });

    return data.data ?? [];
}

export async function lockMonthWages(month) {
    const { data } = await api.post(`${attendancesBase}/lock-month`, { month });

    return data.data ?? {};
}

export async function reopenMonthWages(month) {
    const { data } = await api.post(`${attendancesBase}/reopen-month`, { month });

    return data.data ?? {};
}

export async function unlockMonthWages(month) {
    const { data } = await api.post(`${attendancesBase}/unlock-month`, { month });

    return data.data ?? {};
}

export async function markAllMonthlyWagesPaid(month, payload = {}) {
    const { data } = await api.post(`${attendancesBase}/mark-all-monthly-paid`, {
        month,
        ...payload,
    });

    return data.data ?? [];
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

export async function markMonthlyWagePaid(id, payload = {}) {
    const { data } = await api.post(`/app/api/monthly-driver-wages/${id}/mark-paid`, payload);

    return unwrapResource(data);
}

export async function deleteAttendance(id) {
    await api.delete(`${attendancesBase}/${id}`);
}
