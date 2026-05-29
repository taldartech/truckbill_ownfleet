import api from '../api';

const driversBase = '/app/api/drivers';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export async function listDrivers(page = 1) {
    const { data } = await api.get(driversBase, { params: { page } });

    return data;
}

export async function listAllDrivers() {
    const { data } = await api.get(driversBase, { params: { per_page: 500 } });

    return data.data ?? [];
}

export async function fetchDriver(id) {
    const { data } = await api.get(`${driversBase}/${id}`);

    return unwrapResource(data);
}

export async function createDriver(payload) {
    const { data } = await api.post(driversBase, payload);

    return unwrapResource(data);
}

export async function updateDriver(id, payload) {
    const { data } = await api.put(`${driversBase}/${id}`, payload);

    return unwrapResource(data);
}

export async function deleteDriver(id) {
    await api.delete(`${driversBase}/${id}`);
}
