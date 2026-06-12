/**
 * @typedef {{ driverId: string, kind: 'trip' | 'substitute', vehicleLabel: string, tripNumber?: string|null }} BusySubstituteEntry
 */

/**
 * @param {{
 *   date: string,
 *   monthOverview?: {
 *     rows?: Array<{ vehicle_id?: string, vehicle_label?: string, days?: Record<string, object> }>,
 *     substitute_busy_by_date?: Record<string, Record<string, BusySubstituteEntry>>,
 *   },
 *   excludeAttendanceId?: string | null,
 * }} context
 * @returns {Map<string, BusySubstituteEntry>}
 */
export function buildBusySubstituteDriversByDate({
    date,
    monthOverview = { rows: [] },
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

    const tripBusy = monthOverview.substitute_busy_by_date?.[date] ?? {};

    for (const [driverId, entry] of Object.entries(tripBusy)) {
        if (busy.has(driverId)) {
            continue;
        }

        busy.set(driverId, {
            driverId,
            kind: 'trip',
            vehicleLabel: entry.vehicle_label ?? '—',
            tripNumber: entry.trip_number ?? null,
        });
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

    if (entry.tripNumber) {
        return `On trip ${entry.tripNumber} (${entry.vehicleLabel})`;
    }

    return `On trip (${entry.vehicleLabel})`;
}
