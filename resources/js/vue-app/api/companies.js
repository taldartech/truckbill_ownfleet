import api, { payloadNeedsMultipart, toFormData } from '../api';

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

const COMPANY_FILE_FIELDS = ['company_logo', 'digital_signature'];

export async function createCompany(payload) {
    if (payloadNeedsMultipart(payload, COMPANY_FILE_FIELDS)) {
        const { data } = await api.post(companiesBase, toFormData(payload));

        return unwrapResource(data);
    }

    const { data } = await api.post(companiesBase, payload);

    return unwrapResource(data);
}

export async function updateCompany(id, payload) {
    if (payloadNeedsMultipart(payload, COMPANY_FILE_FIELDS)) {
        const formData = toFormData(payload);
        formData.append('_method', 'PUT');
        const { data } = await api.post(`${companiesBase}/${id}`, formData);

        return unwrapResource(data);
    }

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
