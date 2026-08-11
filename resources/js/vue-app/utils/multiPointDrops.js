import { calculateLegFreight, parseNumeric } from '../api/trips';

function round3(value) {
    return Math.round((Number(value) || 0) * 1000) / 1000;
}

function round2(value) {
    return Math.round((Number(value) || 0) * 100) / 100;
}

function consolidationKey(drop) {
    const stationId = String(drop?.to_station_id ?? '');
    const consigneeId = String(drop?.consignee_id ?? '');
    const lr = String(drop?.lr_number ?? '').trim().toLowerCase();

    return `${stationId}|${consigneeId}|${lr}`;
}

export function emptyAllocation(pickupKey = '') {
    return {
        _key: `alloc-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        pickup_key: pickupKey ? String(pickupKey) : '',
        weight_ton: '',
        freight_rate: '',
        freight_amount: 0,
        freight_auto_calculate: true,
        distance_km: '',
        ewaybill_no: '',
        ewaybill_expiry: '',
        no_of_units: '',
        goods_value: '',
    };
}

export function dropAllocatedWeight(drop) {
    return round3(
        (drop?.allocations ?? []).reduce((sum, allocation) => sum + parseNumeric(allocation?.weight_ton), 0),
    );
}

export function dropAllocatedFreight(drop) {
    return round2(
        (drop?.allocations ?? []).reduce((sum, allocation) => {
            if (allocation?.freight_auto_calculate) {
                return sum + calculateLegFreight(allocation.weight_ton, allocation.freight_rate);
            }

            return sum + parseNumeric(allocation?.freight_amount);
        }, 0),
    );
}

export function syncAllocationFreight(allocation) {
    if (!allocation) {
        return;
    }

    if (allocation.freight_auto_calculate) {
        allocation.freight_amount = calculateLegFreight(allocation.weight_ton, allocation.freight_rate);
    }
}

export function syncDropWeightFromAllocations(drop) {
    if (!drop) {
        return;
    }

    (drop.allocations ?? []).forEach((allocation) => {
        syncAllocationFreight(allocation);
    });

    const total = dropAllocatedWeight(drop);
    drop.weight_ton = total > 0 ? total : '';
    drop.freight_amount = dropAllocatedFreight(drop);
}

/**
 * Merge nested pickup drops into trip-level drops with explicit pickup allocations.
 *
 * @param {Array} pickups
 * @returns {Array}
 */
export function consolidateNestedDropsToTripDrops(pickups = []) {
    const groups = new Map();
    let sequence = 1;

    (pickups ?? []).forEach((pickup) => {
        const pickupKey = String(pickup?._key ?? '');

        (pickup?.drops ?? []).forEach((drop) => {
            const key = consolidationKey(drop);
            const weight = parseNumeric(drop?.weight_ton);
            const freight = parseNumeric(drop?.freight_amount);
            const existing = groups.get(key);

            const allocationPayload = {
                _key: `alloc-${drop.id ?? sequence}`,
                pickup_key: pickupKey,
                weight_ton: weight > 0 ? weight : '',
                freight_rate:
                    drop.freight_rate === '' || drop.freight_rate == null
                        ? ''
                        : Number(drop.freight_rate),
                freight_amount: freight,
                freight_auto_calculate: false,
                distance_km:
                    drop.distance_km == null || drop.distance_km === '' || Number(drop.distance_km) <= 0
                        ? ''
                        : Number(drop.distance_km),
                ewaybill_no: drop.ewaybill_no ?? '',
                ewaybill_expiry: drop.ewaybill_expiry
                    ? String(drop.ewaybill_expiry).slice(0, 10)
                    : '',
                no_of_units:
                    drop.no_of_units == null || drop.no_of_units === ''
                        ? ''
                        : Number(drop.no_of_units),
                goods_value:
                    drop.goods_value == null || drop.goods_value === ''
                        ? ''
                        : Number(drop.goods_value),
            };

            if (!existing) {
                groups.set(key, {
                    _key: drop._key || `drop-c-${sequence}`,
                    drop_code: drop.drop_code || `D${String(sequence).padStart(3, '0')}`,
                    to_station_id: drop.to_station_id ? String(drop.to_station_id) : '',
                    consignee_id: drop.consignee_id ? String(drop.consignee_id) : '',
                    to_address: drop.to_address ?? '',
                    weight_ton: weight > 0 ? weight : '',
                    freight_amount: freight,
                    lr_number: drop.lr_number ?? '',
                    remarks: drop.remarks ?? '',
                    allocations: pickupKey ? [allocationPayload] : [],
                });
                sequence += 1;

                return;
            }

            const nextWeight = round3(parseNumeric(existing.weight_ton) + weight);
            existing.weight_ton = nextWeight > 0 ? nextWeight : '';
            existing.freight_amount = round2(parseNumeric(existing.freight_amount) + freight);

            if (pickupKey && weight > 0) {
                const samePickup = existing.allocations.find(
                    (allocation) => String(allocation.pickup_key) === pickupKey,
                );

                if (samePickup) {
                    samePickup.weight_ton = round3(parseNumeric(samePickup.weight_ton) + weight);
                    samePickup.freight_amount = round2(
                        parseNumeric(samePickup.freight_amount) + freight,
                    );
                    if (!samePickup.freight_rate && allocationPayload.freight_rate !== '') {
                        samePickup.freight_rate = allocationPayload.freight_rate;
                    }
                    if (!samePickup.distance_km && allocationPayload.distance_km) {
                        samePickup.distance_km = allocationPayload.distance_km;
                    }
                    if (!samePickup.ewaybill_no && allocationPayload.ewaybill_no) {
                        samePickup.ewaybill_no = allocationPayload.ewaybill_no;
                    }
                    if (!samePickup.ewaybill_expiry && allocationPayload.ewaybill_expiry) {
                        samePickup.ewaybill_expiry = allocationPayload.ewaybill_expiry;
                    }
                    if (!samePickup.no_of_units && allocationPayload.no_of_units) {
                        samePickup.no_of_units = allocationPayload.no_of_units;
                    }
                    if (!samePickup.goods_value && allocationPayload.goods_value) {
                        samePickup.goods_value = allocationPayload.goods_value;
                    }
                } else {
                    existing.allocations.push({
                        ...allocationPayload,
                        _key: `alloc-${drop.id ?? sequence}-${existing.allocations.length}`,
                    });
                }
            }

            if (!existing.remarks && drop.remarks) {
                existing.remarks = drop.remarks;
            }
        });
    });

    return Array.from(groups.values()).map((drop, index) => {
        syncDropWeightFromAllocations(drop);

        return {
            ...drop,
            drop_code: drop.drop_code || `D${String(index + 1).padStart(3, '0')}`,
            weight_ton: dropAllocatedWeight(drop) || drop.weight_ton,
            freight_amount: dropAllocatedFreight(drop),
        };
    });
}

/**
 * Expand trip-level drops (with pickup allocations) into nested pickup.drops for the API.
 *
 * @param {Array} pickups
 * @param {Array} tripDrops
 * @returns {{ pickups: Array, error: string|null }}
 */
export function allocateTripDropsToPickups(pickups = [], tripDrops = []) {
    const pickupIndexByKey = new Map();
    (pickups ?? []).forEach((pickup, index) => {
        if (pickup?._key) {
            pickupIndexByKey.set(String(pickup._key), index);
        }
    });

    const remaining = (pickups ?? []).map((pickup) => round3(parseNumeric(pickup?.weight_ton)));
    const nestedDrops = (pickups ?? []).map(() => []);
    let dropCodeSeq = 1;

    for (let dropIndex = 0; dropIndex < (tripDrops ?? []).length; dropIndex += 1) {
        const tripDrop = tripDrops[dropIndex];
        const dropLabel = tripDrop?.drop_code || `Drop ${dropIndex + 1}`;
        const allocations = (tripDrop?.allocations ?? []).filter(
            (allocation) => allocation?.pickup_key && parseNumeric(allocation?.weight_ton) > 0,
        );

        if (allocations.length === 0) {
            return {
                pickups: [],
                error: `${dropLabel}: select at least one pickup and enter allocated weight.`,
            };
        }

        const allocWeightTotal = round3(
            allocations.reduce((sum, allocation) => sum + parseNumeric(allocation.weight_ton), 0),
        );

        if (allocWeightTotal <= 0) {
            return {
                pickups: [],
                error: `${dropLabel}: allocated weight must be greater than zero.`,
            };
        }

        for (let partIndex = 0; partIndex < allocations.length; partIndex += 1) {
            const allocation = allocations[partIndex];
            const pickupKey = String(allocation.pickup_key);
            const pickupIndex = pickupIndexByKey.get(pickupKey);

            if (pickupIndex === undefined) {
                return {
                    pickups: [],
                    error: `${dropLabel}: selected pickup is missing. Re-select pickups on this drop.`,
                };
            }

            const weight = round3(parseNumeric(allocation.weight_ton));

            if (weight - remaining[pickupIndex] > 0.0005) {
                const pickupCode = pickups[pickupIndex]?.pickup_code || `Pickup ${pickupIndex + 1}`;

                return {
                    pickups: [],
                    error: `${dropLabel}: allocation to ${pickupCode} (${weight}) exceeds remaining pickup weight (${remaining[pickupIndex]}).`,
                };
            }

            remaining[pickupIndex] = round3(remaining[pickupIndex] - weight);

            const freightPart = allocation.freight_auto_calculate
                ? calculateLegFreight(weight, allocation.freight_rate)
                : parseNumeric(allocation.freight_amount);

            const dropCode = `D${String(dropCodeSeq).padStart(3, '0')}`;
            dropCodeSeq += 1;

            nestedDrops[pickupIndex].push({
                drop_code: dropCode,
                to_station_id: tripDrop.to_station_id || null,
                consignee_id: tripDrop.consignee_id || '',
                to_address: tripDrop.to_address ?? '',
                weight_ton: weight,
                freight_rate:
                    allocation.freight_rate === '' || allocation.freight_rate == null
                        ? null
                        : Number(allocation.freight_rate),
                freight_amount: freightPart > 0 ? freightPart : 0,
                freight_auto_calculate: false,
                distance_km:
                    allocation.distance_km === '' || allocation.distance_km == null || Number(allocation.distance_km) <= 0
                        ? ''
                        : Number(allocation.distance_km),
                ewaybill_no: allocation.ewaybill_no?.trim?.() || allocation.ewaybill_no || '',
                ewaybill_expiry: allocation.ewaybill_expiry || '',
                no_of_units:
                    allocation.no_of_units === '' || allocation.no_of_units == null
                        ? ''
                        : Number(allocation.no_of_units),
                goods_value:
                    allocation.goods_value === '' || allocation.goods_value == null
                        ? ''
                        : Number(allocation.goods_value),
                lr_number: tripDrop.lr_number ?? '',
                remarks: tripDrop.remarks ?? '',
            });
        }
    }

    for (let pickupIndex = 0; pickupIndex < remaining.length; pickupIndex += 1) {
        if (remaining[pickupIndex] > 0.0005) {
            const pickupCode = pickups[pickupIndex]?.pickup_code || `Pickup ${pickupIndex + 1}`;

            return {
                pickups: [],
                error: `${pickupCode}: allocate remaining ${remaining[pickupIndex]} ton to drops.`,
            };
        }

        if ((nestedDrops[pickupIndex] ?? []).length === 0) {
            const pickupCode = pickups[pickupIndex]?.pickup_code || `Pickup ${pickupIndex + 1}`;

            return {
                pickups: [],
                error: `${pickupCode}: link this pickup to at least one drop allocation.`,
            };
        }
    }

    return {
        pickups: (pickups ?? []).map((pickup, index) => ({
            ...pickup,
            drops: nestedDrops[index] ?? [],
        })),
        error: null,
    };
}

/**
 * Live balance helpers for the form UI (supports multi-pickup drops and shared pickups).
 */
export function multiPointWeightBalance(pickups = [], drops = []) {
    const pickupTotals = new Map();

    (pickups ?? []).forEach((pickup) => {
        const key = String(pickup?._key ?? '');
        if (!key) {
            return;
        }

        pickupTotals.set(key, {
            pickup,
            pickupWeight: round3(parseNumeric(pickup?.weight_ton)),
            allocated: 0,
        });
    });

    let dropTotal = 0;

    (drops ?? []).forEach((drop) => {
        const dropWeight = dropAllocatedWeight(drop);
        dropTotal = round3(dropTotal + dropWeight);

        (drop?.allocations ?? []).forEach((allocation) => {
            const key = String(allocation?.pickup_key ?? '');
            const row = pickupTotals.get(key);

            if (!row) {
                return;
            }

            row.allocated = round3(row.allocated + parseNumeric(allocation?.weight_ton));
        });
    });

    const pickupTotal = round3(
        Array.from(pickupTotals.values()).reduce((sum, row) => sum + row.pickupWeight, 0),
    );

    const perPickup = Array.from(pickupTotals.values()).map((row) => ({
        pickup_key: String(row.pickup._key),
        pickup_code: row.pickup.pickup_code || '',
        pickupWeight: row.pickupWeight,
        allocated: row.allocated,
        remaining: round3(row.pickupWeight - row.allocated),
        excess: round3(Math.max(0, row.allocated - row.pickupWeight)),
    }));

    const excessPickups = perPickup.filter((row) => row.excess > 0.0005);
    const remainingPickups = perPickup.filter((row) => row.remaining > 0.0005);

    return {
        pickupTotal,
        dropTotal,
        remainingPickup: round3(Math.max(0, pickupTotal - dropTotal)),
        excessDrop: round3(Math.max(0, dropTotal - pickupTotal)),
        perPickup,
        excessPickups,
        remainingPickups,
        isBalanced:
            Math.abs(pickupTotal - dropTotal) <= 0.0005
            && excessPickups.length === 0
            && remainingPickups.length === 0,
    };
}
