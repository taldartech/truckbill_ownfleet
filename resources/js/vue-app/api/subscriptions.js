import api from '../api';

const base = '/app/api/subscription';

export async function fetchSubscriptionIndex() {
    const { data } = await api.get(base);

    return data;
}

export async function fetchSubscriptionManage(companyId) {
    const { data } = await api.get(`${base}/manage`, { params: { company_id: companyId } });

    return data;
}

export async function fetchSubscriptionCheckout(params) {
    const { data } = await api.get(`${base}/checkout`, { params });

    return data;
}

export async function fetchSubscriptionHistory(params = {}) {
    const { data } = await api.get(`${base}/payments`, { params });

    return data;
}

export async function quoteSubscription(payload) {
    const { data } = await api.post(`${base}/quote`, payload);

    return data;
}

export async function validateSubscriptionPromo(code) {
    const { data } = await api.post(`${base}/promo/validate`, { code });

    return data;
}

export async function checkoutSubscription(payload) {
    const { data } = await api.post(`${base}/checkout`, payload);

    return data;
}

export async function callbackSubscription(payload) {
    const { data } = await api.post(`${base}/callback`, payload);

    return data;
}
