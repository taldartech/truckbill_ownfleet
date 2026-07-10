export const LAYOUT_TYPE_OPTIONS = [
    {
        value: 'standard',
        label: 'Standard Truck',
        description: 'Front steering + rear axles + spare tyres',
    },
    {
        value: 'legacy_dual_rear',
        label: 'Legacy Format',
        description: 'Fixed layout: front pair, dual rear axle, 1 spare',
    },
];

export const TYRE_LAYOUT_PRESETS = [
  { id: '', label: 'Custom — configure manually' },
  {
    id: 'small_truck',
    label: 'Small Truck (6 tyres)',
    config: { steer_axles: 1, rear_axle_count: 1, rear_axles_dual: [false], spare_count: 2 },
  },
  {
    id: 'standard_truck',
    label: 'Standard Truck (10 tyres)',
    config: { steer_axles: 1, rear_axle_count: 2, rear_axles_dual: [true, true], spare_count: 0 },
  },
  {
    id: 'heavy_12',
    label: 'Heavy Truck (12 tyres + 2 spare)',
    config: { steer_axles: 1, rear_axle_count: 2, rear_axles_dual: [true, true], spare_count: 2 },
  },
  {
    id: 'multi_14',
    label: 'Multi-axle (14 tyres)',
    config: { steer_axles: 1, rear_axle_count: 3, rear_axles_dual: [true, true, true], spare_count: 0 },
  },
  {
    id: 'multi_16',
    label: 'Multi-axle (16 tyres + 2 spare)',
    config: { steer_axles: 1, rear_axle_count: 3, rear_axles_dual: [true, true, true], spare_count: 2 },
  },
  {
    id: 'long_18',
    label: 'Long Haul (18 tyres)',
    config: { steer_axles: 1, rear_axle_count: 4, rear_axles_dual: [true, true, true, true], spare_count: 0 },
  },
  {
    id: 'trailer_22',
    label: 'Trailer / Articulated (22 tyres)',
    config: { steer_axles: 1, rear_axle_count: 5, rear_axles_dual: [true, true, true, true, true], spare_count: 0 },
  },
];

export function layoutTypeLabel(type) {
    const match = LAYOUT_TYPE_OPTIONS.find((option) => option.value === type);

    return match?.label ?? type;
}

export function emptyAxleConfig() {
    return {
        steer_axles: 1,
        rear_axle_count: 2,
        rear_axles_dual: [true, true],
        spare_count: 0,
    };
}

export function axleConfigFromPayload({ steer_axles = 1, dual_axles = [], single_axles = [], spare_count = 0 }) {
    const dual = (dual_axles ?? []).map(Number).filter((n) => n > 0);
    const single = (single_axles ?? []).map(Number).filter((n) => n > 0);
    const rear_axle_count = Math.max(0, ...dual, ...single);
    const rear_axles_dual = [];

    for (let axle = 1; axle <= rear_axle_count; axle++) {
        rear_axles_dual.push(dual.includes(axle));
    }

    return {
        steer_axles: steer_axles ?? 1,
        rear_axle_count,
        rear_axles_dual,
        spare_count: spare_count ?? 0,
    };
}

export function payloadFromAxleConfig({ steer_axles, rear_axle_count, rear_axles_dual, spare_count }) {
    const dual_axles = [];
    const single_axles = [];

    for (let index = 0; index < rear_axle_count; index++) {
        const axle = index + 1;

        if (rear_axles_dual[index]) {
            dual_axles.push(axle);
        } else {
            single_axles.push(axle);
        }
    }

    return {
        steer_axles,
        dual_axles,
        single_axles,
        spare_count,
    };
}

export function resizeRearAxles(config, newCount) {
    const count = Math.max(0, Math.min(8, newCount));
    const rear_axles_dual = [...config.rear_axles_dual];

    while (rear_axles_dual.length < count) {
        rear_axles_dual.push(true);
    }

    while (rear_axles_dual.length > count) {
        rear_axles_dual.pop();
    }

    return {
        ...config,
        rear_axle_count: count,
        rear_axles_dual,
    };
}

export function findMatchingPresetId(config) {
    const serialized = JSON.stringify({
        steer_axles: config.steer_axles,
        rear_axle_count: config.rear_axle_count,
        rear_axles_dual: config.rear_axles_dual,
        spare_count: config.spare_count,
    });

    const match = TYRE_LAYOUT_PRESETS.find(
        (preset) =>
            preset.config &&
            JSON.stringify({
                steer_axles: preset.config.steer_axles,
                rear_axle_count: preset.config.rear_axle_count,
                rear_axles_dual: preset.config.rear_axles_dual,
                spare_count: preset.config.spare_count,
            }) === serialized,
    );

    return match?.id ?? '';
}
