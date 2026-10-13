export const BATTERY_TYPE_OPTIONS = Object.freeze([
    { value: 'LEAD_ACID', label: 'Lead Acid (Flooded)' },
    { value: 'MAINTENANCE_FREE_SMF', label: 'Maintenance Free (SMF)' },
    { value: 'TUBULAR', label: 'Tubular' },
    { value: 'LITHIUM_ION', label: 'Lithium-Ion' },
    { value: 'GEL', label: 'Gel Battery' },
]);

export const BATTERY_STATUS_META = Object.freeze({
    IN_STOCK: { label: 'In Stock', badgeClass: 'bg-info text-dark', icon: 'fas fa-boxes', textClass: 'text-info' },
    INSTALLED: { label: 'Installed', badgeClass: 'bg-success', icon: 'fas fa-truck', textClass: 'text-success' },
    REMOVED: { label: 'Removed / Standby', badgeClass: 'bg-warning text-dark', icon: 'fas fa-pause-circle', textClass: 'text-warning' },
    UNDER_INSPECTION: { label: 'Under Inspection', badgeClass: 'bg-primary', icon: 'fas fa-stethoscope', textClass: 'text-primary' },
    RETURNED: { label: 'Returned', badgeClass: 'bg-dark', icon: 'fas fa-undo', textClass: 'text-dark' },
    SCRAPPED: { label: 'Scrapped', badgeClass: 'bg-secondary', icon: 'fas fa-recycle', textClass: 'text-secondary' },
});

export const BATTERY_STATUS_OPTIONS = Object.freeze(
    Object.entries(BATTERY_STATUS_META).map(([value, meta]) => ({ value, label: meta.label })),
);

export const BATTERY_REMOVAL_REASON_OPTIONS = Object.freeze([
    { value: 'BATTERY_FAILED', label: 'Battery Failed' },
    { value: 'LOW_VOLTAGE', label: 'Low Voltage / Not Charging' },
    { value: 'SCHEDULED_AGING', label: 'Aging / Scheduled Replacement' },
    { value: 'PHYSICAL_DAMAGE', label: 'Physical Damage / Terminal Leak' },
    { value: 'WARRANTY_CLAIM', label: 'Warranty Claim / Dealer Return' },
    { value: 'TRANSFERRED', label: 'Transferred to Other Truck' },
    { value: 'VEHICLE_MAINTENANCE', label: 'Removed for Vehicle Maintenance' },
]);

export const BATTERY_CONDITION_OPTIONS = Object.freeze([
    { value: 'GOOD', label: 'Good', badgeClass: 'bg-success' },
    { value: 'FAIR', label: 'Fair / Monitor', badgeClass: 'bg-info text-dark' },
    { value: 'WEAK', label: 'Weak / Needs Attention', badgeClass: 'bg-warning text-dark' },
    { value: 'FAILED', label: 'Failed / Replace', badgeClass: 'bg-danger' },
]);

export const BATTERY_WARRANTY_META = Object.freeze({
    active: { label: 'In Warranty', badgeClass: 'bg-success-subtle text-success border border-success-subtle' },
    expiring: { label: 'Expiring Soon', badgeClass: 'bg-warning-subtle text-warning-emphasis border border-warning-subtle' },
    expired: { label: 'Expired', badgeClass: 'bg-secondary-subtle text-secondary border' },
    none: { label: 'No Warranty', badgeClass: 'bg-light text-muted border' },
});

export const BATTERY_SLOT_PRESETS = Object.freeze([
    'Main #1',
    'Main #2',
    'Slot 1 (Main)',
    'Slot 2 (Auxiliary)',
    'Reefer / Tail-lift',
]);

export function batteryTypeLabel(type) {
    return BATTERY_TYPE_OPTIONS.find((option) => option.value === type)?.label ?? type ?? '';
}

export function batteryStatusLabel(status) {
    return BATTERY_STATUS_META[status]?.label ?? status ?? '';
}

export function batteryStatusBadgeClass(status) {
    return BATTERY_STATUS_META[status]?.badgeClass ?? 'bg-light text-dark border';
}

export function batteryRemovalReasonLabel(reason) {
    return BATTERY_REMOVAL_REASON_OPTIONS.find((option) => option.value === reason)?.label ?? reason ?? '';
}

export function batteryConditionOption(condition) {
    return BATTERY_CONDITION_OPTIONS.find((option) => option.value === condition) ?? null;
}

export function batteryWarrantyMeta(status) {
    return BATTERY_WARRANTY_META[status] ?? BATTERY_WARRANTY_META.none;
}

export function formatKm(value) {
    return `${Math.round(Number(value || 0)).toLocaleString('en-IN')} KM`;
}

/**
 * @param {{ id: string, serial_number: string, brand?: string, capacity_ah?: number, voltage?: string, status?: string }} battery
 */
export function availableBatteryLabel(battery) {
    const specs = [battery.brand, battery.capacity_ah ? `${Number(battery.capacity_ah)} Ah` : null, battery.voltage]
        .filter(Boolean)
        .join(', ');
    const standby = battery.status === 'REMOVED' ? ' — Standby (used)' : '';

    return `${battery.serial_number}${specs ? ` (${specs})` : ''}${standby}`;
}
