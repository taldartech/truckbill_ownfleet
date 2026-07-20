import { tripStatusMeta } from '../api/trips';
import { formatDate } from './datetime';
import { formatMeterKm } from './formatMeter';

export function resolveTripStatusChangeContext({ trip, form, vehicleRegistration } = {}) {
    return {
        tripNumber: form?.trip_number || trip?.trip_number || '—',
        truckNumber:
            vehicleRegistration ||
            trip?.vehicle?.registration_number ||
            form?.vehicle?.registration_number ||
            '—',
    };
}

function formatStatusChangeDate(value) {
    if (!value) {
        return null;
    }

    return formatDate(value);
}

export function buildTripStatusChangeUpdateItems({ fromStatus, toStatus, confirmPayload } = {}) {
    const toLabel = tripStatusMeta(toStatus).label;
    const fromLabel = tripStatusMeta(fromStatus).label;
    const items = [];

    if (fromStatus && String(fromStatus).toUpperCase() !== String(toStatus).toUpperCase()) {
        items.push(`Trip status will change from ${fromLabel} to ${toLabel}`);
    } else {
        items.push(`Trip status will change to ${toLabel}`);
    }

    items.push('A status history record will be added');

    if (toStatus === 'COMPLETED') {
        const endDate = formatStatusChangeDate(confirmPayload?.end_at);

        if (endDate) {
            items.push(`End date will be saved as ${endDate}`);
        } else {
            items.push('End date will be saved');
        }

        const arrivalDate = formatStatusChangeDate(confirmPayload?.actual_arrival_at);

        if (arrivalDate) {
            items.push(`Actual arrival date will be saved as ${arrivalDate}`);
        } else {
            items.push('Actual arrival date will be saved');
        }

        const endMeter = confirmPayload?.end_meter_reading_km;

        if (endMeter !== '' && endMeter != null && Number.isFinite(Number(endMeter))) {
            items.push(`End meter reading will be saved as ${formatMeterKm(endMeter)}`);
        } else {
            items.push('End meter reading will be saved');
        }

        items.push('Truck running meter (odometer) will be updated');
    }

    if (toStatus === 'IN_PROGRESS') {
        const startDate = formatStatusChangeDate(confirmPayload?.start_at);

        if (startDate) {
            items.push(`Start date will be saved as ${startDate}`);
        } else {
            items.push('Start date will be saved');
        }

        const startMeter = confirmPayload?.start_meter_reading_km;

        if (startMeter !== '' && startMeter != null && Number.isFinite(Number(startMeter))) {
            items.push(`Start meter reading will be saved as ${formatMeterKm(startMeter)}`);
        } else {
            items.push('Start meter reading will be saved');
        }
    }

    if (toStatus === 'CANCELLED') {
        const cancelledDate = formatStatusChangeDate(confirmPayload?.cancelled_at);

        if (cancelledDate) {
            items.push(`Cancellation date will be saved as ${cancelledDate}`);
        } else {
            items.push('Cancellation date will be saved');
        }

        items.push('Cancellation reason will be saved');

        const endMeter = confirmPayload?.end_meter_reading_km;

        if (endMeter !== '' && endMeter != null && Number.isFinite(Number(endMeter))) {
            items.push(`End meter reading will be saved as ${formatMeterKm(endMeter)}`);
        } else {
            items.push('End meter reading will be saved');
        }

        items.push('Truck running meter (odometer) will be updated');
    }

    return items;
}
