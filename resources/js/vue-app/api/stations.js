import api from '../api';

const base = '/app/api/stations';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export async function listStations({ page = 1, perPage = 15, search = '' } = {}) {
    const { data } = await api.get(base, {
        params: {
            page,
            per_page: perPage,
            ...(search ? { search } : {}),
        },
    });

    return {
        data: data.data ?? [],
        meta: data.meta ?? {},
    };
}

export async function listAllStations({ search } = {}) {
    const { data } = await api.get(base, {
        params: {
            per_page: 500,
            ...(search ? { search } : {}),
        },
    });

    return data.data ?? [];
    s;
}

export async function createStation(payload) {
    const { data } = await api.post(base, payload);

    return unwrapResource(data);
}

export async function updateStation(id, payload) {
    const { data } = await api.put(`${base}/${id}`, payload);

    return unwrapResource(data);
}

export async function fetchStation(id) {
    const { data } = await api.get(`${base}/${id}`);

    return unwrapResource(data);
}

export async function deleteStation(id, payload) {
    const { data } = await api.delete(`${base}/${id}`, {
        data: payload,
    });
    return data ?? null;
}

export async function downloadStationsTemplate() {
    const response = await api.get(`${base}/bulk/template`, {
        responseType: 'blob',
    });

    return response.data;
}

export async function bulkUploadStations(file, onUploadProgress) {
    const formData = new FormData();

    formData.append('file', file);

    const { data } = await api.post(`${base}/bulk`, formData, {
        onUploadProgress,
    });

    return data;
}
