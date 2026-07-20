export const CALCULATION_VERSION = 1;

function roundCurrency(value) {
    return Math.round(Number(value) * 100) / 100;
}

export function calculateExpectedRunningKm(startMeter, endMeter) {
    const start = Number(startMeter ?? 0);
    const endRaw = endMeter;

    if (endRaw === '' || endRaw == null || !Number.isFinite(Number(endRaw))) {
        return null;
    }

    const end = Number(endRaw);

    if (!Number.isFinite(start) || start <= 0 || !Number.isFinite(end) || end < start) {
        return null;
    }

    return roundCurrency(end - start);
}

export function calculatePlannedDiesel(expectedRunningKm, plannedMileage) {
    const km = Number(expectedRunningKm ?? 0);
    const mileage = Number(plannedMileage ?? 0);

    if (!Number.isFinite(km) || km <= 0 || !Number.isFinite(mileage) || mileage <= 0) {
        return null;
    }

    return roundCurrency(km / mileage);
}

export function calculateExpectedTotalKm(plannedDiesel, plannedMileage) {
    const diesel = Number(plannedDiesel ?? 0);
    const mileage = Number(plannedMileage ?? 0);

    if (diesel <= 0 || mileage <= 0) {
        return 0;
    }

    return roundCurrency(diesel * mileage);
}

export function calculateRatePerKm(plannedDiesel, dieselRate, expectedTotalKm) {
    const diesel = Number(plannedDiesel ?? 0);
    const rate = Number(dieselRate ?? 0);
    const totalKm = Number(expectedTotalKm ?? 0);

    if (diesel <= 0 || rate <= 0 || totalKm <= 0) {
        return null;
    }

    const totalTripCost = roundCurrency(diesel * rate);

    return roundCurrency(totalTripCost / totalKm);
}

export function calculateDifferenceAmount(unusedKm, ratePerKm) {
    const km = Number(unusedKm ?? 0);
    const rate = Number(ratePerKm ?? 0);

    if (km <= 0 || rate <= 0) {
        return 0;
    }

    return roundCurrency(km * rate);
}

/**
 * @param {{
 *   plannedMileageKmPerLiter?: number | string | null,
 *   plannedDieselLiters?: number | string | null,
 *   dieselRatePerLiter?: number | string | null,
 *   actualTotalKm?: number | string | null,
 * }} params
 */
export function calculatePerformanceSummary({
    plannedMileageKmPerLiter = null,
    plannedDieselLiters = null,
    dieselRatePerLiter = null,
    actualTotalKm = null,
} = {}) {
    const plannedMileage = Number(plannedMileageKmPerLiter ?? 0);
    const plannedDiesel = Number(plannedDieselLiters ?? 0);
    const dieselRate = Number(dieselRatePerLiter ?? 0);
    const actualKm = Number(actualTotalKm ?? 0);

    if (!Number.isFinite(plannedMileage) || plannedMileage <= 0 || !Number.isFinite(plannedDiesel) || plannedDiesel <= 0) {
        return null;
    }

    const expectedTotalKm = calculateExpectedTotalKm(plannedDiesel, plannedMileage);
    const totalTripCost = dieselRate > 0 ? roundCurrency(plannedDiesel * dieselRate) : null;
    const ratePerKm = totalTripCost !== null ? calculateRatePerKm(plannedDiesel, dieselRate, expectedTotalKm) : null;
    const hasActual = Number.isFinite(actualKm) && actualKm > 0;

    let extraKm = 0;
    let unusedKm = 0;
    let performanceStatus = hasActual ? 'within' : 'pending';

    if (hasActual) {
        const diffKm = roundCurrency(actualKm - expectedTotalKm);

        if (diffKm > 0) {
            extraKm = diffKm;
            performanceStatus = 'over';
        } else if (diffKm < 0) {
            unusedKm = Math.abs(diffKm);
            performanceStatus = 'under';
        }
    }

    const extraDiesel = extraKm > 0 && plannedMileage > 0 ? roundCurrency(extraKm / plannedMileage) : 0;
    const unusedDiesel = unusedKm > 0 && plannedMileage > 0 ? roundCurrency(unusedKm / plannedMileage) : 0;
    const extraDieselCost = extraDiesel > 0 && dieselRate > 0 ? roundCurrency(extraDiesel * dieselRate) : 0;
    const remainingAmount = calculateDifferenceAmount(unusedKm, ratePerKm ?? 0);

    return {
        calculation_version: CALCULATION_VERSION,
        planned_mileage_km_per_liter: roundCurrency(plannedMileage),
        planned_diesel_liters: roundCurrency(plannedDiesel),
        diesel_rate_per_liter: dieselRate > 0 ? roundCurrency(dieselRate) : null,
        total_trip_cost: totalTripCost,
        expected_total_km: expectedTotalKm,
        actual_total_km: hasActual ? roundCurrency(actualKm) : null,
        rate_per_km: ratePerKm,
        unused_km: hasActual ? roundCurrency(unusedKm) : null,
        extra_km: hasActual ? extraKm : null,
        remaining_amount: hasActual ? remainingAmount : null,
        performance_status: performanceStatus,
        unused_diesel_liters: hasActual ? unusedDiesel : null,
        extra_diesel_liters: hasActual ? extraDiesel : null,
        extra_diesel_cost: hasActual ? extraDieselCost : null,
    };
}

export const TripFuelCalculator = {
    CALCULATION_VERSION,
    calculateExpectedRunningKm,
    calculatePlannedDiesel,
    calculateExpectedTotalKm,
    calculateRatePerKm,
    calculateDifferenceAmount,
    calculatePerformanceSummary,
};
