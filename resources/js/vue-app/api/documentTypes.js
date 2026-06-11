import api from '../api';

const base = '/app/api/document-types';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export async function listAllDocumentTypes() {
    const { data } = await api.get(base, {
        params: { per_page: 500 },
    });

    return data.data ?? [];
}

export async function createDocumentType(payload) {
    const { data } = await api.post(base, payload);

    return unwrapResource(data);
}
