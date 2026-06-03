export function daysInMonth(dateString) {
    if (!dateString) {
        return 30;
    }

    const [year, month] = dateString.split('-').map(Number);

    return new Date(year, month, 0).getDate();
}

export function monthlyDayAccrual(monthlyBase, dateString) {
    const base = Number(monthlyBase ?? 0);

    if (!base || base <= 0) {
        return 0;
    }

    const days = daysInMonth(dateString);

    return Math.round((base / days) * 100) / 100;
}
