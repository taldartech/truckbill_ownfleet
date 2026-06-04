import api from '../api';

const driversBase = '/app/api/drivers';

export async function fetchDriverAdvanceLedger(driverId) {
    const { data } = await api.get(`${driversBase}/${driverId}/advance-ledger`);

    return data.data;
}

export async function createDriverAdvanceEntry(driverId, payload) {
    const { data } = await api.post(`${driversBase}/${driverId}/advance-entries`, payload);

    return data.data;
}
