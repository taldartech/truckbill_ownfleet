function isLeftCode(code) {
    return (
        code.includes('_L_') ||
        code.endsWith('_L') ||
        code.includes('LEFT') ||
        code === 'FRONT_LEFT'
    );
}

function isRightCode(code) {
    return (
        code.includes('_R_') ||
        code.endsWith('_R') ||
        code.includes('RIGHT') ||
        code === 'FRONT_RIGHT'
    );
}

function isOuterCode(code) {
    return code.includes('_OUT') || code.includes('OUTER');
}

function isInnerCode(code) {
    return code.includes('_IN') || code.includes('INNER');
}

function extractAxleNumber(slot) {
    if (slot.axle != null && slot.axle > 0) {
        return slot.axle;
    }

    const match = slot.position.match(/AX(\d+)/i);
    if (match) {
        return Number(match[1]);
    }

    return slot.axle === 0 ? 0 : 1;
}

function findSide(slots, side) {
    return (
        slots.find((slot) => (side === 'L' ? isLeftCode(slot.position) : isRightCode(slot.position))) ?? null
    );
}

function findDual(slots, side, role) {
    return (
        slots.find((slot) => {
            const sideMatch = side === 'L' ? isLeftCode(slot.position) : isRightCode(slot.position);
            const roleMatch = role === 'OUT' ? isOuterCode(slot.position) : isInnerCode(slot.position);

            return sideMatch && roleMatch;
        }) ?? null
    );
}

function buildAxleRow(axleKey, group) {
    const slots = group.slots;
    const isSteer =
        group.axle === 0 ||
        slots.some(
            (slot) =>
                slot.position.startsWith('STEER') ||
                slot.position.startsWith('FRONT_') ||
                slot.position_label?.toLowerCase().includes('steer') ||
                slot.position_label?.toLowerCase().includes('front')
        );
    const isDual = slots.some((slot) => isOuterCode(slot.position) || isInnerCode(slot.position));

    if (isSteer) {
        return {
            key: axleKey,
            type: 'steer',
            label: 'Steer (Front)',
            left: findSide(slots, 'L'),
            right: findSide(slots, 'R'),
        };
    }

    const axleNumber = extractAxleNumber(slots[0]);

    if (isDual) {
        return {
            key: axleKey,
            type: 'dual',
            label: `Axle ${axleNumber}`,
            left: {
                outer: findDual(slots, 'L', 'OUT'),
                inner: findDual(slots, 'L', 'IN'),
            },
            right: {
                inner: findDual(slots, 'R', 'IN'),
                outer: findDual(slots, 'R', 'OUT'),
            },
        };
    }

    return {
        key: axleKey,
        type: 'single',
        label: `Axle ${axleNumber}`,
        left: findSide(slots, 'L'),
        right: findSide(slots, 'R'),
    };
}

/**
 * @param {Array} positions
 * @returns {{ rows: Array, spares: Array }}
 */
export function buildTruckChassisLayout(positions) {
    const spares = [];
    const axleMap = new Map();

    for (const slot of positions) {
        if (slot.is_spare) {
            spares.push(slot);
            continue;
        }

        const axleKey = slot.axle != null ? `axle-${slot.axle}` : `pos-${slot.position}`;
        if (!axleMap.has(axleKey)) {
            axleMap.set(axleKey, { axle: slot.axle ?? extractAxleNumber(slot), slots: [] });
        }
        axleMap.get(axleKey).slots.push(slot);
    }

    const rows = [...axleMap.entries()]
        .sort((a, b) => {
            if (a[1].axle !== b[1].axle) {
                return a[1].axle - b[1].axle;
            }

            return a[0].localeCompare(b[0]);
        })
        .map(([axleKey, group]) => buildAxleRow(axleKey, group));

    return { rows, spares };
}
