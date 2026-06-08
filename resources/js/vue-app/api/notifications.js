import api from '../api';

const base = '/app/api/notifications';

export async function fetchNotifications(params = {}) {
    const { data } = await api.get(base, { params });

    return data;
}

export async function fetchUnreadNotificationCount() {
    const { data } = await api.get(`${base}/unread-count`);

    return data.count ?? 0;
}

export async function markNotificationRead(id) {
    const { data } = await api.post(`${base}/${id}/read`);

    return data.notification;
}

export async function markAllNotificationsRead() {
    await api.post(`${base}/read-all`);
}
