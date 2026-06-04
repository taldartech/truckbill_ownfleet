import api from '../api';

const base = '/app/api/locations';

export async function listCountries() {
    const { data } = await api.get(`${base}/countries`);

    return data.data ?? [];
}

export async function listStates(countryId) {
    const { data } = await api.get(`${base}/states`, {
        params: countryId ? { country_id: countryId } : undefined,
    });

    return data.data ?? [];
}

export async function listCities(stateId, search) {
    const { data } = await api.get(`${base}/cities`, {
        params: {
            state_id: stateId,
            search: search || undefined,
        },
    });

    return data.data ?? [];
}
