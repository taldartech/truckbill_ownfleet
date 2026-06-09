export function dailyAttendanceStorageKey(date) {
    return `truckbill-daily-attendance:${date}`;
}

export function loadSavedVehicleIds(date) {
    if (!date) {
        return new Set();
    }

    try {
        const raw = localStorage.getItem(dailyAttendanceStorageKey(date));

        return new Set(JSON.parse(raw ?? '[]'));
    } catch {
        return new Set();
    }
}

export function persistSavedVehicleIds(date, ids) {
    if (!date) {
        return;
    }

    localStorage.setItem(dailyAttendanceStorageKey(date), JSON.stringify([...ids]));
}

export function markVehiclesSavedForDate(date, vehicleIds) {
    const saved = loadSavedVehicleIds(date);

    for (const vehicleId of vehicleIds) {
        saved.add(vehicleId);
    }

    persistSavedVehicleIds(date, saved);

    return saved;
}

export function isPresentConfirmed(date, vehicleId) {
    return loadSavedVehicleIds(date).has(vehicleId);
}
