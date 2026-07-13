import api from '../api';

const invoicesBase = '/app/api/invoices';
const tripsBase = '/app/api/trips';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export async function listInvoices({
    page = 1,
    perPage = 25,
    search = '',
    payment_status = '',
    due_filter = '',
} = {}) {
    const { data } = await api.get(invoicesBase, {
        params: {
            page,
            per_page: perPage,
            ...(search ? { search } : {}),
            ...(payment_status ? { payment_status } : {}),
            ...(due_filter ? { due_filter } : {}),
        },
    });

    return {
        data: data.data ?? [],
        meta: data.meta ?? {},
    };
}

export async function showInvoice(id) {
    const { data } = await api.get(`${invoicesBase}/${id}`);

    return unwrapResource(data);
}

export async function previewForTrip(tripId, payload = {}) {
    const { data } = await api.post(`${tripsBase}/${tripId}/invoices/preview`, payload);

    return data.data ?? data ?? [];
}

export async function generateForTrip(tripId, payload = {}) {
    const { data } = await api.post(`${tripsBase}/${tripId}/invoices/generate`, payload);

    return data.data ?? data ?? [];
}

export function pdfUrl(id) {
    return `${invoicesBase}/${id}/pdf`;
}

export async function recordPayment(invoiceId, payload) {
    const { data } = await api.post(`${invoicesBase}/${invoiceId}/payments`, payload);

    return unwrapResource(data);
}

export async function cancelInvoice(id) {
    await api.delete(`${invoicesBase}/${id}`);
}
