import api from '../api';

const documentsBase = '/app/api/vehicle-documents';

function unwrapResource(payload) {
    return payload?.data ?? payload;
}

export const vehicleDocumentTypes = [
    { value: 'fitness_certificate', label: 'Fitness Certificate' },
    { value: 'puc', label: 'PUC' },
    { value: 'insurance', label: 'Insurance' },
    { value: 'permit', label: 'Permit' },
    { value: 'rc', label: 'RC' },
    { value: 'road_tax', label: 'Road Tax' },
    { value: 'national_permit', label: 'National Permit' },
    { value: 'gps_subscription', label: 'GPS Subscription' },
    { value: 'fastag', label: 'FASTag' },
];

function buildFormData(payload, file) {
    const formData = new FormData();

    Object.entries(payload).forEach(([key, value]) => {
        if (value !== null && value !== undefined && value !== '') {
            formData.append(key, value);
        }
    });

    if (file) {
        formData.append('file', file);
    }

    return formData;
}

export async function listVehicleDocuments(vehicleId) {
    const { data } = await api.get(documentsBase, {
        params: { vehicle_id: vehicleId, per_page: 100 },
    });

    return data.data ?? [];
}

export async function createVehicleDocument(payload, file = null) {
    const formData = buildFormData(payload, file);
    const { data } = await api.post(documentsBase, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });

    return unwrapResource(data);
}

export async function updateVehicleDocument(id, payload, file = null) {
    const formData = buildFormData(payload, file);
    formData.append('_method', 'PUT');
    const { data } = await api.post(`${documentsBase}/${id}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });

    return unwrapResource(data);
}

export async function deleteVehicleDocument(id) {
    await api.delete(`${documentsBase}/${id}`);
}
