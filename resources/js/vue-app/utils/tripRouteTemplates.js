const STORAGE_KEY = 'truckbill_recent_trip_routes';
const MAX_TEMPLATES = 8;

function readStore() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);

        if (!raw) {
            return [];
        }

        const parsed = JSON.parse(raw);

        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

function writeStore(templates) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(templates.slice(0, MAX_TEMPLATES)));
    } catch {
        // Ignore quota / private mode failures.
    }
}

function routeSignature(template) {
    const start = String(template.start_station_id ?? '');
    const stops = (template.route_legs ?? [])
        .map((leg) => `${leg.to_station_id ?? ''}:${leg.customer_id ?? ''}:${leg.goods_name ?? ''}`)
        .join('|');

    return `${start}>>${stops}`;
}

/**
 * @param {{
 *   start_station_id: string|number,
 *   route_legs: Array<Record<string, mixed>>,
 *   stations?: Array<{id: string|number, name?: string}>,
 * }} formLike
 */
export function buildRouteTemplateFromForm(formLike, stations = []) {
    const startStationId = formLike.start_station_id;

    if (!startStationId || !Array.isArray(formLike.route_legs) || formLike.route_legs.length === 0) {
        return null;
    }

    const stationName = (id) => stations.find((station) => String(station.id) === String(id))?.name ?? '';

    const routeLegs = formLike.route_legs
        .filter((leg) => leg?.to_station_id)
        .map((leg) => ({
            to_station_id: leg.to_station_id,
            to_station_name: stationName(leg.to_station_id),
            customer_id: leg.use_same_customer ? '' : (leg.customer_id || ''),
            use_same_customer: Boolean(leg.use_same_customer),
            goods_name: leg.goods_name || '',
            weight_ton: leg.weight_ton ?? '',
            freight_rate: leg.freight_rate ?? '',
            freight_amount: leg.freight_amount ?? 0,
            freight_auto_calculate: leg.freight_auto_calculate !== false,
            distance_km: leg.distance_km ?? '',
            expected_mileage_km_per_liter: leg.expected_mileage_km_per_liter ?? '',
        }));

    if (routeLegs.length === 0) {
        return null;
    }

    const startName = stationName(startStationId);
    const pathLabel = [startName, ...routeLegs.map((leg) => leg.to_station_name || 'Stop')]
        .filter(Boolean)
        .join(' → ');

    return {
        id: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        saved_at: new Date().toISOString(),
        start_station_id: startStationId,
        start_station_name: startName,
        label: pathLabel || 'Saved route',
        route_legs: routeLegs,
    };
}

export function listRecentRouteTemplates() {
    return readStore();
}

export function saveRecentRouteTemplate(template) {
    if (!template?.start_station_id || !Array.isArray(template.route_legs) || template.route_legs.length === 0) {
        return;
    }

    const signature = routeSignature(template);
    const existing = readStore().filter((item) => routeSignature(item) !== signature);

    writeStore([template, ...existing]);
}

export function clearRecentRouteTemplates() {
    writeStore([]);
}
