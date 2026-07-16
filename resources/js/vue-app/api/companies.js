import api from '../api';

const companiesBase = '/app/api/companies';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export async function listCompanies({ page = 1, perPage = 10, search = '' } = {}) {
    const { data } = await api.get(companiesBase, {
        params: { page, per_page: perPage, search: search || undefined },
    });

    return {
        data: data.data ?? [],
        meta: data.meta ?? {},
    };
}

export async function listAllCompanies() {
    const { data } = await api.get(companiesBase, { params: { per_page: 500 } });

    return data.data ?? [];
}

export async function createCompany(payload) {
    const { data } = await api.post(companiesBase, payload);

    return unwrapResource(data);
}

export async function updateCompany(id, payload) {
    const { data } = await api.put(`${companiesBase}/${id}`, payload);

    return unwrapResource(data);
}

export async function fetchCompany(id) {
    const { data } = await api.get(`${companiesBase}/${id}`);

    return unwrapResource(data);
}

export async function enterCompany(id) {
    const { data } = await api.post(`${companiesBase}/${id}/enter`);

    return data;
}

export async function exitCompany() {
    const { data } = await api.post(`${companiesBase}/exit`);

    return data;
}
