/**
 * @typedef {{ driverId: string, kind: 'primary' | 'substitute', vehicleLabel: string }} BusySubstituteEntry
 */

/**
 * @param {{
 *   date: string,
 *   monthOverview?: { rows?: Array<{ vehicle_id?: string, vehicle_label?: string, days?: Record<string, object> }> },
 *   vehicles?: Array<object>,
 *   excludeAttendanceId?: string | null,
 * }} context
 * @returns {Map<string, BusySubstituteEntry>}
 */
export function buildBusySubstituteDriversByDate({
    date,
    monthOverview = { rows: [] },
    vehicles = [],
    excludeAttendanceId = null,
}) {
    /** @type {Map<string, BusySubstituteEntry>} */
    const busy = new Map();

    if (!date) {
        return busy;
    }

    for (const row of monthOverview.rows ?? []) {
        const cell = row.days?.[date];

        if (!cell) {
            continue;
        }

        const primaryId = cell.primary_driver_id;

        if (primaryId) {
            busy.set(String(primaryId), {
                driverId: String(primaryId),
                kind: 'primary',
                vehicleLabel: row.vehicle_label ?? '—',
            });
        }

        const substituteId = cell.temporary_driver_id;

        if (!substituteId || cell.status !== 'ABSENT') {
            continue;
        }

        if (excludeAttendanceId && cell.id === excludeAttendanceId) {
            continue;
        }

        busy.set(String(substituteId), {
            driverId: String(substituteId),
            kind: 'substitute',
            vehicleLabel: row.vehicle_label ?? '—',
        });
    }

    for (const vehicle of vehicles) {
        const row = (monthOverview.rows ?? []).find((item) => item.vehicle_id === vehicle.id);

        if (row?.days?.[date]) {
            continue;
        }

        const primaryId =
            vehicle.primary_driver_id ??
            vehicle.current_primary_assignment?.driver_id ??
            vehicle.current_primary_assignment?.driver?.id;

        if (!primaryId) {
            continue;
        }

        const key = String(primaryId);

        if (!busy.has(key)) {
            busy.set(key, {
                driverId: key,
                kind: 'primary',
                vehicleLabel: vehicle.registration_number ?? '—',
            });
        }
    }

    return busy;
}

/**
 * @param {Map<string, BusySubstituteEntry>} busyMap
 * @param {string} driverId
 */
export function isSubstituteDriverBusy(busyMap, driverId) {
    return busyMap.has(String(driverId));
}

/**
 * @param {BusySubstituteEntry} entry
 */
export function substituteBusyLabel(entry) {
    if (entry.kind === 'substitute') {
        return `Already substitute on ${entry.vehicleLabel}`;
    }

    return `Already primary on ${entry.vehicleLabel}`;
}
