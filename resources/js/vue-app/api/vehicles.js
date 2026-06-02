import api from '../api';

const vehiclesBase = '/app/api/vehicles';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export async function listAllVehicles() {
    const { data } = await api.get(vehiclesBase, { params: { per_page: 500 } });

    return data.data ?? [];
}

export async function createVehicle(payload) {
    const { data } = await api.post(vehiclesBase, payload);

    return unwrapResource(data);
}

export async function updateVehicle(id, payload) {
    const { data } = await api.put(`${vehiclesBase}/${id}`, payload);

    return unwrapResource(data);
}

export async function deleteVehicle(id) {
    await api.delete(`${vehiclesBase}/${id}`);
}
