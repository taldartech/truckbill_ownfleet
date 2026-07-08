import { formatDate } from './datetime';
import { formatDamageType } from './tyreDisplay';

function formatKm(value) {
    if (value === '' || value == null || !Number.isFinite(Number(value))) {
        return null;
    }

    return `${Number(value).toLocaleString('en-IN')} km`;
}

function formatCurrency(value) {
    if (value === '' || value == null || !Number.isFinite(Number(value))) {
        return null;
    }

    return `₹${Number(value).toLocaleString('en-IN')}`;
}

function resolveTruckNumber({ tyre, actionForm, vehicles }) {
    const active = tyre?.active_installation;

    if (active?.vehicle_registration) {
        return active.vehicle_registration;
    }

    if (actionForm?.vehicle_id && Array.isArray(vehicles)) {
        const vehicle = vehicles.find((row) => row.id === actionForm.vehicle_id);

        return vehicle?.registration_number ?? null;
    }

    return null;
}

function resolvePositionLabel({ tyre, actionForm, positionOptions, action }) {
    if (action === 'install' && actionForm?.position_code && Array.isArray(positionOptions)) {
        const match = positionOptions.find((option) => option.value === actionForm.position_code);

        return match?.label ?? actionForm.position_code;
    }

    return tyre?.active_installation?.position_label ?? null;
}

export function resolveTyreActionContext({ tyre, actionForm, vehicles, positionOptions, action }) {
    return {
        tyreSerial: tyre?.serial_number ?? '—',
        truckNumber: resolveTruckNumber({ tyre, actionForm, vehicles }) ?? '—',
        position: resolvePositionLabel({ tyre, actionForm, positionOptions, action }) ?? '',
    };
}

function findOptionLabel(options, value) {
    if (!value || !Array.isArray(options)) {
        return value ?? '—';
    }

    return options.find((option) => option.value === value)?.label ?? value;
}

export function buildTyreActionConfirmSummary({
    action,
    tyre,
    actionForm,
    damageModalMode,
    vehicles,
    stockTyres,
    rotatePositionOptions,
    selectedRotateTarget,
    calculatedStintKm,
    installPositionSelectOptions,
}) {
    const context = resolveTyreActionContext({
        tyre,
        actionForm,
        vehicles,
        positionOptions: installPositionSelectOptions,
        action,
    });

    const summaries = {
        install: {
            title: 'Confirm Install Tyre',
            message: 'Are you sure you want to install this tyre on the truck?',
            confirmLabel: 'Yes, Install Tyre',
            iconTone: 'primary',
            items: [
                `Tyre will be installed on truck ${context.truckNumber}`,
                `Position will be set to ${findOptionLabel(installPositionSelectOptions, actionForm.position_code)}`,
                `Installation date will be saved as ${formatDate(actionForm.installed_at)}`,
                `Meter reading at install will be saved as ${formatKm(actionForm.km_at_installation) ?? '—'}`,
                'Tyre status will change to Installed',
            ],
        },
        rotate: {
            title: 'Confirm Rotate Tyre',
            message: 'Are you sure you want to rotate this tyre?',
            confirmLabel: 'Yes, Rotate Tyre',
            iconTone: 'warning',
            items: [
                `Tyre will move from ${tyre?.active_installation?.position_label ?? '—'} to ${findOptionLabel(rotatePositionOptions, actionForm.new_position)}`,
                ...(selectedRotateTarget?.occupied
                    ? [`Will swap with tyre ${selectedRotateTarget.tyre_serial} at ${selectedRotateTarget.label}`]
                    : []),
                `Rotation date will be saved as ${formatDate(actionForm.rotation_date)}`,
                `Current meter reading will be saved as ${formatKm(actionForm.odometer_km) ?? '—'}`,
                ...(calculatedStintKm != null ? [`KM on truck will be recorded as ${formatKm(calculatedStintKm)}`] : []),
                `Rotation cost will be saved as ${formatCurrency(actionForm.rotation_cost) ?? '₹0'}`,
                'Truck running meter (odometer) will be updated',
            ],
        },
        replace: {
            title: 'Confirm Replace Tyre',
            message: 'Are you sure you want to replace this tyre on the truck?',
            confirmLabel: 'Yes, Replace Tyre',
            iconTone: 'primary',
            items: [
                `Current tyre ${context.tyreSerial} will be removed from ${context.position || 'position'}`,
                actionForm.new_tyre_id
                    ? `New tyre ${findOptionLabel(
                          stockTyres?.map((row) => ({ value: row.id, label: row.serial_number })),
                          actionForm.new_tyre_id,
                      )} will be installed`
                    : 'Tyre will be removed without installing a replacement',
                `Replace date will be saved as ${formatDate(actionForm.replaced_at)}`,
                `Current meter reading will be saved as ${formatKm(actionForm.odometer_km) ?? '—'}`,
                ...(calculatedStintKm != null ? [`KM on truck will be recorded as ${formatKm(calculatedStintKm)}`] : []),
            ],
        },
        remove: {
            title: 'Confirm Remove Tyre',
            message: 'Are you sure you want to remove this tyre from the truck?',
            confirmLabel: 'Yes, Remove Tyre',
            iconTone: 'warning',
            items: [
                `Tyre will be removed from truck ${context.truckNumber}`,
                `Remove date will be saved as ${formatDate(actionForm.removed_at)}`,
                `Current meter reading will be saved as ${formatKm(actionForm.odometer_km) ?? '—'}`,
                ...(calculatedStintKm != null ? [`KM on truck will be recorded as ${formatKm(calculatedStintKm)}`] : []),
                'Tyre status will change to In Stock',
            ],
        },
        updateKm: {
            title: 'Confirm Meter Reading Update',
            message: 'Are you sure you want to update the meter reading for this tyre stint?',
            confirmLabel: 'Yes, Update Reading',
            iconTone: 'primary',
            items: [
                `Current meter reading will be updated to ${formatKm(actionForm.odometer_km) ?? '—'}`,
                ...(calculatedStintKm != null ? [`KM on truck will be recalculated as ${formatKm(calculatedStintKm)}`] : []),
                'Truck running meter (odometer) will be updated',
            ],
        },
        damage: {
            title: damageModalMode === 'recapped' ? 'Confirm Regrip' : 'Confirm Repair',
            message:
                damageModalMode === 'recapped'
                    ? 'Are you sure you want to record regrip for this tyre?'
                    : 'Are you sure you want to record this repair?',
            confirmLabel: damageModalMode === 'recapped' ? 'Yes, Save Regrip' : 'Yes, Save Repair',
            iconTone: damageModalMode === 'recapped' ? 'primary' : 'danger',
            items: [
                damageModalMode === 'recapped'
                    ? `Expected life will be updated to ${formatKm(actionForm.expected_life_km) ?? '—'}`
                    : `Repair type will be saved as ${formatDamageType(actionForm.damage_type)}`,
                `Date will be saved as ${formatDate(actionForm.occurred_at)}`,
                `Current meter reading will be saved as ${formatKm(actionForm.odometer_km) ?? '—'}`,
                `${damageModalMode === 'recapped' ? 'Regrip' : 'Repair'} cost will be saved as ${formatCurrency(actionForm.repair_cost) ?? '—'}`,
                ...(damageModalMode === 'recapped'
                    ? ['Tyre expected life will be updated after regrip']
                    : ['A repair record will be added to tyre history']),
            ],
        },
        scrap: {
            title: 'Confirm Scrap / Sell',
            message: 'Are you sure you want to scrap or sell this tyre?',
            confirmLabel: 'Yes, Scrap / Sell',
            iconTone: 'danger',
            items: [
                `Scrap date will be saved as ${formatDate(actionForm.scrapped_at)}`,
                ...(actionForm.scrap_sale_amount
                    ? [`Sale amount will be saved as ${formatCurrency(actionForm.scrap_sale_amount)}`]
                    : []),
                'Tyre status will change to Scrapped',
            ],
        },
        inspection: {
            title: 'Confirm Inspection',
            message: 'Are you sure you want to save this inspection?',
            confirmLabel: 'Yes, Save Inspection',
            iconTone: 'primary',
            items: [
                `Inspection date will be saved as ${formatDate(actionForm.inspected_at)}`,
                `Result will be saved as ${actionForm.inspection_result || 'PASS'}`,
                ...(actionForm.tread_depth_mm != null && actionForm.tread_depth_mm !== ''
                    ? [`Tread depth will be saved as ${actionForm.tread_depth_mm} mm`]
                    : []),
                'An inspection record will be added to tyre history',
            ],
        },
    };

    const summary = summaries[action];

    if (!summary) {
        return null;
    }

    return {
        ...summary,
        context,
    };
}
