import api from '../api';

const base = '/app/api/document-alert-settings';

export async function fetchDocumentAlertSettings() {
    const { data } = await api.get(base);

    return data;
}

export async function updateDocumentAlertSettings(payload) {
    const { data } = await api.put(base, payload);

    return data;
}
