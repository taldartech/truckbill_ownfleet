import api from '../api';

const driversBase = '/app/api/drivers';

export async function fetchDriverAdvanceLedger(driverId, { page = 1, perPage = 5 } = {}) {
    const { data } = await api.get(`${driversBase}/${driverId}/advance-ledger`, {
        params: {
            page,
            per_page: perPage,
        },
    });

    return data.data;
}

export async function createDriverAdvanceEntry(driverId, payload) {
    const { data } = await api.post(`${driversBase}/${driverId}/advance-entries`, payload);

    return data.data;
}
